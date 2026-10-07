<?php
/**
 * Inventory CSV upload API + credentials admin.
 *
 * External systems POST a CSV to:
 *   POST /wp-json/nw-fuel/v1/inventory/csv
 * with header: X-NW-Fuel-Key: <api-key>
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_INVENTORY_OPTION_KEY     = 'nw_fuel_inventory_api_key_hash';
const NW_FUEL_INVENTORY_OPTION_HINT    = 'nw_fuel_inventory_api_key_hint';
const NW_FUEL_INVENTORY_OPTION_MODE    = 'nw_fuel_inventory_update_mode';
const NW_FUEL_INVENTORY_MODE_NIGHTLY   = 'nightly';
const NW_FUEL_INVENTORY_MODE_MANUAL    = 'manual';
const NW_FUEL_INVENTORY_UPLOAD_SUBDIR  = 'nw-fuel-inventory';
const NW_FUEL_INVENTORY_LATEST_FILE    = 'latest.csv';
const NW_FUEL_INVENTORY_RATE_PER_MIN   = 10;
const NW_FUEL_INVENTORY_UPLOADS_PER_NIGHT = 3;
const NW_FUEL_INVENTORY_QUOTA_OPTION   = 'nw_fuel_inventory_nightly_upload_quota';

add_action('rest_api_init', 'nw_fuel_register_inventory_rest_routes');
add_action('admin_menu', 'nw_fuel_register_inventory_api_page');
add_action('admin_post_nw_fuel_generate_inventory_api_key', 'nw_fuel_handle_generate_inventory_api_key');
add_action('admin_post_nw_fuel_revoke_inventory_api_key', 'nw_fuel_handle_revoke_inventory_api_key');
add_action('admin_post_nw_fuel_save_inventory_mode', 'nw_fuel_handle_save_inventory_mode');
add_action('admin_post_nw_fuel_upload_inventory_file', 'nw_fuel_handle_upload_inventory_file');

/**
 * Current product-list update source: nightly API or manual Excel/CSV.
 */
function nw_fuel_inventory_update_mode(): string
{
    $mode = (string) get_option(NW_FUEL_INVENTORY_OPTION_MODE, NW_FUEL_INVENTORY_MODE_NIGHTLY);

    return $mode === NW_FUEL_INVENTORY_MODE_MANUAL
        ? NW_FUEL_INVENTORY_MODE_MANUAL
        : NW_FUEL_INVENTORY_MODE_NIGHTLY;
}

function nw_fuel_inventory_is_nightly_mode(): bool
{
    return nw_fuel_inventory_update_mode() === NW_FUEL_INVENTORY_MODE_NIGHTLY;
}

function nw_fuel_inventory_is_manual_mode(): bool
{
    return nw_fuel_inventory_update_mode() === NW_FUEL_INVENTORY_MODE_MANUAL;
}

/**
 * Register REST routes.
 */
function nw_fuel_register_inventory_rest_routes(): void
{
    register_rest_route('nw-fuel/v1', '/inventory/csv', [
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'nw_fuel_rest_upload_inventory_csv',
            'permission_callback' => 'nw_fuel_inventory_api_permission',
            'args'                => [],
        ],
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'nw_fuel_rest_inventory_status',
            'permission_callback' => 'nw_fuel_inventory_api_permission',
        ],
    ]);
}

/**
 * Validate API key from X-NW-Fuel-Key header (or ?api_key= for quick tests).
 *
 * @param WP_REST_Request $request Request.
 */
function nw_fuel_inventory_api_permission(WP_REST_Request $request): bool|WP_Error
{
    $limited = nw_fuel_inventory_rate_limit_or_error();
    if (is_wp_error($limited)) {
        return $limited;
    }

    $provided = (string) $request->get_header('x_nw_fuel_key');
    if ($provided === '') {
        $provided = (string) $request->get_param('api_key');
    }
    $provided = trim($provided);

    if ($provided === '') {
        return new WP_Error(
            'nw_fuel_missing_api_key',
            __('Missing API key. Send header X-NW-Fuel-Key.', 'nw-fuel'),
            ['status' => 401]
        );
    }

    $stored_hash = (string) get_option(NW_FUEL_INVENTORY_OPTION_KEY, '');
    if ($stored_hash === '') {
        return new WP_Error(
            'nw_fuel_api_key_not_configured',
            __('Inventory API key is not configured. Generate one in Tools → Inventory updates.', 'nw-fuel'),
            ['status' => 503]
        );
    }

    if (! hash_equals($stored_hash, hash('sha256', $provided))) {
        return new WP_Error(
            'nw_fuel_invalid_api_key',
            __('Invalid API key.', 'nw-fuel'),
            ['status' => 403]
        );
    }

    return true;
}

/**
 * Client IP for inventory API rate limits. Uses REMOTE_ADDR only (not spoofable headers).
 */
function nw_fuel_inventory_api_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim($ip);

    return $ip !== '' ? $ip : '0.0.0.0';
}

/**
 * Max 10 inventory API calls per IP per minute (including bad keys).
 */
function nw_fuel_inventory_rate_limit_or_error(): true|WP_Error
{
    $ip  = nw_fuel_inventory_api_client_ip();
    $key = 'nw_fuel_inv_rl_' . md5($ip);
    $max = NW_FUEL_INVENTORY_RATE_PER_MIN;
    $n   = (int) get_transient($key);

    if ($n >= $max) {
        return new WP_Error(
            'nw_fuel_rate_limited',
            sprintf(
                /* translators: %d: max calls per minute */
                __('Too many inventory API requests. Maximum %d calls per minute.', 'nw-fuel'),
                $max
            ),
            ['status' => 429]
        );
    }

    set_transient($key, $n + 1, MINUTE_IN_SECONDS);

    return true;
}

/**
 * Pacific calendar date for nightly upload quota.
 */
function nw_fuel_inventory_pacific_date(): string
{
    $tz = defined('NW_FUEL_INVENTORY_SYNC_TZ') ? NW_FUEL_INVENTORY_SYNC_TZ : 'America/Los_Angeles';

    return (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('Y-m-d');
}

/**
 * @return array{date: string, count: int}
 */
function nw_fuel_inventory_nightly_upload_quota_state(): array
{
    $today = nw_fuel_inventory_pacific_date();
    $raw   = get_option(NW_FUEL_INVENTORY_QUOTA_OPTION, []);
    if (! is_array($raw) || (string) ($raw['date'] ?? '') !== $today) {
        return ['date' => $today, 'count' => 0];
    }

    return [
        'date'  => $today,
        'count' => max(0, (int) ($raw['count'] ?? 0)),
    ];
}

/**
 * Max 3 successful CSV uploads per Pacific night.
 */
function nw_fuel_inventory_nightly_upload_quota_or_error(): true|WP_Error
{
    $state = nw_fuel_inventory_nightly_upload_quota_state();
    $max   = NW_FUEL_INVENTORY_UPLOADS_PER_NIGHT;

    if ($state['count'] >= $max) {
        return new WP_Error(
            'nw_fuel_nightly_quota',
            sprintf(
                /* translators: %d: max successful uploads per night */
                __('Nightly upload limit reached. Maximum %d successful CSV uploads per night (America/Los_Angeles).', 'nw-fuel'),
                $max
            ),
            ['status' => 429]
        );
    }

    return true;
}

function nw_fuel_inventory_increment_nightly_upload_quota(): void
{
    $state = nw_fuel_inventory_nightly_upload_quota_state();
    $state['count']++;
    update_option(NW_FUEL_INVENTORY_QUOTA_OPTION, $state, false);
}

/**
 * Upload and store inventory CSV.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function nw_fuel_rest_upload_inventory_csv(WP_REST_Request $request)
{
    if (! nw_fuel_inventory_is_nightly_mode()) {
        return new WP_Error(
            'nw_fuel_inventory_manual_mode',
            __('Inventory is in manual upload mode. Nightly API uploads are disabled.', 'nw-fuel'),
            ['status' => 409]
        );
    }

    $files = $request->get_file_params();
    $raw   = $request->get_body();

    $csv_bytes = '';
    $filename  = 'upload.csv';

    if (! empty($files['file']['tmp_name']) && is_uploaded_file($files['file']['tmp_name'])) {
        $tmp = $files['file']['tmp_name'];
        $csv_bytes = (string) file_get_contents($tmp);
        $filename  = sanitize_file_name((string) ($files['file']['name'] ?? 'upload.csv'));
    } elseif (is_string($raw) && $raw !== '') {
        $csv_bytes = $raw;
        $filename  = 'body.csv';
    } else {
        return new WP_Error(
            'nw_fuel_missing_csv',
            __('Send multipart field "file" or raw CSV body (Content-Type: text/csv).', 'nw-fuel'),
            ['status' => 400]
        );
    }

    $csv_bytes = nw_fuel_normalize_inventory_csv_bytes($csv_bytes);
    $validation = nw_fuel_validate_inventory_csv($csv_bytes);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $quota = nw_fuel_inventory_nightly_upload_quota_or_error();
    if (is_wp_error($quota)) {
        return $quota;
    }

    $saved = nw_fuel_store_inventory_csv($csv_bytes, $filename);
    if (is_wp_error($saved)) {
        return $saved;
    }

    nw_fuel_inventory_increment_nightly_upload_quota();

    /**
     * Fires after a valid inventory CSV is stored.
     *
     * @param array<string, mixed> $saved Save metadata.
     */
    do_action('nw_fuel_inventory_csv_uploaded', $saved);

    return new WP_REST_Response([
        'ok'           => true,
        'message'      => 'CSV stored successfully. Nightly sync will apply it at 1:00 AM America/Los_Angeles.',
        'rows'         => $validation['rows'],
        'headers'      => $validation['headers'],
        'latest_path'  => $saved['latest_path'],
        'backup_path'  => $saved['backup_path'],
        'bytes'        => $saved['bytes'],
        'received_at'  => $saved['received_at'],
        'source_name'  => $filename,
    ], 200);
}

/**
 * Status of latest stored CSV.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function nw_fuel_rest_inventory_status(WP_REST_Request $request): WP_REST_Response
{
    unset($request);
    $meta = nw_fuel_get_inventory_csv_meta();

    return new WP_REST_Response([
        'ok'     => true,
        'mode'   => nw_fuel_inventory_update_mode(),
        'has_csv'=> ! empty($meta['exists']),
        'meta'   => $meta,
    ], 200);
}

/**
 * Strip BOM / normalize newlines.
 */
function nw_fuel_normalize_inventory_csv_bytes(string $bytes): string
{
    if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
        $bytes = substr($bytes, 3);
    }

    return str_replace(["\r\n", "\r"], "\n", $bytes);
}

/**
 * Map a spreadsheet header to the internal inventory field name.
 */
function nw_fuel_inventory_canonical_header(string $name): string
{
    $key = strtolower(trim($name));
    $key = (string) preg_replace('/[\s_\-#]+/', '', $key);
    $map = [
        'item'                => 'Part_Number',
        'itemnumber'          => 'Part_Number',
        'partnumber'          => 'Part_Number',
        'title'               => 'Title',
        'producttitle'        => 'Title',
        'productname'         => 'Title',
        'itemname'            => 'Title',
        'webname'             => 'Title',
        'webtitle'            => 'Title',
        'description'         => 'Description',
        'category'            => 'Category',
        'categories'          => 'Category',
        'productcategory'     => 'Category',
        'productcat'          => 'Category',
        'itemcategory'        => 'Category',
        'webcategory'         => 'Category',
        'woocommercecategory' => 'Category',
        'afs'                 => 'AvailableForSale',
        'availableforsale'    => 'AvailableForSale',
        'retail'              => 'ListTotal',
        'retailprice'         => 'ListTotal',
        'listtotal'           => 'ListTotal',
        'wholesale'           => 'Wholesale',
        'core'                => 'CostPCore',
        'corecharge'          => 'CostPCore',
        'coresurcharge'       => 'CostPCore',
        'costpcore'           => 'CostPCore',
        'alternatesforsearch' => 'SecondaryIDList',
        'alternatepartnumbers'=> 'SecondaryIDList',
        'alternates'          => 'SecondaryIDList',
        'secondaryidlist'     => 'SecondaryIDList',
        'pricebook'           => 'PriceBook',
        'oemvendornumber'     => 'OEMVendorNumber',
        'tradetotal'          => 'TradeTotal',
        'discountlevel1'      => 'TradeTotal',
        'level1'              => 'TradeTotal',
        'special1total'       => 'Special1Total',
        'discountlevel2'      => 'Special1Total',
        'level2'              => 'Special1Total',
        'special2total'       => 'Special2Total',
        'discountlevel3'      => 'Special2Total',
        'level3'              => 'Special2Total',
        'special3total'       => 'Special3Total',
        'discountlevel4'      => 'Special3Total',
        'level4'              => 'Special3Total',
        'website'             => 'Website',
        'onwebsite'           => 'Website',
        'showonwebsite'       => 'Website',
        'includeonwebsite'    => 'Website',
        'web'                 => 'Website',
        'weblisting'          => 'Website',
        'yesno'               => 'Website',
        'columnp'             => 'Website',
    ];

    if ($key === '' || $key === 'notefor') {
        return '';
    }

    return $map[$key] ?? trim($name);
}

/**
 * Canonical header names keyed by CSV column index.
 *
 * @param list<string> $headers_raw
 * @return array<int, string>
 */
function nw_fuel_inventory_map_header_row(array $headers_raw): array
{
    $headers = [];
    $seen    = [];
    foreach ($headers_raw as $i => $h) {
        $name = nw_fuel_inventory_canonical_header((string) $h);
        if ($name === '' || isset($seen[$name])) {
            $headers[$i] = '';
            continue;
        }
        $seen[$name] = true;
        $headers[$i] = $name;
    }

    // Column P is Yes/No for the website. If unnamed, the 16th column is used.
    if (! in_array('Website', $headers, true) && count($headers_raw) >= 16) {
        $headers[15] = 'Website';
    }

    return $headers;
}

/**
 * Whether this row should appear on the website. Null = column omitted (treat as yes).
 *
 * @param array<string, string> $row
 */
function nw_fuel_inventory_row_on_website(array $row): ?bool
{
    if (! array_key_exists('Website', $row)) {
        return null;
    }

    $value = strtolower(trim((string) $row['Website']));
    $value = (string) preg_replace('/\s+/', '', $value);
    if ($value === '') {
        return false;
    }

    return in_array($value, ['yes', 'y', '1', 'true', 'on'], true);
}

/**
 * Validate CSV has required headers and at least one data row.
 *
 * @return array{headers: list<string>, rows: int}|WP_Error
 */
function nw_fuel_validate_inventory_csv(string $bytes): array|WP_Error
{
    if (trim($bytes) === '') {
        return new WP_Error('nw_fuel_empty_csv', __('CSV is empty.', 'nw-fuel'), ['status' => 400]);
    }

    if (strlen($bytes) > 50 * 1024 * 1024) {
        return new WP_Error('nw_fuel_csv_too_large', __('CSV exceeds 50MB limit.', 'nw-fuel'), ['status' => 413]);
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return new WP_Error('nw_fuel_csv_io', __('Unable to read CSV.', 'nw-fuel'), ['status' => 500]);
    }
    fwrite($stream, $bytes);
    rewind($stream);

    $headers = fgetcsv($stream);
    if (! is_array($headers) || $headers === []) {
        fclose($stream);
        return new WP_Error('nw_fuel_csv_headers', __('CSV header row is missing.', 'nw-fuel'), ['status' => 400]);
    }

    $headers  = function_exists('nw_fuel_inventory_map_header_row')
        ? nw_fuel_inventory_map_header_row($headers)
        : array_map('nw_fuel_inventory_canonical_header', array_map(static fn($h) => trim((string) $h), $headers));
    $required = ['Part_Number', 'Description', 'AvailableForSale'];
    $missing  = array_values(array_diff($required, $headers));
    if ($missing !== []) {
        fclose($stream);
        return new WP_Error(
            'nw_fuel_csv_missing_columns',
            sprintf(
                /* translators: %s: comma-separated column names */
                __('CSV missing required columns: %s', 'nw-fuel'),
                implode(', ', $missing)
            ),
            ['status' => 400, 'missing' => $missing, 'headers' => $headers]
        );
    }

    $rows = 0;
    while (($row = fgetcsv($stream)) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }
        if (count($row) === 1 && trim((string) $row[0]) === '') {
            continue;
        }
        ++$rows;
    }
    fclose($stream);

    if ($rows < 1) {
        return new WP_Error('nw_fuel_csv_no_rows', __('CSV has no data rows.', 'nw-fuel'), ['status' => 400]);
    }

    return [
        'headers' => $headers,
        'rows'    => $rows,
    ];
}

/**
 * Absolute directory for inventory CSV files.
 */
function nw_fuel_inventory_upload_dir(): string|WP_Error
{
    $uploads = wp_upload_dir();
    if (! empty($uploads['error'])) {
        return new WP_Error('nw_fuel_upload_dir', (string) $uploads['error'], ['status' => 500]);
    }

    $dir = trailingslashit($uploads['basedir']) . NW_FUEL_INVENTORY_UPLOAD_SUBDIR;
    if (! wp_mkdir_p($dir)) {
        return new WP_Error('nw_fuel_upload_mkdir', __('Could not create inventory upload directory.', 'nw-fuel'), ['status' => 500]);
    }

    // Deny direct web access to CSV files.
    $htaccess = $dir . '/.htaccess';
    if (! file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    $index = $dir . '/index.php';
    if (! file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }

    return $dir;
}

/**
 * Store CSV as latest.csv + timestamped backup.
 *
 * @return array<string, mixed>|WP_Error
 */
function nw_fuel_store_inventory_csv(string $bytes, string $source_name): array|WP_Error
{
    $dir = nw_fuel_inventory_upload_dir();
    if (is_wp_error($dir)) {
        return $dir;
    }

    $stamp       = gmdate('Ymd-His');
    $safe_source = sanitize_file_name($source_name);
    $backup_name = 'inventory-' . $stamp . '-' . $safe_source;
    if (! str_ends_with(strtolower($backup_name), '.csv')) {
        $backup_name .= '.csv';
    }

    $latest_path = trailingslashit($dir) . NW_FUEL_INVENTORY_LATEST_FILE;
    $backup_path = trailingslashit($dir) . $backup_name;

    if (file_put_contents($backup_path, $bytes) === false) {
        return new WP_Error('nw_fuel_csv_write_backup', __('Failed to write backup CSV.', 'nw-fuel'), ['status' => 500]);
    }
    if (file_put_contents($latest_path, $bytes) === false) {
        return new WP_Error('nw_fuel_csv_write_latest', __('Failed to write latest.csv.', 'nw-fuel'), ['status' => 500]);
    }

    $meta = [
        'exists'       => true,
        'latest_path'  => $latest_path,
        'backup_path'  => $backup_path,
        'bytes'        => strlen($bytes),
        'received_at'  => gmdate('c'),
        'source_name'  => $source_name,
        'timezone_note'=> 'Timestamps are UTC. Nightly sync runs 1:00 AM America/Los_Angeles.',
    ];
    update_option('nw_fuel_inventory_csv_meta', $meta, false);

    return $meta;
}

/**
 * @return array<string, mixed>
 */
function nw_fuel_get_inventory_csv_meta(): array
{
    $meta = get_option('nw_fuel_inventory_csv_meta', []);
    if (! is_array($meta)) {
        $meta = [];
    }

    $dir = nw_fuel_inventory_upload_dir();
    $latest = is_wp_error($dir) ? '' : trailingslashit($dir) . NW_FUEL_INVENTORY_LATEST_FILE;
    $meta['exists'] = $latest !== '' && file_exists($latest);
    $meta['latest_path'] = $latest;
    if ($meta['exists']) {
        $meta['bytes'] = (int) filesize($latest);
        $meta['mtime'] = gmdate('c', (int) filemtime($latest));
    }

    return $meta;
}

/**
 * Path to latest.csv or empty string.
 */
function nw_fuel_inventory_latest_csv_path(): string
{
    $dir = nw_fuel_inventory_upload_dir();
    if (is_wp_error($dir)) {
        return '';
    }
    $path = trailingslashit($dir) . NW_FUEL_INVENTORY_LATEST_FILE;

    return file_exists($path) ? $path : '';
}

/**
 * Admin: Tools → Inventory CSV API.
 */
function nw_fuel_register_inventory_api_page(): void
{
    add_management_page(
        __('Inventory updates', 'nw-fuel'),
        __('Inventory updates', 'nw-fuel'),
        'manage_options',
        'nw-fuel-inventory-api',
        'nw_fuel_render_inventory_api_page'
    );
}

/**
 * Render credentials + endpoint docs.
 */
function nw_fuel_render_inventory_api_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $has_key   = (string) get_option(NW_FUEL_INVENTORY_OPTION_KEY, '') !== '';
    $hint      = (string) get_option(NW_FUEL_INVENTORY_OPTION_HINT, '');
    $plain_key = get_transient('nw_fuel_inventory_api_key_plain');
    if (is_string($plain_key) && $plain_key !== '') {
        delete_transient('nw_fuel_inventory_api_key_plain');
    } else {
        $plain_key = '';
    }

    $endpoint = rest_url('nw-fuel/v1/inventory/csv');
    $meta     = nw_fuel_get_inventory_csv_meta();
    $mode     = nw_fuel_inventory_update_mode();
    $flash    = get_transient('nw_fuel_inventory_admin_flash');
    if (is_array($flash)) {
        delete_transient('nw_fuel_inventory_admin_flash');
    } else {
        $flash = [];
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Inventory updates', 'nw-fuel'); ?></h1>
        <p><?php esc_html_e('Choose how WooCommerce products are updated: nightly CSV from the inventory API, or a manual Excel/CSV upload.', 'nw-fuel'); ?></p>

        <?php if ($flash !== []) : ?>
            <div class="notice notice-<?php echo ! empty($flash['ok']) ? 'success' : 'error'; ?> is-dismissible">
                <p><?php echo esc_html((string) ($flash['message'] ?? '')); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e('Update source', 'nw-fuel'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('nw_fuel_save_inventory_mode', 'nw_fuel_inventory_mode_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_save_inventory_mode">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Product list source', 'nw-fuel'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="radio" name="nw_fuel_inventory_mode" value="<?php echo esc_attr(NW_FUEL_INVENTORY_MODE_NIGHTLY); ?>" <?php checked($mode, NW_FUEL_INVENTORY_MODE_NIGHTLY); ?>>
                                <strong><?php esc_html_e('Nightly API', 'nw-fuel'); ?></strong>
                                — <?php esc_html_e('External system POSTs a CSV. WordPress applies it at 1:00 AM America/Los_Angeles. Max 10 API calls per minute and 3 successful uploads per night.', 'nw-fuel'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="nw_fuel_inventory_mode" value="<?php echo esc_attr(NW_FUEL_INVENTORY_MODE_MANUAL); ?>" <?php checked($mode, NW_FUEL_INVENTORY_MODE_MANUAL); ?>>
                                <strong><?php esc_html_e('Manual Excel / CSV', 'nw-fuel'); ?></strong>
                                — <?php esc_html_e('Upload a file in admin. It is applied immediately. Nightly API uploads are disabled.', 'nw-fuel'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save source', 'nw-fuel'), 'primary', 'submit', false); ?>
        </form>

        <?php if ($mode === NW_FUEL_INVENTORY_MODE_MANUAL) : ?>
        <h2><?php esc_html_e('Manual file upload', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('Upload .xlsx or .csv. Required: Item# / Part_Number, Description, AFS. Optional: Title, Category, Retail (H), discount levels I–L, Core, Alternates, Website Yes/No (P). Matching part numbers are updated; new part numbers are added. Title and Category columns can be omitted until the provider adds them.', 'nw-fuel'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('nw_fuel_upload_inventory_file', 'nw_fuel_inventory_upload_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_upload_inventory_file">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nw_fuel_inventory_file"><?php esc_html_e('Excel or CSV', 'nw-fuel'); ?></label></th>
                    <td>
                        <input type="file" id="nw_fuel_inventory_file" name="nw_fuel_inventory_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Existing products', 'nw-fuel'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="nw_fuel_inventory_replace" value="1">
                            <?php esc_html_e('Remove products that are not in this file', 'nw-fuel'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Leave unchecked to only add or update by part number. Check only when replacing the whole catalog.', 'nw-fuel'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Upload and apply now', 'nw-fuel'), 'primary', 'submit', false); ?>
        </form>
        <?php endif; ?>

        <h2><?php echo $mode === NW_FUEL_INVENTORY_MODE_MANUAL ? esc_html__('Nightly API (disabled)', 'nw-fuel') : esc_html__('Credentials', 'nw-fuel'); ?></h2>
        <?php if ($mode === NW_FUEL_INVENTORY_MODE_MANUAL) : ?>
            <p class="description"><?php esc_html_e('The API endpoint is turned off while manual upload mode is on. Switch back to Nightly API to accept remote CSV uploads.', 'nw-fuel'); ?></p>
        <?php endif; ?>
        <?php if ($plain_key !== '') : ?>
            <div class="notice notice-success">
                <p><strong><?php esc_html_e('Copy this API key now — it will not be shown again.', 'nw-fuel'); ?></strong></p>
                <p><code style="font-size:14px;word-break:break-all;"><?php echo esc_html($plain_key); ?></code></p>
            </div>
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Status', 'nw-fuel'); ?></th>
                <td>
                    <?php if ($has_key) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
                        <?php esc_html_e('API key configured', 'nw-fuel'); ?>
                        <?php if ($hint !== '') : ?>
                            <code><?php echo esc_html($hint); ?></code>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" style="color:#dba617;"></span>
                        <?php esc_html_e('No API key yet — generate one below.', 'nw-fuel'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Endpoint', 'nw-fuel'); ?></th>
                <td><code><?php echo esc_html($endpoint); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auth header', 'nw-fuel'); ?></th>
                <td><code>X-NW-Fuel-Key: YOUR_API_KEY</code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Method', 'nw-fuel'); ?></th>
                <td><code>POST</code> multipart field <code>file</code>, or raw CSV body</td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Limits', 'nw-fuel'); ?></th>
                <td><?php esc_html_e('10 API calls per IP per minute. 3 successful CSV uploads per night (America/Los_Angeles). Extra calls return HTTP 429.', 'nw-fuel'); ?></td>
            </tr>
        </table>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <?php wp_nonce_field('nw_fuel_generate_inventory_api_key', 'nw_fuel_inventory_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_generate_inventory_api_key">
            <?php
            submit_button(
                $has_key ? __('Regenerate API Key', 'nw-fuel') : __('Generate API Key', 'nw-fuel'),
                $has_key ? 'secondary' : 'primary',
                'submit',
                false
            );
            ?>
        </form>
        <?php if ($has_key) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" onsubmit="return confirm('Revoke the current API key? Uploads will fail until you generate a new one.');">
            <?php wp_nonce_field('nw_fuel_revoke_inventory_api_key', 'nw_fuel_inventory_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_revoke_inventory_api_key">
            <?php submit_button(__('Revoke Key', 'nw-fuel'), 'delete', 'submit', false); ?>
        </form>
        <?php endif; ?>

        <h2><?php esc_html_e('Example (curl)', 'nw-fuel'); ?></h2>
        <pre style="background:#f6f7f7;padding:12px;overflow:auto;">curl -X POST "<?php echo esc_html($endpoint); ?>" \
  -H "X-NW-Fuel-Key: YOUR_API_KEY" \
  -F "file=@/path/to/inventory.csv"</pre>

        <h2><?php esc_html_e('Latest stored CSV', 'nw-fuel'); ?></h2>
        <?php if (! empty($meta['exists'])) : ?>
            <ul>
                <li><?php esc_html_e('Path:', 'nw-fuel'); ?> <code><?php echo esc_html((string) ($meta['latest_path'] ?? '')); ?></code></li>
                <li><?php esc_html_e('Size:', 'nw-fuel'); ?> <?php echo esc_html((string) ($meta['bytes'] ?? 0)); ?> bytes</li>
                <li><?php esc_html_e('Modified (UTC):', 'nw-fuel'); ?> <?php echo esc_html((string) ($meta['mtime'] ?? $meta['received_at'] ?? '')); ?></li>
                <?php if (! empty($meta['source_name'])) : ?>
                <li><?php esc_html_e('Source name:', 'nw-fuel'); ?> <?php echo esc_html((string) $meta['source_name']); ?></li>
                <?php endif; ?>
            </ul>
        <?php else : ?>
            <p><?php esc_html_e('No CSV uploaded yet.', 'nw-fuel'); ?></p>
        <?php endif; ?>

        <?php
        if (function_exists('nw_fuel_render_inventory_sync_section')) {
            nw_fuel_render_inventory_sync_section();
        }
        ?>
    </div>
    <?php
}

/**
 * Generate and store hashed API key; show plaintext once via transient.
 */
function nw_fuel_handle_generate_inventory_api_key(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_inventory_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_nonce'])), 'nw_fuel_generate_inventory_api_key')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $plain = 'nwf_' . bin2hex(random_bytes(24));
    update_option(NW_FUEL_INVENTORY_OPTION_KEY, hash('sha256', $plain), false);
    update_option(NW_FUEL_INVENTORY_OPTION_HINT, substr($plain, 0, 8) . '…' . substr($plain, -4), false);
    set_transient('nw_fuel_inventory_api_key_plain', $plain, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-inventory-api&generated=1'));
    exit;
}

/**
 * Revoke API key.
 */
function nw_fuel_handle_revoke_inventory_api_key(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_inventory_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_nonce'])), 'nw_fuel_revoke_inventory_api_key')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    delete_option(NW_FUEL_INVENTORY_OPTION_KEY);
    delete_option(NW_FUEL_INVENTORY_OPTION_HINT);
    delete_transient('nw_fuel_inventory_api_key_plain');

    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-inventory-api&revoked=1'));
    exit;
}

/**
 * Save nightly vs manual update source and toggle cron.
 */
function nw_fuel_handle_save_inventory_mode(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_inventory_mode_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_mode_nonce'])), 'nw_fuel_save_inventory_mode')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $raw  = isset($_POST['nw_fuel_inventory_mode']) ? sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_mode'])) : '';
    $mode = $raw === NW_FUEL_INVENTORY_MODE_MANUAL
        ? NW_FUEL_INVENTORY_MODE_MANUAL
        : NW_FUEL_INVENTORY_MODE_NIGHTLY;

    update_option(NW_FUEL_INVENTORY_OPTION_MODE, $mode, false);

    if (function_exists('nw_fuel_ensure_inventory_sync_scheduled')) {
        nw_fuel_ensure_inventory_sync_scheduled();
    }

    set_transient('nw_fuel_inventory_admin_flash', [
        'ok'      => true,
        'message' => $mode === NW_FUEL_INVENTORY_MODE_MANUAL
            ? __('Product list source is now Manual Excel / CSV. Nightly API uploads are off.', 'nw-fuel')
            : __('Product list source is now Nightly API. Sync will run at 1:00 AM America/Los_Angeles.', 'nw-fuel'),
    ], 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-inventory-api&mode_saved=1'));
    exit;
}

/**
 * Admin Excel/CSV upload — store as latest.csv and apply immediately.
 */
function nw_fuel_handle_upload_inventory_file(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_inventory_upload_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_inventory_upload_nonce'])), 'nw_fuel_upload_inventory_file')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $redirect = admin_url('tools.php?page=nw-fuel-inventory-api');

    if (! nw_fuel_inventory_is_manual_mode()) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => __('Switch the product list source to Manual Excel / CSV before uploading.', 'nw-fuel'),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    if (empty($_FILES['nw_fuel_inventory_file']['tmp_name'])) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => __('Choose a .xlsx or .csv file to upload.', 'nw-fuel'),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $file = $_FILES['nw_fuel_inventory_file'];
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => __('File upload failed. Try again or use a smaller file.', 'nw-fuel'),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $tmp  = (string) $file['tmp_name'];
    $name = sanitize_file_name((string) ($file['name'] ?? 'upload.csv'));

    $csv_bytes = nw_fuel_inventory_uploaded_file_to_csv($tmp, $name);
    if (is_wp_error($csv_bytes)) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => $csv_bytes->get_error_message(),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $csv_bytes  = nw_fuel_normalize_inventory_csv_bytes($csv_bytes);
    $validation = nw_fuel_validate_inventory_csv($csv_bytes);
    if (is_wp_error($validation)) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => $validation->get_error_message(),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $saved = nw_fuel_store_inventory_csv($csv_bytes, $name);
    if (is_wp_error($saved)) {
        set_transient('nw_fuel_inventory_admin_flash', [
            'ok'      => false,
            'message' => $saved->get_error_message(),
        ], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $replace = ! empty($_POST['nw_fuel_inventory_replace']);
    $result  = function_exists('nw_fuel_run_inventory_sync')
        ? nw_fuel_run_inventory_sync([
            'source'  => 'manual_upload',
            'replace' => $replace,
        ])
        : ['ok' => true, 'message' => 'File stored.'];

    set_transient('nw_fuel_inventory_sync_flash', $result, 5 * MINUTE_IN_SECONDS);
    set_transient('nw_fuel_inventory_admin_flash', [
        'ok'      => ! empty($result['ok']),
        'message' => ! empty($result['ok'])
            ? sprintf(
                /* translators: %d: number of data rows */
                __('File accepted (%d rows). Products are updating in the background.', 'nw-fuel'),
                (int) ($validation['rows'] ?? 0)
            )
            : (string) ($result['message'] ?? __('Upload saved but sync failed.', 'nw-fuel')),
    ], 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect($redirect . '&uploaded=1');
    exit;
}

/**
 * Convert an uploaded .xlsx or .csv path into CSV bytes.
 */
function nw_fuel_inventory_uploaded_file_to_csv(string $tmp_path, string $filename): string|WP_Error
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    if ($ext === 'xls') {
        return new WP_Error(
            'nw_fuel_xls_unsupported',
            __('Old .xls files are not supported. Save as .xlsx or CSV and try again.', 'nw-fuel')
        );
    }

    if ($ext === 'xlsx') {
        if (! function_exists('nw_fuel_xlsx_to_csv')) {
            return new WP_Error('nw_fuel_xlsx_missing', __('Excel converter is not available. Upload a CSV instead.', 'nw-fuel'));
        }

        return nw_fuel_xlsx_to_csv($tmp_path);
    }

    if ($ext !== 'csv') {
        return new WP_Error(
            'nw_fuel_inventory_bad_type',
            __('Upload a .xlsx or .csv file.', 'nw-fuel')
        );
    }

    $bytes = file_get_contents($tmp_path);
    if (! is_string($bytes)) {
        return new WP_Error('nw_fuel_inventory_read', __('Unable to read the uploaded file.', 'nw-fuel'));
    }

    return $bytes;
}
