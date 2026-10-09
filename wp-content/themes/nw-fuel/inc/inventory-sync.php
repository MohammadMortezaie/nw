<?php
/**
 * Nightly inventory CSV → WooCommerce product sync.
 *
 * Runs at 1:00 AM America/Los_Angeles against uploads/nw-fuel-inventory/latest.csv
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_INVENTORY_SYNC_HOOK       = 'nw_fuel_inventory_sync_event';
const NW_FUEL_INVENTORY_SYNC_BATCH_HOOK = 'nw_fuel_inventory_sync_batch';
const NW_FUEL_INVENTORY_SYNC_TZ         = 'America/Los_Angeles';
const NW_FUEL_INVENTORY_SYNC_STATE      = 'nw_fuel_inventory_sync_state';
const NW_FUEL_INVENTORY_SYNC_CHUNK      = 80;

add_action('init', 'nw_fuel_ensure_inventory_sync_scheduled', 20);
add_action(NW_FUEL_INVENTORY_SYNC_HOOK, 'nw_fuel_handle_inventory_sync_cron');
add_action(NW_FUEL_INVENTORY_SYNC_BATCH_HOOK, 'nw_fuel_handle_inventory_sync_batch');
add_action('admin_post_nw_fuel_run_inventory_sync', 'nw_fuel_handle_run_inventory_sync');
add_action('nw_fuel_inventory_csv_uploaded', 'nw_fuel_maybe_note_csv_ready_for_sync');

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('nw-fuel sync-inventory', 'nw_fuel_cli_sync_inventory');
}

/**
 * Ensure a single event is always scheduled for the next 1:00 AM PST/PDT.
 */
function nw_fuel_ensure_inventory_sync_scheduled(): void
{
    if (function_exists('nw_fuel_inventory_is_nightly_mode') && ! nw_fuel_inventory_is_nightly_mode()) {
        wp_clear_scheduled_hook(NW_FUEL_INVENTORY_SYNC_HOOK);
        return;
    }
    if (wp_next_scheduled(NW_FUEL_INVENTORY_SYNC_HOOK)) {
        return;
    }
    wp_schedule_single_event(nw_fuel_next_1am_pacific_timestamp(), NW_FUEL_INVENTORY_SYNC_HOOK);
}

/**
 * Next 1:00 AM in America/Los_Angeles as a Unix timestamp (UTC epoch).
 */
function nw_fuel_next_1am_pacific_timestamp(): int
{
    try {
        $tz  = new DateTimeZone(NW_FUEL_INVENTORY_SYNC_TZ);
        $now = new DateTimeImmutable('now', $tz);
        $next = $now->setTime(1, 0, 0);
        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }
        return $next->getTimestamp();
    } catch (Exception $e) {
        return time() + DAY_IN_SECONDS;
    }
}

/**
 * Cron callback: sync then reschedule tomorrow 1 AM Pacific.
 */
function nw_fuel_handle_inventory_sync_cron(): void
{
    if (function_exists('nw_fuel_inventory_is_nightly_mode') && ! nw_fuel_inventory_is_nightly_mode()) {
        wp_clear_scheduled_hook(NW_FUEL_INVENTORY_SYNC_HOOK);
        return;
    }
    nw_fuel_run_inventory_sync(['source' => 'cron', 'wait' => false]);
    // Clear any stray duplicates then schedule next night.
    wp_clear_scheduled_hook(NW_FUEL_INVENTORY_SYNC_HOOK);
    wp_schedule_single_event(nw_fuel_next_1am_pacific_timestamp(), NW_FUEL_INVENTORY_SYNC_HOOK);
}

/**
 * Admin "Run sync now".
 */
function nw_fuel_handle_run_inventory_sync(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_inventory_sync_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_sync_nonce'])), 'nw_fuel_run_inventory_sync')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $result = nw_fuel_run_inventory_sync(['source' => 'admin']);
    set_transient('nw_fuel_inventory_sync_flash', $result, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-inventory-api&synced=1'));
    exit;
}

/**
 * @param array<string, mixed> $meta Upload meta (unused; hook marker).
 */
function nw_fuel_maybe_note_csv_ready_for_sync(array $meta): void
{
    update_option('nw_fuel_inventory_csv_pending_sync', 1, false);
}

/**
 * WP-CLI: wp nw-fuel sync-inventory
 *
 * @param list<string>             $args
 * @param array<string, string>    $assoc_args
 */
function nw_fuel_cli_sync_inventory(array $args, array $assoc_args): void
{
    unset($args, $assoc_args);
    $result = nw_fuel_run_inventory_sync(['source' => 'cli', 'wait' => true]);
    if (! empty($result['ok'])) {
        WP_CLI::success(sprintf(
            'Sync complete: created=%d updated=%d skipped=%d errors=%d',
            (int) ($result['created'] ?? 0),
            (int) ($result['updated'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            (int) ($result['errors'] ?? 0)
        ));
        return;
    }
    WP_CLI::error((string) ($result['message'] ?? 'Sync failed'));
}

/**
 * Current background sync state.
 *
 * @return array<string, mixed>
 */
function nw_fuel_inventory_sync_state(): array
{
    $state = get_option(NW_FUEL_INVENTORY_SYNC_STATE, []);
    return is_array($state) ? $state : [];
}

/**
 * Whether a sync batch is currently running and recently updated.
 */
function nw_fuel_inventory_sync_is_active(): bool
{
    $state = nw_fuel_inventory_sync_state();
    if (($state['status'] ?? '') !== 'running') {
        return false;
    }

    $updated = strtotime((string) ($state['updated_at'] ?? ''));
    if ($updated === false) {
        return false;
    }

    return (time() - $updated) < 10 * MINUTE_IN_SECONDS;
}

/**
 * Queue the next sync chunk (Action Scheduler, else WP-Cron).
 */
function nw_fuel_queue_inventory_sync_batch(): void
{
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action(NW_FUEL_INVENTORY_SYNC_BATCH_HOOK, [], 'nw-fuel-inventory');
        return;
    }

    if (! wp_next_scheduled(NW_FUEL_INVENTORY_SYNC_BATCH_HOOK)) {
        wp_schedule_single_event(time() + 1, NW_FUEL_INVENTORY_SYNC_BATCH_HOOK);
    }
    if (function_exists('spawn_cron')) {
        spawn_cron();
    }
}

/**
 * Cron / Action Scheduler: process one chunk, then queue the next.
 */
function nw_fuel_handle_inventory_sync_batch(): void
{
    $result = nw_fuel_process_inventory_sync_batch();
    if (! empty($result['running']) && empty($result['locked'])) {
        nw_fuel_queue_inventory_sync_batch();
    }
}

/**
 * Start inventory sync from latest.csv (chunked in the background).
 *
 * @param array{source?: string, replace?: bool, wait?: bool} $opts
 * @return array<string, mixed>
 */
function nw_fuel_run_inventory_sync(array $opts = []): array
{
    $source  = (string) ($opts['source'] ?? 'manual');
    $replace = ! empty($opts['replace']);
    $wait    = ! empty($opts['wait']) || $source === 'cli';
    $started = gmdate('c');

    if (nw_fuel_inventory_sync_is_active()) {
        $state = nw_fuel_inventory_sync_state();
        $result = [
            'ok'      => true,
            'running' => true,
            'message' => sprintf(
                'Sync already running: %d / %d rows.',
                (int) ($state['offset'] ?? 0),
                (int) ($state['total'] ?? 0)
            ),
            'source'  => (string) ($state['source'] ?? $source),
            'started' => (string) ($state['started'] ?? $started),
            'finished'=> '',
            'created' => (int) ($state['created'] ?? 0),
            'updated' => (int) ($state['updated'] ?? 0),
            'skipped' => (int) ($state['skipped'] ?? 0),
            'removed' => (int) ($state['removed'] ?? 0),
            'errors'  => (int) ($state['errors'] ?? 0),
            'rows'    => (int) ($state['total'] ?? 0),
        ];
        update_option('nw_fuel_inventory_sync_last', $result, false);
        return $result;
    }

    if (! nw_fuel_is_woocommerce_active() || ! function_exists('wc_get_product')) {
        $result = [
            'ok'      => false,
            'message' => 'WooCommerce is not active.',
            'source'  => $source,
            'started' => $started,
            'finished'=> gmdate('c'),
        ];
        update_option('nw_fuel_inventory_sync_last', $result, false);
        return $result;
    }

    $path = nw_fuel_inventory_latest_csv_path();
    if ($path === '' || ! is_readable($path)) {
        $result = [
            'ok'      => false,
            'message' => 'No latest.csv found. Upload a CSV or Excel file first.',
            'source'  => $source,
            'started' => $started,
            'finished'=> gmdate('c'),
        ];
        update_option('nw_fuel_inventory_sync_last', $result, false);
        return $result;
    }

    $parsed = nw_fuel_parse_inventory_csv_file($path);
    if (is_wp_error($parsed)) {
        $result = [
            'ok'      => false,
            'message' => $parsed->get_error_message(),
            'source'  => $source,
            'started' => $started,
            'finished'=> gmdate('c'),
        ];
        update_option('nw_fuel_inventory_sync_last', $result, false);
        return $result;
    }

    $total = count($parsed['rows']);
    $state = [
        'status'     => 'running',
        'source'     => $source,
        'replace'    => $replace,
        'csv_path'   => $path,
        'offset'     => 0,
        'total'      => $total,
        'created'    => 0,
        'updated'    => 0,
        'skipped'    => 0,
        'errors'     => 0,
        'removed'    => 0,
        'log'        => [],
        'keep_parts' => [],
        'started'    => $started,
        'updated_at' => $started,
    ];
    update_option(NW_FUEL_INVENTORY_SYNC_STATE, $state, false);

    $result = [
        'ok'      => true,
        'running' => true,
        'message' => sprintf('Sync started: %d rows in batches of %d.', $total, NW_FUEL_INVENTORY_SYNC_CHUNK),
        'source'  => $source,
        'csv_path'=> $path,
        'rows'    => $total,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'removed' => 0,
        'errors'  => 0,
        'started' => $started,
        'finished'=> '',
    ];
    update_option('nw_fuel_inventory_sync_last', $result, false);

    if ($wait) {
        do {
            $batch = nw_fuel_process_inventory_sync_batch();
        } while (! empty($batch['running']));

        $last = get_option('nw_fuel_inventory_sync_last', $result);
        return is_array($last) ? $last : $result;
    }

    // Cron already has a request: do the first chunk here, then queue the rest.
    if ($source === 'cron') {
        $batch = nw_fuel_process_inventory_sync_batch();
        if (! empty($batch['running'])) {
            nw_fuel_queue_inventory_sync_batch();
        }
        $last = get_option('nw_fuel_inventory_sync_last', $result);
        return is_array($last) ? $last : $result;
    }

    nw_fuel_queue_inventory_sync_batch();
    return $result;
}

/**
 * Apply one CSV chunk. Returns running=true when more rows remain.
 *
 * @return array<string, mixed>
 */
function nw_fuel_process_inventory_sync_batch(): array
{
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('admin');
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(120);
    }

    $lock_key = 'nw_fuel_inventory_sync_lock';
    $now      = time();
    $locked   = get_option($lock_key, 0);
    if (is_numeric($locked) && (int) $locked > ($now - 90)) {
        return ['ok' => true, 'running' => true, 'locked' => true, 'message' => 'Another batch is running.'];
    }
    update_option($lock_key, $now, false);

    $state = nw_fuel_inventory_sync_state();
    if (($state['status'] ?? '') !== 'running') {
        delete_option($lock_key);
        return ['ok' => true, 'running' => false, 'message' => 'No sync in progress.'];
    }

    $path = (string) ($state['csv_path'] ?? '');
    if ($path === '' || ! is_readable($path)) {
        $path = nw_fuel_inventory_latest_csv_path();
    }
    if ($path === '' || ! is_readable($path)) {
        delete_option($lock_key);
        return nw_fuel_finalize_inventory_sync($state, false, 'latest.csv disappeared during sync.');
    }

    $parsed = nw_fuel_parse_inventory_csv_file($path);
    if (is_wp_error($parsed)) {
        delete_option($lock_key);
        return nw_fuel_finalize_inventory_sync($state, false, $parsed->get_error_message());
    }

    $rows   = $parsed['rows'];
    $total  = count($rows);
    $offset = (int) ($state['offset'] ?? 0);
    $chunk  = array_slice($rows, $offset, NW_FUEL_INVENTORY_SYNC_CHUNK);

    $state['total'] = $total;
    $keep_parts     = is_array($state['keep_parts'] ?? null) ? $state['keep_parts'] : [];
    $log            = is_array($state['log'] ?? null) ? $state['log'] : [];

    foreach ($chunk as $row) {
        $part = trim((string) ($row['Part_Number'] ?? ''));
        if ($part === '') {
            $state['skipped'] = (int) $state['skipped'] + 1;
            continue;
        }
        $keep_parts[] = $part;

        try {
            $prices_only = (($state['source'] ?? '') !== 'manual_upload');
            $outcome     = nw_fuel_upsert_product_from_inventory_row($row, $prices_only);
            if ($outcome === 'created') {
                $state['created'] = (int) $state['created'] + 1;
            } elseif ($outcome === 'updated') {
                $state['updated'] = (int) $state['updated'] + 1;
            } else {
                $state['skipped'] = (int) $state['skipped'] + 1;
            }
        } catch (Throwable $e) {
            $state['errors'] = (int) $state['errors'] + 1;
            if (count($log) < 25) {
                $log[] = sprintf('Error on %s: %s', $part, $e->getMessage());
            }
        }
    }

    $state['offset']     = $offset + count($chunk);
    $state['keep_parts'] = $keep_parts;
    $state['log']        = $log;
    $state['updated_at'] = gmdate('c');

    if ($state['offset'] < $total) {
        update_option(NW_FUEL_INVENTORY_SYNC_STATE, $state, false);
        $progress = [
            'ok'      => true,
            'running' => true,
            'message' => sprintf('Syncing %d / %d rows…', (int) $state['offset'], $total),
            'source'  => (string) ($state['source'] ?? ''),
            'csv_path'=> $path,
            'rows'    => $total,
            'created' => (int) $state['created'],
            'updated' => (int) $state['updated'],
            'skipped' => (int) $state['skipped'],
            'removed' => (int) $state['removed'],
            'errors'  => (int) $state['errors'],
            'started' => (string) ($state['started'] ?? ''),
            'finished'=> '',
            'log'     => $log,
        ];
        update_option('nw_fuel_inventory_sync_last', $progress, false);
        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
        }
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        delete_option($lock_key);
        return $progress;
    }

    $removed = 0;
    if (! empty($state['replace']) && (int) $state['errors'] === 0 && $keep_parts !== []) {
        $removed = nw_fuel_delete_products_not_in_part_list($keep_parts);
    }
    $state['removed'] = $removed;

    delete_option($lock_key);
    return nw_fuel_finalize_inventory_sync($state, true, sprintf('Synced %d rows from CSV.', $total));
}

/**
 * Mark a sync finished and store the last-run summary.
 *
 * @param array<string, mixed> $state
 * @return array<string, mixed>
 */
function nw_fuel_finalize_inventory_sync(array $state, bool $ok, string $message): array
{
    delete_option('nw_fuel_inventory_csv_pending_sync');
    delete_option(NW_FUEL_INVENTORY_SYNC_STATE);

    $result = [
        'ok'        => $ok,
        'running'   => false,
        'message'   => $message,
        'source'    => (string) ($state['source'] ?? ''),
        'csv_path'  => (string) ($state['csv_path'] ?? ''),
        'rows'      => (int) ($state['total'] ?? 0),
        'created'   => (int) ($state['created'] ?? 0),
        'updated'   => (int) ($state['updated'] ?? 0),
        'skipped'   => (int) ($state['skipped'] ?? 0),
        'removed'   => (int) ($state['removed'] ?? 0),
        'errors'    => (int) ($state['errors'] ?? 0),
        'log'       => is_array($state['log'] ?? null) ? $state['log'] : [],
        'started'   => (string) ($state['started'] ?? ''),
        'finished'  => gmdate('c'),
    ];

    nw_fuel_ensure_inventory_sync_scheduled();
    $next = wp_next_scheduled(NW_FUEL_INVENTORY_SYNC_HOOK);
    $result['next_cron'] = $next ? gmdate('c', (int) $next) : '';
    $result['next_cron_pacific'] = $next
        ? (new DateTimeImmutable('@' . $next))->setTimezone(new DateTimeZone(NW_FUEL_INVENTORY_SYNC_TZ))->format('Y-m-d H:i:s T')
        : '';

    update_option('nw_fuel_inventory_sync_last', $result, false);
    do_action('nw_fuel_inventory_sync_complete', $result);

    return $result;
}

/**
 * Parse inventory CSV into associative rows (first occurrence of duplicate headers wins).
 *
 * @return array{headers: list<string>, rows: list<array<string, string>>}|WP_Error
 */
function nw_fuel_parse_inventory_csv_file(string $path): array|WP_Error
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return new WP_Error('nw_fuel_csv_open', __('Unable to open latest.csv', 'nw-fuel'));
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers_raw = fgetcsv($handle);
    if (! is_array($headers_raw) || $headers_raw === []) {
        fclose($handle);
        return new WP_Error('nw_fuel_csv_headers', __('CSV header row missing', 'nw-fuel'));
    }

    $headers = function_exists('nw_fuel_inventory_map_header_row')
        ? nw_fuel_inventory_map_header_row($headers_raw)
        : [];
    if ($headers === []) {
        $seen = [];
        foreach ($headers_raw as $i => $h) {
            $name = trim((string) $h);
            if ($name === '' || isset($seen[$name])) {
                $headers[$i] = '';
                continue;
            }
            $seen[$name] = true;
            $headers[$i] = $name;
        }
    }

    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        if (count($data) === 1 && trim((string) $data[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $name) {
            if ($name === '') {
                continue;
            }
            $row[$name] = isset($data[$i]) ? trim((string) $data[$i]) : '';
        }
        if (($row['Part_Number'] ?? '') === '') {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    return [
        'headers' => array_values(array_filter($headers)),
        'rows'    => $rows,
    ];
}

/**
 * Stock qty from AFS. Negative values become 0 / out of stock.
 *
 * @param array<string, string> $row
 */
function nw_fuel_inventory_row_stock_qty(array $row): int
{
    $stock_raw = (float) ($row['AvailableForSale'] ?? 0);

    return (int) max(0, floor($stock_raw));
}

/**
 * Apply price + quantity fields from a CSV row.
 *
 * @param array<string, string> $row
 */
function nw_fuel_apply_inventory_price_and_qty(WC_Product $product, array $row, bool $only_present): void
{
    $product_id = $product->get_id();
    $stock_qty  = nw_fuel_inventory_row_stock_qty($row);

    $product->set_manage_stock(true);
    $product->set_stock_quantity($stock_qty);
    $product->set_stock_status($stock_qty > 0 ? 'instock' : 'outofstock');
    $product->set_regular_price('');
    $product->set_price('');
    $product->save();

    $price_map = [
        'ListTotal'      => '_nw_retail',
        'Wholesale'      => '_nw_wholesale',
        'TradeTotal'     => '_nw_trade_total',
        'Special1Total'  => '_nw_special1_total',
        'Special2Total'  => '_nw_special2_total',
        'Special3Total'  => '_nw_special3_total',
        'CostPCore'      => '_nw_cost_p_core',
    ];

    foreach ($price_map as $column => $meta_key) {
        if ($only_present && ! array_key_exists($column, $row)) {
            continue;
        }
        update_post_meta($product_id, $meta_key, nw_fuel_inventory_parse_money((string) ($row[$column] ?? '')));
    }
}

/**
 * Create or update one WooCommerce product from a CSV row.
 *
 * Nightly API updates of existing products change name/title, both short
 * descriptions, category (when present), prices, quantity, alternates, and
 * brand. The main Product Description (post_content) is preserved.
 *
 * @param array<string, string> $row
 * @return 'created'|'updated'|'skipped'
 */
function nw_fuel_upsert_product_from_inventory_row(array $row, bool $prices_only = false): string
{
    $part = sanitize_text_field($row['Part_Number'] ?? '');
    if ($part === '') {
        return 'skipped';
    }

    $on_website = function_exists('nw_fuel_inventory_row_on_website')
        ? nw_fuel_inventory_row_on_website($row)
        : null;

    $file_title  = sanitize_text_field($row['Title'] ?? '');
    $file_desc   = sanitize_textarea_field($row['Description'] ?? '');
    $title       = $file_title !== '' ? $file_title : ($file_desc !== '' ? sanitize_text_field($file_desc) : $part);
    $description = $file_desc !== '' ? $file_desc : $title;

    $product_id = nw_fuel_find_product_id_by_part_number($part);
    $is_new     = false;

    $post_fields = [
        'post_title'   => $title,
        'post_excerpt' => $description,
        'post_content' => $description,
    ];

    if ($product_id <= 0) {
        if ($on_website === false) {
            return 'skipped';
        }
        $is_new = true;
        $product_id = wp_insert_post(array_merge($post_fields, [
            'post_name'   => sanitize_title($part),
            'post_status' => 'publish',
            'post_type'   => 'product',
        ]), true);

        if (is_wp_error($product_id)) {
            throw new RuntimeException($product_id->get_error_message());
        }
        $product_id = (int) $product_id;
    } elseif ($file_title !== '' || $file_desc !== '') {
        $update_fields = $post_fields;
        if ($prices_only) {
            unset($update_fields['post_content']);
        }
        wp_update_post(array_merge(['ID' => $product_id], $update_fields));
    }

    $product = wc_get_product($product_id);
    if (! $product) {
        throw new RuntimeException('wc_get_product failed for ID ' . $product_id);
    }

    if ($is_new || ! $prices_only) {
        try {
            $product->set_sku($part);
        } catch (Exception $e) {
            // SKU conflict on another product: keep existing SKU and match by meta.
        }
        $product->set_status('publish');
    }

    if ($on_website === false) {
        $product->set_catalog_visibility('hidden');
    } elseif ($on_website === true || $is_new || ! $prices_only) {
        $product->set_catalog_visibility('visible');
    }

    nw_fuel_apply_inventory_price_and_qty($product, $row, $prices_only && ! $is_new);

    update_post_meta($product_id, '_nw_part_number', $part);
    if ($prices_only) {
        update_post_meta($product_id, '_nw_inventory_managed', 'yes');
    }
    if ($on_website !== null) {
        update_post_meta($product_id, '_nw_website', $on_website ? 'yes' : 'no');
    }

    if (array_key_exists('PriceBook', $row)) {
        $price_book = sanitize_text_field($row['PriceBook'] ?? '');
        if ($price_book !== '') {
            nw_fuel_assign_inventory_brand($product_id, $price_book);
        }
    }

    if (array_key_exists('Category', $row)) {
        $category = sanitize_text_field($row['Category'] ?? '');
        if ($category !== '') {
            nw_fuel_assign_inventory_category($product_id, $category);
        }
    }

    if (array_key_exists('SecondaryIDList', $row)) {
        $secondary = nw_fuel_inventory_parse_secondary_ids($row['SecondaryIDList'] ?? '');
        update_post_meta($product_id, '_nw_secondary_ids', nw_fuel_json_encode_meta($secondary));
        update_post_meta($product_id, '_nw_search_alts', implode(' ', $secondary));
    }

    if ($is_new || ! $prices_only) {
        update_post_meta($product_id, '_nw_oem_vendor', sanitize_text_field($row['OEMVendorNumber'] ?? ''));
    }
    if ($file_title !== '' || $file_desc !== '') {
        update_post_meta($product_id, '_nw_short_description', $description);
    }

    return $is_new ? 'created' : 'updated';
}

/**
 * Category column → WooCommerce product_cat. Creates the term if it does not exist.
 * Matches existing categories case-insensitively. Comma/semicolon/pipe lists are allowed.
 */
function nw_fuel_assign_inventory_category(int $product_id, string $category): void
{
    static $term_by_lower = null;

    $category = sanitize_text_field($category);
    if ($category === '' || ! taxonomy_exists('product_cat')) {
        return;
    }

    if ($term_by_lower === null) {
        $term_by_lower = [];
        $all = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        if (is_array($all)) {
            foreach ($all as $term) {
                if ($term instanceof WP_Term) {
                    $term_by_lower[strtolower($term->name)] = (int) $term->term_id;
                }
            }
        }
    }

    $names = preg_split('/\s*[;,|]\s*/', $category) ?: [];
    $term_ids = [];
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '' || strcasecmp($name, 'uncategorized') === 0) {
            continue;
        }

        $key = strtolower($name);
        if (isset($term_by_lower[$key])) {
            $term_ids[] = $term_by_lower[$key];
            continue;
        }

        $inserted = wp_insert_term($name, 'product_cat');
        if (! is_wp_error($inserted) && isset($inserted['term_id'])) {
            $id = (int) $inserted['term_id'];
            $term_by_lower[$key] = $id;
            $term_ids[] = $id;
        }
    }

    if ($term_ids !== []) {
        wp_set_object_terms($product_id, array_values(array_unique($term_ids)), 'product_cat', false);
    }
}

/**
 * PriceBook column → Brand attribute (pa_brand) + _nw_price_book meta.
 */
function nw_fuel_assign_inventory_brand(int $product_id, string $brand): void
{
    $brand = sanitize_text_field($brand);
    update_post_meta($product_id, '_nw_price_book', $brand);
    if (! taxonomy_exists('pa_brand')) {
        return;
    }
    if ($brand === '') {
        wp_set_object_terms($product_id, [], 'pa_brand', false);
        return;
    }

    if (! term_exists($brand, 'pa_brand')) {
        wp_insert_term($brand, 'pa_brand');
    }
    wp_set_object_terms($product_id, [$brand], 'pa_brand', false);

    $attributes = get_post_meta($product_id, '_product_attributes', true);
    if (! is_array($attributes)) {
        $attributes = [];
    }
    $attributes['pa_brand'] = [
        'name'         => 'pa_brand',
        'value'        => '',
        'position'     => 0,
        'is_visible'   => 1,
        'is_variation' => 0,
        'is_taxonomy'  => 1,
    ];
    update_post_meta($product_id, '_product_attributes', $attributes);
}

/**
 * Find product by SKU or _nw_part_number meta.
 */
function nw_fuel_find_product_id_by_part_number(string $part): int
{
    if (function_exists('wc_get_product_id_by_sku')) {
        $by_sku = (int) wc_get_product_id_by_sku($part);
        if ($by_sku > 0) {
            return $by_sku;
        }
    }

    $q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_nw_part_number',
                'value' => $part,
            ],
        ],
        'no_found_rows'  => true,
    ]);

    if ($q->posts !== []) {
        return (int) $q->posts[0];
    }

    return 0;
}

/**
 * Normalize money string to WooCommerce price string.
 */
function nw_fuel_inventory_parse_money(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = str_replace([',', '$'], '', $value);
    if (! is_numeric($value)) {
        return '';
    }

    return number_format((float) $value, 2, '.', '');
}

/**
 * @return list<string>
 */
function nw_fuel_inventory_parse_secondary_ids(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $raw = (string) preg_replace('/note\s+for\b.*/i', '', $raw);
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $out   = [];
    foreach ($parts as $p) {
        $p = sanitize_text_field($p);
        if ($p === '' || strlen($p) > 40) {
            continue;
        }
        if (preg_match('/^etc\.?$/i', $p)) {
            continue;
        }
        $out[] = $p;
    }

    return array_values(array_unique($out));
}

/**
 * Permanently delete WooCommerce products whose part number is not in $keep_parts.
 *
 * @param list<string> $keep_parts
 */
function nw_fuel_delete_products_not_in_part_list(array $keep_parts): int
{
    $keep = [];
    foreach ($keep_parts as $part) {
        $key = strtolower(trim((string) $part));
        if ($key !== '') {
            $keep[$key] = true;
        }
    }
    if ($keep === []) {
        return 0;
    }

    $ids = wc_get_products([
        'status' => ['publish', 'draft', 'private', 'pending'],
        'limit'  => -1,
        'return' => 'ids',
    ]);
    if (! is_array($ids)) {
        return 0;
    }

    $removed = 0;
    foreach ($ids as $id) {
        $id      = (int) $id;
        $product = wc_get_product($id);
        if (! $product) {
            continue;
        }
        $part = strtolower(nw_fuel_get_part_number($id));
        $sku  = strtolower((string) $product->get_sku());
        if (($part !== '' && isset($keep[$part])) || ($sku !== '' && isset($keep[$sku]))) {
            continue;
        }
        wp_delete_post($id, true);
        ++$removed;
    }

    return $removed;
}

/**
 * Render sync controls on the Inventory CSV API admin page.
 */
function nw_fuel_render_inventory_sync_section(): void
{
    $last   = get_option('nw_fuel_inventory_sync_last', []);
    if (! is_array($last)) {
        $last = [];
    }
    $flash  = get_transient('nw_fuel_inventory_sync_flash');
    if (is_array($flash)) {
        delete_transient('nw_fuel_inventory_sync_flash');
        $last = $flash;
    }

    $next_ts = wp_next_scheduled(NW_FUEL_INVENTORY_SYNC_HOOK);
    $next_pacific = '';
    if ($next_ts) {
        $next_pacific = (new DateTimeImmutable('@' . $next_ts))
            ->setTimezone(new DateTimeZone(NW_FUEL_INVENTORY_SYNC_TZ))
            ->format('Y-m-d H:i:s T');
    }

    $csv_meta = function_exists('nw_fuel_get_inventory_csv_meta') ? nw_fuel_get_inventory_csv_meta() : [];
    $nightly  = function_exists('nw_fuel_inventory_is_nightly_mode') ? nw_fuel_inventory_is_nightly_mode() : true;
    $running  = nw_fuel_inventory_sync_is_active();
    $state    = nw_fuel_inventory_sync_state();
    if ($running) {
        echo '<script>window.setTimeout(function () { window.location.reload(); }, 8000);</script>';
    }
    ?>
    <hr>
    <h2><?php esc_html_e('Product sync', 'nw-fuel'); ?></h2>
    <?php if ($nightly) : ?>
    <p><?php esc_html_e('Applies latest.csv every night at 1:00 AM America/Los_Angeles, in batches of 80. Column P = Yes is required to add or show a part. Existing Yes products: name/title, short descriptions, category (when a Category column is present), prices, quantity, search alternates, and brand (PriceBook). The main Product Description, photos, and other admin copy stay as-is. Column P = No hides the part. Missing rows are not deleted.', 'nw-fuel'); ?></p>
    <?php else : ?>
    <p><?php esc_html_e('Nightly API sync is off. Upload an Excel or CSV file above to apply it immediately. Match key: Part_Number → SKU / part number. Missing rows are left unchanged (not deleted).', 'nw-fuel'); ?></p>
    <?php endif; ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Next scheduled run', 'nw-fuel'); ?></th>
            <td>
                <?php if (! $nightly) : ?>
                    <?php esc_html_e('Disabled — manual upload mode.', 'nw-fuel'); ?>
                <?php elseif ($next_pacific !== '') : ?>
                    <code><?php echo esc_html($next_pacific); ?></code>
                    <span class="description">(<?php echo esc_html(gmdate('c', (int) $next_ts)); ?> UTC)</span>
                <?php else : ?>
                    <?php esc_html_e('Not scheduled — will schedule on next page load.', 'nw-fuel'); ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Latest CSV', 'nw-fuel'); ?></th>
            <td>
                <?php if (! empty($csv_meta['exists'])) : ?>
                    <?php esc_html_e('Ready', 'nw-fuel'); ?> —
                    <?php echo esc_html((string) ($csv_meta['bytes'] ?? 0)); ?> bytes
                <?php else : ?>
                    <?php esc_html_e('None uploaded yet', 'nw-fuel'); ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($running && $state !== []) : ?>
        <tr>
            <th scope="row"><?php esc_html_e('Sync progress', 'nw-fuel'); ?></th>
            <td>
                <span style="color:#dba617;"><?php esc_html_e('Running', 'nw-fuel'); ?></span>
                — <?php echo esc_html(sprintf('%d / %d rows', (int) ($state['offset'] ?? 0), (int) ($state['total'] ?? 0))); ?><br>
                <?php
                printf(
                    /* translators: 1: created 2: updated 3: skipped 4: errors */
                    esc_html__('Created %1$d · Updated %2$d · Skipped %3$d · Errors %4$d', 'nw-fuel'),
                    (int) ($state['created'] ?? 0),
                    (int) ($state['updated'] ?? 0),
                    (int) ($state['skipped'] ?? 0),
                    (int) ($state['errors'] ?? 0)
                );
                ?>
                <p class="description"><?php esc_html_e('This page refreshes every 8 seconds until the file is finished.', 'nw-fuel'); ?></p>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($last !== []) : ?>
        <tr>
            <th scope="row"><?php esc_html_e('Last sync', 'nw-fuel'); ?></th>
            <td>
                <?php if (! empty($last['running'])) : ?>
                    <span style="color:#dba617;"><?php esc_html_e('Running', 'nw-fuel'); ?></span>
                <?php elseif (! empty($last['ok'])) : ?>
                    <span style="color:#00a32a;"><?php esc_html_e('OK', 'nw-fuel'); ?></span>
                <?php else : ?>
                    <span style="color:#d63638;"><?php esc_html_e('Failed', 'nw-fuel'); ?></span>
                <?php endif; ?>
                — <?php echo esc_html((string) ($last['message'] ?? '')); ?><br>
                <?php if (! empty($last['ok'])) : ?>
                    <?php
                    printf(
                        /* translators: 1: created 2: updated 3: skipped 4: removed 5: errors */
                        esc_html__('Created %1$d · Updated %2$d · Skipped %3$d · Removed %4$d · Errors %5$d', 'nw-fuel'),
                        (int) ($last['created'] ?? 0),
                        (int) ($last['updated'] ?? 0),
                        (int) ($last['skipped'] ?? 0),
                        (int) ($last['removed'] ?? 0),
                        (int) ($last['errors'] ?? 0)
                    );
                    ?>
                    <br>
                <?php endif; ?>
                <span class="description">
                    <?php echo esc_html((string) ($last['finished'] ?? $last['started'] ?? '')); ?>
                    · source=<?php echo esc_html((string) ($last['source'] ?? '')); ?>
                </span>
                <?php if (! empty($last['log']) && is_array($last['log'])) : ?>
                    <ul>
                        <?php foreach ($last['log'] as $line) : ?>
                            <li><?php echo esc_html((string) $line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('nw_fuel_run_inventory_sync', 'nw_fuel_inventory_sync_nonce'); ?>
        <input type="hidden" name="action" value="nw_fuel_run_inventory_sync">
        <?php
        submit_button(
            $running ? __('Sync running…', 'nw-fuel') : __('Run sync now', 'nw-fuel'),
            'primary',
            'submit',
            false,
            $running ? ['disabled' => 'disabled'] : []
        );
        ?>
    </form>
    <p class="description"><?php esc_html_e('CLI: wp nw-fuel sync-inventory', 'nw-fuel'); ?></p>
    <?php
}
