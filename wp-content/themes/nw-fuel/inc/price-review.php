<?php
/**
 * Inventory price-decrease review workflow and alert emails.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_PRICE_REVIEW_POST_TYPE = 'nw_price_review';
const NW_FUEL_PRICE_REVIEW_SETTINGS  = 'nw_fuel_price_review_settings';
const NW_FUEL_PRICE_HISTORY_META     = '_nw_price_history';

add_action('init', 'nw_fuel_register_price_review_post_type');
add_action('admin_menu', 'nw_fuel_register_price_review_page', 30);
add_action('wp_dashboard_setup', 'nw_fuel_register_price_review_dashboard_widget');
add_action('admin_post_nw_fuel_save_price_review_settings', 'nw_fuel_handle_save_price_review_settings');
add_action('admin_post_nw_fuel_price_review_action', 'nw_fuel_handle_price_review_action');
add_action('nw_fuel_inventory_sync_complete', 'nw_fuel_send_inventory_result_alert', 20);
add_filter('rest_post_dispatch', 'nw_fuel_maybe_alert_inventory_api_failure', 20, 3);

/**
 * Internal records are managed through the purpose-built review screen.
 */
function nw_fuel_register_price_review_post_type(): void
{
    register_post_type(NW_FUEL_PRICE_REVIEW_POST_TYPE, [
        'labels' => [
            'name'          => __('Price reviews', 'nw-fuel'),
            'singular_name' => __('Price review', 'nw-fuel'),
        ],
        'public'              => false,
        'show_ui'             => false,
        'show_in_rest'        => false,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);
}

/**
 * Price columns that require review when they decrease beyond the threshold.
 * Cost/core is intentionally excluded.
 *
 * @return array<string, array{meta_key:string,label:string}>
 */
function nw_fuel_price_review_columns(): array
{
    return [
        'ListTotal'     => ['meta_key' => '_nw_retail', 'label' => __('Retail', 'nw-fuel')],
        'Wholesale'     => ['meta_key' => '_nw_wholesale', 'label' => __('Wholesale', 'nw-fuel')],
        'TradeTotal'    => ['meta_key' => '_nw_trade_total', 'label' => __('Trade / Level 1', 'nw-fuel')],
        'Special1Total' => ['meta_key' => '_nw_special1_total', 'label' => __('Special / Level 2', 'nw-fuel')],
        'Special2Total' => ['meta_key' => '_nw_special2_total', 'label' => __('Special / Level 3', 'nw-fuel')],
        'Special3Total' => ['meta_key' => '_nw_special3_total', 'label' => __('Special / Level 4', 'nw-fuel')],
    ];
}

/**
 * @return array{enabled:bool,threshold:float,recipients:string,cc:string,from_address:string,from_name:string}
 */
function nw_fuel_price_review_settings(): array
{
    $saved = get_option(NW_FUEL_PRICE_REVIEW_SETTINGS, []);
    if (! is_array($saved)) {
        $saved = [];
    }

    return [
        'enabled'      => ! array_key_exists('enabled', $saved) || ! empty($saved['enabled']),
        'threshold'    => min(100.0, max(0.01, (float) ($saved['threshold'] ?? 10))),
        'recipients'   => (string) ($saved['recipients'] ?? 'gord@nwfuel.ca, ahmad@nwfuel.ca, grace@nwfuel.ca'),
        'cc'           => (string) ($saved['cc'] ?? 'info@webpulse.ca'),
        'from_address' => sanitize_email((string) ($saved['from_address'] ?? 'do-not-reply@webpulse.ca')),
        'from_name'    => sanitize_text_field((string) ($saved['from_name'] ?? 'NWF Website | Automated Message')),
    ];
}

/**
 * @return list<string>
 */
function nw_fuel_price_review_recipient_list(): array
{
    $settings = nw_fuel_price_review_settings();
    $parts    = preg_split('/[,;\s]+/', $settings['recipients']) ?: [];
    $emails   = [];
    foreach ($parts as $part) {
        $email = sanitize_email($part);
        if ($email !== '' && is_email($email)) {
            $emails[] = strtolower($email);
        }
    }

    return array_values(array_unique($emails));
}

/**
 * @return list<string>
 */
function nw_fuel_price_review_cc_list(): array
{
    $settings = nw_fuel_price_review_settings();
    $parts    = preg_split('/[,;\s]+/', $settings['cc']) ?: [];
    $emails   = [];
    foreach ($parts as $part) {
        $email = sanitize_email($part);
        if ($email !== '' && is_email($email)) {
            $emails[] = strtolower($email);
        }
    }

    return array_values(array_unique($emails));
}

/**
 * CSV/API failure recipients are operational contacts and are intentionally
 * not editable from WordPress. Local development is isolated from customers.
 *
 * @return list<string>
 */
function nw_fuel_inventory_error_recipient_list(): array
{
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        return ['moemortezaei@gmail.com'];
    }

    return [
        'gord@nwfuel.ca',
        'ahmad@nwfuel.ca',
        'grace@nwfuel.ca',
    ];
}

/**
 * @return list<string>
 */
function nw_fuel_inventory_error_cc_list(): array
{
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
        ? []
        : ['info@webpulse.ca'];
}

/**
 * Find the open review record for a product.
 */
function nw_fuel_find_pending_price_review(int $product_id): int
{
    $ids = get_posts([
        'post_type'      => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_nw_product_id', 'value' => $product_id, 'type' => 'NUMERIC'],
            ['key' => '_nw_review_status', 'value' => 'pending'],
        ],
        'no_found_rows'  => true,
    ]);

    return $ids !== [] ? (int) $ids[0] : 0;
}

/**
 * Hold qualifying decreases and return a row safe to apply to the product.
 * One review record is maintained per product, even when several price tiers change.
 *
 * @param array<string, string> $row
 * @return array<string, string>
 */
function nw_fuel_price_review_filter_inventory_prices(
    int $product_id,
    array $row,
    bool $only_present,
    string $import_id
): array {
    $settings = nw_fuel_price_review_settings();
    if (! $settings['enabled'] || $product_id <= 0) {
        return $row;
    }

    $safe_row  = $row;
    $pending_id = nw_fuel_find_pending_price_review($product_id);
    $previous   = $pending_id > 0 ? get_post_meta($pending_id, '_nw_review_changes', true) : [];
    $changes    = is_array($previous) ? $previous : [];
    $rejected   = get_post_meta($product_id, '_nw_rejected_price_proposals', true);
    $rejected   = is_array($rejected) ? $rejected : [];
    foreach (nw_fuel_price_review_columns() as $column => $config) {
        if (! array_key_exists($column, $row)) {
            // Preserve an existing pending change when a partial file omits its column.
            if (isset($changes[$column])) {
                $safe_row[$column] = (string) get_post_meta($product_id, $config['meta_key'], true);
            }
            continue;
        }

        // This import supplied the column, so it replaces any older proposal for it.
        unset($changes[$column]);

        $old_raw = (string) get_post_meta($product_id, $config['meta_key'], true);
        $old     = is_numeric($old_raw) ? (float) $old_raw : 0.0;
        if ($old <= 0) {
            unset($rejected[$config['meta_key']]);
            continue;
        }

        $parsed       = function_exists('nw_fuel_inventory_parse_money')
            ? nw_fuel_inventory_parse_money((string) $row[$column])
            : (string) $row[$column];
        $proposed     = is_numeric($parsed) ? (float) $parsed : 0.0;
        $decrease_pct = (($old - $proposed) / $old) * 100;
        if ($proposed >= $old || $decrease_pct + 0.00001 < $settings['threshold']) {
            unset($rejected[$config['meta_key']]);
            continue;
        }

        // Preserve the live approved price until an administrator approves.
        $safe_row[$column] = number_format($old, 2, '.', '');

        // Do not recreate the same request every night after an explicit rejection.
        if (array_key_exists($config['meta_key'], $rejected)
            && (string) $rejected[$config['meta_key']] === (string) $parsed) {
            continue;
        }
        unset($rejected[$config['meta_key']]);

        $changes[$column] = [
            'column'           => $column,
            'meta_key'         => $config['meta_key'],
            'label'            => $config['label'],
            'old'              => number_format($old, 2, '.', ''),
            'proposed'         => $parsed,
            'decrease_percent' => round($decrease_pct, 2),
        ];

    }

    if ($rejected === []) {
        delete_post_meta($product_id, '_nw_rejected_price_proposals');
    } else {
        update_post_meta($product_id, '_nw_rejected_price_proposals', $rejected);
    }

    if ($changes === []) {
        if ($pending_id > 0) {
            update_post_meta($pending_id, '_nw_review_status', 'superseded');
            update_post_meta($pending_id, '_nw_review_decided_at', gmdate('c'));
        }
        return $safe_row;
    }

    $part  = function_exists('nw_fuel_get_part_number') ? nw_fuel_get_part_number($product_id) : '';
    $title = get_the_title($product_id);
    $post  = [
        'post_type'   => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status' => 'publish',
        'post_title'  => sprintf('%s — %s', $part !== '' ? $part : '#' . $product_id, $title),
    ];

    if ($pending_id > 0) {
        $post['ID'] = $pending_id;
        wp_update_post($post);
        $review_id = $pending_id;
    } else {
        $inserted = wp_insert_post($post, true);
        if (is_wp_error($inserted)) {
            // Safety first: even if the review record cannot be created, do not apply the decrease.
            return $safe_row;
        }
        $review_id = (int) $inserted;
        update_post_meta($review_id, '_nw_review_created_at', gmdate('c'));
    }

    update_post_meta($review_id, '_nw_product_id', $product_id);
    update_post_meta($review_id, '_nw_part_number', $part);
    update_post_meta($review_id, '_nw_review_status', 'pending');
    update_post_meta($review_id, '_nw_review_changes', $changes);
    update_post_meta($review_id, '_nw_review_import_id', sanitize_text_field($import_id));
    update_post_meta($review_id, '_nw_review_updated_at', gmdate('c'));

    return $safe_row;
}

/**
 * Save a compact post-import price snapshot for the admin chart.
 */
function nw_fuel_price_review_record_history(int $product_id, string $import_id = ''): void
{
    if ($product_id <= 0) {
        return;
    }

    $prices = [];
    foreach (nw_fuel_price_review_columns() as $column => $config) {
        unset($column);
        $value = (string) get_post_meta($product_id, $config['meta_key'], true);
        $prices[$config['meta_key']] = is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';
    }

    $history = get_post_meta($product_id, NW_FUEL_PRICE_HISTORY_META, true);
    if (! is_array($history)) {
        $history = [];
    }

    $last = $history !== [] ? end($history) : null;
    if (is_array($last) && ($last['prices'] ?? []) === $prices) {
        return;
    }

    $history[] = [
        'time'      => gmdate('c'),
        'import_id' => sanitize_text_field($import_id),
        'prices'    => $prices,
    ];
    $history = array_slice($history, -120);
    update_post_meta($product_id, NW_FUEL_PRICE_HISTORY_META, $history);
}

/**
 * Pending reviews touched by an import.
 *
 * @return list<int>
 */
function nw_fuel_price_reviews_for_import(string $import_id): array
{
    if ($import_id === '') {
        return [];
    }

    return array_map('intval', get_posts([
        'post_type'      => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_nw_review_import_id', 'value' => $import_id],
            ['key' => '_nw_review_status', 'value' => 'pending'],
        ],
        'no_found_rows'  => true,
    ]));
}

/**
 * Send exactly one summary email after a completed import when attention is needed.
 *
 * @param array<string, mixed> $result
 */
function nw_fuel_send_inventory_result_alert(array $result): void
{
    if (! empty($result['running'])) {
        return;
    }

    $import_id = sanitize_text_field((string) ($result['import_id'] ?? ''));
    $fingerprint = $import_id !== ''
        ? $import_id
        : md5(wp_json_encode([$result['started'] ?? '', $result['message'] ?? '', $result['source'] ?? '']));
    $dedupe_key = 'nw_fuel_alert_' . md5($fingerprint);
    if (get_transient($dedupe_key)) {
        return;
    }

    $review_ids = nw_fuel_price_reviews_for_import($import_id);
    $failed     = empty($result['ok']) || (int) ($result['errors'] ?? 0) > 0;
    if (! $failed && $review_ids === []) {
        return;
    }

    $recipients = $failed
        ? nw_fuel_inventory_error_recipient_list()
        : nw_fuel_price_review_recipient_list();
    if ($recipients === []) {
        return;
    }

    $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    $subject   = $failed
        ? sprintf('[%s] Inventory import needs attention', $site_name)
        : sprintf('[%s] %d price change%s waiting for review', $site_name, count($review_ids), count($review_ids) === 1 ? '' : 's');

    $lines = [
        $failed ? 'The inventory import needs attention.' : 'Inventory import completed with price decreases held for approval.',
        '',
        'Source: ' . (string) ($result['source'] ?? 'unknown'),
        'Started: ' . (string) ($result['started'] ?? ''),
        'Finished: ' . (string) ($result['finished'] ?? ''),
        'Rows: ' . (int) ($result['rows'] ?? 0),
        'Errors: ' . (int) ($result['errors'] ?? 0),
    ];

    if ($failed) {
        $lines[] = 'Message: ' . (string) ($result['message'] ?? 'Unknown error');
        $log = is_array($result['log'] ?? null) ? $result['log'] : [];
        foreach (array_slice($log, 0, 10) as $entry) {
            $lines[] = '- ' . sanitize_text_field((string) $entry);
        }
        $failed_file = sanitize_file_name((string) ($result['failed_file'] ?? ''));
        if ($failed_file !== '' && function_exists('nw_fuel_inventory_download_url')) {
            $lines[] = 'Retained failed file (7 days): ' . $failed_file;
            $lines[] = 'Download (WordPress admin login required): ' . nw_fuel_inventory_download_url($failed_file);
        }
    }

    if ($review_ids !== []) {
        $lines[] = '';
        $lines[] = sprintf('%d product%s waiting for review:', count($review_ids), count($review_ids) === 1 ? ' is' : 's are');
        foreach (array_slice($review_ids, 0, 30) as $review_id) {
            $part    = (string) get_post_meta($review_id, '_nw_part_number', true);
            $changes = get_post_meta($review_id, '_nw_review_changes', true);
            $summary = [];
            if (is_array($changes)) {
                foreach ($changes as $change) {
                    if (! is_array($change)) {
                        continue;
                    }
                    $summary[] = sprintf(
                        '%s $%s -> $%s (-%s%%)',
                        (string) ($change['label'] ?? 'Price'),
                        (string) ($change['old'] ?? '0.00'),
                        (string) ($change['proposed'] ?? '0.00'),
                        (string) ($change['decrease_percent'] ?? '0')
                    );
                }
            }
            $lines[] = sprintf('- %s: %s', $part !== '' ? $part : '#' . $review_id, implode('; ', $summary));
        }
        if (count($review_ids) > 30) {
            $lines[] = sprintf('- Plus %d more products.', count($review_ids) - 30);
        }
        $lines[] = '';
        $lines[] = 'Review and approve/reject: ' . admin_url('edit.php?post_type=product&page=nw-fuel-price-review');
        $lines[] = 'Prices remain unchanged until approved. There is no automatic approval after 24 hours.';
    }

    $settings = nw_fuel_price_review_settings();
    $headers  = ['Content-Type: text/plain; charset=UTF-8'];
    if ($settings['from_address'] !== '' && is_email($settings['from_address'])) {
        $headers[] = sprintf('From: %s <%s>', $settings['from_name'], $settings['from_address']);
    }
    $cc_list = $failed
        ? nw_fuel_inventory_error_cc_list()
        : nw_fuel_price_review_cc_list();
    foreach ($cc_list as $cc) {
        $headers[] = 'Cc: ' . $cc;
    }

    if (wp_mail($recipients, $subject, implode("\n", $lines), $headers)) {
        set_transient($dedupe_key, 1, 7 * DAY_IN_SECONDS);
    }
}

/**
 * Route a pre-sync upload/validation error through the same single-email alert.
 */
function nw_fuel_alert_inventory_upload_failure(string $message, string $source = 'upload', string $failed_file = ''): void
{
    nw_fuel_send_inventory_result_alert([
        'ok'        => false,
        'running'   => false,
        'message'   => sanitize_text_field($message),
        'source'    => sanitize_key($source),
        'rows'      => 0,
        'errors'    => 1,
        'started'   => gmdate('c'),
        'finished'  => gmdate('c'),
        'import_id' => 'upload-' . wp_generate_uuid4(),
        'failed_file'=> sanitize_file_name($failed_file),
    ]);
}

/**
 * Alert on authenticated inventory API upload failures, excluding auth/rate-limit noise.
 *
 * @param mixed           $response
 * @param WP_REST_Server  $server
 * @param WP_REST_Request $request
 * @return mixed
 */
function nw_fuel_maybe_alert_inventory_api_failure($response, WP_REST_Server $server, WP_REST_Request $request)
{
    unset($server);
    if ($request->get_route() !== '/nw-fuel/v1/inventory/csv' || $request->get_method() !== 'POST') {
        return $response;
    }

    $status  = 500;
    $code    = '';
    $message = '';
    $failed_file = '';
    if (is_wp_error($response)) {
        $code    = (string) $response->get_error_code();
        $message = $response->get_error_message($code);
        $error_data = $response->get_error_data($code);
        if (is_array($error_data) && isset($error_data['status'])) {
            $status = (int) $error_data['status'];
        }
        if (is_array($error_data) && isset($error_data['failed_file'])) {
            $failed_file = sanitize_file_name((string) $error_data['failed_file']);
        }
    } else {
        $rest_response = rest_ensure_response($response);
        $status = $rest_response->get_status();
        $data   = $rest_response->get_data();
        if (is_array($data)) {
            $code    = (string) ($data['code'] ?? '');
            $message = (string) ($data['message'] ?? '');
            if (is_array($data['data'] ?? null) && isset($data['data']['failed_file'])) {
                $failed_file = sanitize_file_name((string) $data['data']['failed_file']);
            }
        }
    }
    if ($status < 400) {
        return $response;
    }

    $ignored = [
        'nw_fuel_missing_api_key',
        'nw_fuel_invalid_api_key',
        'nw_fuel_api_key_not_configured',
        'nw_fuel_rate_limited',
    ];
    if (in_array($code, $ignored, true)) {
        return $response;
    }

    $message = $message !== '' ? $message : 'Inventory API upload failed.';
    if ($failed_file !== '') {
        $message .= ' Retained as ' . $failed_file . '.';
    }
    nw_fuel_alert_inventory_upload_failure($message, 'api_upload', $failed_file);

    return $response;
}

/**
 * Count open records for the menu badge.
 */
function nw_fuel_pending_price_review_count(): int
{
    $counts = wp_count_posts(NW_FUEL_PRICE_REVIEW_POST_TYPE);
    if (! $counts) {
        return 0;
    }

    $query = new WP_Query([
        'post_type'      => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_nw_review_status',
        'meta_value'     => 'pending',
    ]);

    return (int) $query->found_posts;
}

function nw_fuel_register_price_review_page(): void
{
    if (! post_type_exists('product')) {
        return;
    }

    $count = nw_fuel_pending_price_review_count();
    $label = __('Price Review', 'nw-fuel');
    if ($count > 0) {
        $label .= sprintf(' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count);
    }

    add_submenu_page(
        'edit.php?post_type=product',
        __('Price Review', 'nw-fuel'),
        $label,
        'manage_woocommerce',
        'nw-fuel-price-review',
        'nw_fuel_render_price_review_page'
    );
}

function nw_fuel_handle_save_price_review_settings(): void
{
    if (! current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    check_admin_referer('nw_fuel_save_price_review_settings');

    $threshold = isset($_POST['threshold']) ? (float) wp_unslash($_POST['threshold']) : 10.0;
    $raw_recipients = sanitize_textarea_field((string) wp_unslash($_POST['recipients'] ?? ''));
    $valid = [];
    foreach (preg_split('/[,;\s]+/', $raw_recipients) ?: [] as $candidate) {
        $email = sanitize_email($candidate);
        if ($email !== '' && is_email($email)) {
            $valid[] = strtolower($email);
        }
    }
    $raw_cc = sanitize_textarea_field((string) wp_unslash($_POST['cc'] ?? ''));
    $valid_cc = [];
    foreach (preg_split('/[,;\s]+/', $raw_cc) ?: [] as $candidate) {
        $email = sanitize_email($candidate);
        if ($email !== '' && is_email($email)) {
            $valid_cc[] = strtolower($email);
        }
    }

    update_option(NW_FUEL_PRICE_REVIEW_SETTINGS, [
        'enabled'      => ! empty($_POST['enabled']),
        'threshold'    => min(100.0, max(0.01, $threshold)),
        'recipients'   => implode(', ', array_values(array_unique($valid))),
        'cc'           => implode(', ', array_values(array_unique($valid_cc))),
        'from_address' => sanitize_email((string) wp_unslash($_POST['from_address'] ?? '')),
        'from_name'    => sanitize_text_field((string) wp_unslash($_POST['from_name'] ?? '')),
    ], false);

    wp_safe_redirect(add_query_arg([
        'post_type' => 'product',
        'page'      => 'nw-fuel-price-review',
        'saved'     => 1,
    ], admin_url('edit.php')));
    exit;
}

/**
 * Apply a decision to one review.
 */
function nw_fuel_decide_price_review(int $review_id, string $decision): bool
{
    if (get_post_type($review_id) !== NW_FUEL_PRICE_REVIEW_POST_TYPE) {
        return false;
    }
    if ((string) get_post_meta($review_id, '_nw_review_status', true) !== 'pending') {
        return false;
    }

    $product_id = (int) get_post_meta($review_id, '_nw_product_id', true);
    $changes    = get_post_meta($review_id, '_nw_review_changes', true);
    if ($decision === 'approve' && $product_id > 0 && is_array($changes)) {
        $rejected = get_post_meta($product_id, '_nw_rejected_price_proposals', true);
        $rejected = is_array($rejected) ? $rejected : [];
        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }
            $meta_key = (string) ($change['meta_key'] ?? '');
            if (! in_array($meta_key, array_column(nw_fuel_price_review_columns(), 'meta_key'), true)) {
                continue;
            }
            update_post_meta($product_id, $meta_key, (string) ($change['proposed'] ?? ''));
            unset($rejected[$meta_key]);
        }
        if ($rejected === []) {
            delete_post_meta($product_id, '_nw_rejected_price_proposals');
        } else {
            update_post_meta($product_id, '_nw_rejected_price_proposals', $rejected);
        }
        nw_fuel_price_review_record_history($product_id, 'approval-' . $review_id);
    } elseif ($decision === 'reject' && $product_id > 0 && is_array($changes)) {
        $rejected = get_post_meta($product_id, '_nw_rejected_price_proposals', true);
        $rejected = is_array($rejected) ? $rejected : [];
        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }
            $meta_key = (string) ($change['meta_key'] ?? '');
            if (in_array($meta_key, array_column(nw_fuel_price_review_columns(), 'meta_key'), true)) {
                $rejected[$meta_key] = (string) ($change['proposed'] ?? '');
            }
        }
        update_post_meta($product_id, '_nw_rejected_price_proposals', $rejected);
    }

    update_post_meta($review_id, '_nw_review_status', $decision === 'approve' ? 'approved' : 'rejected');
    update_post_meta($review_id, '_nw_review_decided_at', gmdate('c'));
    update_post_meta($review_id, '_nw_review_decided_by', get_current_user_id());
    return true;
}

function nw_fuel_handle_price_review_action(): void
{
    if (! current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    check_admin_referer('nw_fuel_price_review_action');

    $decision = sanitize_key((string) wp_unslash($_POST['decision'] ?? ''));
    if (! in_array($decision, ['approve', 'reject'], true)) {
        wp_die(esc_html__('Invalid review action.', 'nw-fuel'));
    }

    $ids = isset($_POST['review_ids']) && is_array($_POST['review_ids'])
        ? array_map('absint', wp_unslash($_POST['review_ids']))
        : [];
    $changed = 0;
    foreach (array_unique($ids) as $review_id) {
        if (nw_fuel_decide_price_review($review_id, $decision)) {
            ++$changed;
        }
    }

    $redirect_to = sanitize_key((string) wp_unslash($_POST['redirect_to'] ?? 'price_review'));
    $redirect = $redirect_to === 'dashboard'
        ? add_query_arg([
            'nw_price_decided'  => $changed,
            'nw_price_decision' => $decision,
        ], admin_url('index.php'))
        : add_query_arg([
            'post_type' => 'product',
            'page'      => 'nw-fuel-price-review',
            'decided'   => $changed,
            'decision'  => $decision,
        ], admin_url('edit.php'));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Put pending price decisions on the main WordPress Dashboard.
 */
function nw_fuel_register_price_review_dashboard_widget(): void
{
    if (! current_user_can('manage_woocommerce')) {
        return;
    }

    $count = nw_fuel_pending_price_review_count();
    wp_add_dashboard_widget(
        'nw_fuel_price_review_dashboard',
        sprintf(
            /* translators: %d: pending review count */
            __('Price changes waiting for review (%d)', 'nw-fuel'),
            $count
        ),
        'nw_fuel_render_price_review_dashboard_widget'
    );

    // Keep this operational alert at the top of the main dashboard column.
    global $wp_meta_boxes;
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['nw_fuel_price_review_dashboard'])) {
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['nw_fuel_price_review_dashboard'];
        unset($wp_meta_boxes['dashboard']['normal']['core']['nw_fuel_price_review_dashboard']);
        $wp_meta_boxes['dashboard']['normal']['core'] = [
            'nw_fuel_price_review_dashboard' => $widget,
        ] + $wp_meta_boxes['dashboard']['normal']['core'];
    }
}

function nw_fuel_render_price_review_dashboard_widget(): void
{
    $query = new WP_Query([
        'post_type'      => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_key'       => '_nw_review_status',
        'meta_value'     => 'pending',
    ]);

    if (isset($_GET['nw_price_decided'])) {
        $changed  = absint($_GET['nw_price_decided']);
        $decision = sanitize_key((string) wp_unslash($_GET['nw_price_decision'] ?? ''));
        echo '<div class="notice notice-success inline"><p>';
        echo esc_html(sprintf(
            /* translators: 1: decision, 2: count */
            __('Price review %1$s applied to %2$d product(s).', 'nw-fuel'),
            $decision === 'approve' ? __('approval', 'nw-fuel') : __('rejection', 'nw-fuel'),
            $changed
        ));
        echo '</p></div>';
    }

    if (! $query->have_posts()) {
        echo '<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> ';
        esc_html_e('No price changes are waiting for review.', 'nw-fuel');
        echo '</p>';
        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=product&page=nw-fuel-price-review')) . '">';
        esc_html_e('View price review history', 'nw-fuel');
        echo '</a></p>';
        return;
    }
    ?>
    <p style="margin-top:0"><?php esc_html_e('These decreases are held. The current prices stay live until you approve them.', 'nw-fuel'); ?></p>
    <table class="widefat striped" style="border:0">
      <thead><tr><th><?php esc_html_e('Product', 'nw-fuel'); ?></th><th><?php esc_html_e('Proposed change', 'nw-fuel'); ?></th><th><?php esc_html_e('Decision', 'nw-fuel'); ?></th></tr></thead>
      <tbody>
      <?php while ($query->have_posts()) : $query->the_post();
          $review_id = get_the_ID();
          $product_id = (int) get_post_meta($review_id, '_nw_product_id', true);
          $part = (string) get_post_meta($review_id, '_nw_part_number', true);
          $changes = get_post_meta($review_id, '_nw_review_changes', true);
          ?>
        <tr>
          <td><strong><a href="<?php echo esc_url(get_edit_post_link($product_id) ?: '#'); ?>"><?php echo esc_html(get_the_title($product_id)); ?></a></strong><br><code><?php echo esc_html($part); ?></code></td>
          <td>
            <?php if (is_array($changes)) : foreach ($changes as $change) : if (! is_array($change)) { continue; } ?>
              <div><strong><?php echo esc_html((string) ($change['label'] ?? __('Price', 'nw-fuel'))); ?></strong>: <del><?php echo esc_html('$' . (string) ($change['old'] ?? '0.00')); ?></del> &rarr; <strong><?php echo esc_html('$' . ((string) ($change['proposed'] ?? '') !== '' ? (string) $change['proposed'] : '0.00')); ?></strong> <span style="color:#b32d2e"><?php echo esc_html('(-' . (string) ($change['decrease_percent'] ?? '0') . '%)'); ?></span></div>
            <?php endforeach; endif; ?>
          </td>
          <td>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;flex-wrap:wrap">
              <input type="hidden" name="action" value="nw_fuel_price_review_action">
              <input type="hidden" name="redirect_to" value="dashboard">
              <input type="hidden" name="review_ids[]" value="<?php echo esc_attr((string) $review_id); ?>">
              <?php wp_nonce_field('nw_fuel_price_review_action'); ?>
              <button class="button button-primary button-small" name="decision" value="approve" onclick="return confirm('<?php echo esc_js(__('Apply this proposed price?', 'nw-fuel')); ?>')"><?php esc_html_e('Approve', 'nw-fuel'); ?></button>
              <button class="button button-small" name="decision" value="reject" onclick="return confirm('<?php echo esc_js(__('Reject this change and keep the current price?', 'nw-fuel')); ?>')"><?php esc_html_e('Reject', 'nw-fuel'); ?></button>
            </form>
          </td>
        </tr>
      <?php endwhile; wp_reset_postdata(); ?>
      </tbody>
    </table>
    <p style="margin-bottom:0"><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=nw-fuel-price-review')); ?>"><?php esc_html_e('View all price reviews', 'nw-fuel'); ?></a></p>
    <?php
}

/**
 * Render a dependency-free retail-price history chart.
 *
 * @param list<array<string,mixed>> $history
 */
function nw_fuel_render_price_history_chart(array $history, string $proposed = ''): void
{
    $points = [];
    foreach ($history as $entry) {
        if (! is_array($entry) || ! is_array($entry['prices'] ?? null)) {
            continue;
        }
        $value = (string) ($entry['prices']['_nw_retail'] ?? '');
        if (is_numeric($value)) {
            $points[] = ['value' => (float) $value, 'time' => (string) ($entry['time'] ?? '')];
        }
    }
    if (is_numeric($proposed)) {
        $points[] = ['value' => (float) $proposed, 'time' => __('Proposed', 'nw-fuel')];
    }
    $points = array_slice($points, -20);
    if (count($points) < 2) {
        echo '<p class="description">' . esc_html__('Price history will appear after at least two different imports.', 'nw-fuel') . '</p>';
        return;
    }

    $values = array_column($points, 'value');
    $min    = min($values);
    $max    = max($values);
    $range  = max(0.01, $max - $min);
    $coords = [];
    $last_i = count($points) - 1;
    foreach ($points as $i => $point) {
        $x = 10 + (($i / $last_i) * 580);
        $y = 110 - ((($point['value'] - $min) / $range) * 90);
        $coords[] = round($x, 1) . ',' . round($y, 1);
    }
    ?>
    <svg viewBox="0 0 600 140" role="img" aria-label="<?php esc_attr_e('Retail price history', 'nw-fuel'); ?>" style="width:100%;max-width:700px;background:#fff;border:1px solid #dcdcde;border-radius:4px">
      <line x1="10" y1="110" x2="590" y2="110" stroke="#dcdcde" />
      <polyline points="<?php echo esc_attr(implode(' ', $coords)); ?>" fill="none" stroke="#2271b1" stroke-width="3" />
      <text x="10" y="132" font-size="11" fill="#50575e"><?php echo esc_html('$' . number_format($min, 2)); ?></text>
      <text x="590" y="132" text-anchor="end" font-size="11" fill="#50575e"><?php echo esc_html('$' . number_format($max, 2)); ?></text>
    </svg>
    <?php
}

function nw_fuel_render_price_review_page(): void
{
    if (! current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }

    $settings = nw_fuel_price_review_settings();
    $status   = sanitize_key((string) wp_unslash($_GET['review_status'] ?? 'pending'));
    if (! in_array($status, ['pending', 'approved', 'rejected', 'superseded'], true)) {
        $status = 'pending';
    }
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $query = new WP_Query([
        'post_type'      => NW_FUEL_PRICE_REVIEW_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'paged'          => $paged,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'meta_key'       => '_nw_review_status',
        'meta_value'     => $status,
    ]);
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Price Review', 'nw-fuel'); ?></h1>
      <p><?php esc_html_e('Large price decreases stay at their previous approved value until someone approves them here. Pending changes never auto-approve.', 'nw-fuel'); ?></p>

      <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Price alert settings saved.', 'nw-fuel'); ?></p></div>
      <?php endif; ?>
      <?php if (isset($_GET['decided'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('%d review(s) updated.', 'nw-fuel'), absint($_GET['decided']))); ?></p></div>
      <?php endif; ?>

      <details style="margin:16px 0;background:#fff;border:1px solid #c3c4c7;padding:12px 16px"<?php echo isset($_GET['saved']) ? ' open' : ''; ?>>
        <summary style="cursor:pointer;font-weight:600"><?php esc_html_e('Alert settings', 'nw-fuel'); ?></summary>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="nw_fuel_save_price_review_settings">
          <?php wp_nonce_field('nw_fuel_save_price_review_settings'); ?>
          <table class="form-table" role="presentation">
            <tr><th scope="row"><?php esc_html_e('Protection', 'nw-fuel'); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>> <?php esc_html_e('Hold large price decreases for approval', 'nw-fuel'); ?></label></td></tr>
            <tr><th scope="row"><label for="nw-threshold"><?php esc_html_e('Decrease threshold', 'nw-fuel'); ?></label></th><td><input id="nw-threshold" name="threshold" type="number" min="0.01" max="100" step="0.01" value="<?php echo esc_attr((string) $settings['threshold']); ?>"> %</td></tr>
            <tr><th scope="row"><label for="nw-recipients"><?php esc_html_e('Price-change recipients', 'nw-fuel'); ?></label></th><td><textarea id="nw-recipients" name="recipients" class="large-text" rows="2"><?php echo esc_textarea($settings['recipients']); ?></textarea><p class="description"><?php esc_html_e('Used only for price-review alerts. Separate multiple email addresses with commas.', 'nw-fuel'); ?></p></td></tr>
            <tr><th scope="row"><label for="nw-cc"><?php esc_html_e('Price-change CC', 'nw-fuel'); ?></label></th><td><input id="nw-cc" name="cc" type="text" class="large-text" value="<?php echo esc_attr($settings['cc']); ?>"><p class="description"><?php esc_html_e('Used only for price-review alerts. CSV/API error recipients are fixed in code.', 'nw-fuel'); ?></p></td></tr>
            <tr><th scope="row"><label for="nw-from-address"><?php esc_html_e('From address', 'nw-fuel'); ?></label></th><td><input id="nw-from-address" name="from_address" type="email" class="regular-text" value="<?php echo esc_attr($settings['from_address']); ?>"></td></tr>
            <tr><th scope="row"><label for="nw-from-name"><?php esc_html_e('From name', 'nw-fuel'); ?></label></th><td><input id="nw-from-name" name="from_name" type="text" class="regular-text" value="<?php echo esc_attr($settings['from_name']); ?>"></td></tr>
          </table>
          <?php submit_button(__('Save alert settings', 'nw-fuel')); ?>
        </form>
      </details>

      <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Review status', 'nw-fuel'); ?>">
        <?php foreach (['pending' => __('Pending', 'nw-fuel'), 'approved' => __('Approved', 'nw-fuel'), 'rejected' => __('Rejected', 'nw-fuel'), 'superseded' => __('Superseded', 'nw-fuel')] as $key => $label) : ?>
          <a class="nav-tab<?php echo $status === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['post_type' => 'product', 'page' => 'nw-fuel-price-review', 'review_status' => $key], admin_url('edit.php'))); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </nav>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="nw_fuel_price_review_action">
        <?php wp_nonce_field('nw_fuel_price_review_action'); ?>
        <?php if ($status === 'pending' && $query->have_posts()) : ?>
          <p><button class="button button-primary" name="decision" value="approve" onclick="return confirm('<?php echo esc_js(__('Apply the selected proposed prices?', 'nw-fuel')); ?>')"><?php esc_html_e('Approve selected', 'nw-fuel'); ?></button> <button class="button" name="decision" value="reject" onclick="return confirm('<?php echo esc_js(__('Reject the selected price changes and keep the current prices?', 'nw-fuel')); ?>')"><?php esc_html_e('Reject selected', 'nw-fuel'); ?></button></p>
        <?php endif; ?>
        <table class="wp-list-table widefat fixed striped">
          <thead><tr><td class="manage-column check-column"><input type="checkbox" onclick="document.querySelectorAll('.nw-review-check').forEach(function(el){el.checked=this.checked}.bind(this))"></td><th><?php esc_html_e('Product', 'nw-fuel'); ?></th><th><?php esc_html_e('Price changes', 'nw-fuel'); ?></th><th><?php esc_html_e('Received', 'nw-fuel'); ?></th><th><?php esc_html_e('Status', 'nw-fuel'); ?></th></tr></thead>
          <tbody>
          <?php if (! $query->have_posts()) : ?>
            <tr><td colspan="5"><?php esc_html_e('No price reviews in this status.', 'nw-fuel'); ?></td></tr>
          <?php else : while ($query->have_posts()) : $query->the_post();
              $review_id = get_the_ID();
              $product_id = (int) get_post_meta($review_id, '_nw_product_id', true);
              $part = (string) get_post_meta($review_id, '_nw_part_number', true);
              $changes = get_post_meta($review_id, '_nw_review_changes', true);
              $received = (string) get_post_meta($review_id, '_nw_review_updated_at', true);
              $history = get_post_meta($product_id, NW_FUEL_PRICE_HISTORY_META, true);
              $retail_proposed = is_array($changes) && isset($changes['ListTotal']['proposed']) ? (string) $changes['ListTotal']['proposed'] : '';
              ?>
            <tr>
              <th class="check-column"><input class="nw-review-check" type="checkbox" name="review_ids[]" value="<?php echo esc_attr((string) $review_id); ?>"<?php disabled($status !== 'pending'); ?>></th>
              <td><strong><a href="<?php echo esc_url(get_edit_post_link($product_id) ?: '#'); ?>"><?php echo esc_html(get_the_title($product_id)); ?></a></strong><br><code><?php echo esc_html($part); ?></code><details style="margin-top:8px"><summary><?php esc_html_e('Price history', 'nw-fuel'); ?></summary><?php nw_fuel_render_price_history_chart(is_array($history) ? $history : [], $retail_proposed); ?></details></td>
              <td><?php if (is_array($changes)) : foreach ($changes as $change) : if (! is_array($change)) { continue; } ?><div style="margin-bottom:6px"><strong><?php echo esc_html((string) ($change['label'] ?? 'Price')); ?></strong>: <del><?php echo esc_html('$' . (string) ($change['old'] ?? '0.00')); ?></del> &rarr; <strong><?php echo esc_html('$' . ((string) ($change['proposed'] ?? '') !== '' ? (string) $change['proposed'] : '0.00')); ?></strong> <span style="color:#b32d2e">(<?php echo esc_html('-' . (string) ($change['decrease_percent'] ?? '0') . '%'); ?>)</span></div><?php endforeach; endif; ?></td>
              <td><?php echo esc_html($received !== '' ? wp_date('M j, Y g:i a', strtotime($received)) : '—'); ?></td>
              <td><strong><?php echo esc_html(ucfirst($status)); ?></strong><?php if ($status === 'pending') : ?><br><span class="description"><?php esc_html_e('Current price remains live', 'nw-fuel'); ?></span><?php endif; ?></td>
            </tr>
          <?php endwhile; wp_reset_postdata(); endif; ?>
          </tbody>
        </table>
      </form>
      <?php if ($query->max_num_pages > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(['total' => $query->max_num_pages, 'current' => $paged])); ?></div></div>
      <?php endif; ?>
    </div>
    <?php
}

