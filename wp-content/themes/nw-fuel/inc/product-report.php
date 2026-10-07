<?php
/**
 * Product Report — admin analytics (search/view/order counts, conversion).
 *
 * Available to every user who can manage options (Administrators).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_PRODUCT_REPORT_SCHEMA_VERSION  = '2';
const NW_FUEL_PRODUCT_REPORT_PER_PAGE        = 20;
const NW_FUEL_PRODUCT_REPORT_KEYWORD_LIMIT   = 100;

add_action('after_switch_theme', 'nw_fuel_product_report_install');
add_action('init', 'nw_fuel_product_report_maybe_install');
add_action('template_redirect', 'nw_fuel_product_report_track_singular_view');
add_action('wp_ajax_nw_fuel_product_view', 'nw_fuel_ajax_product_view');
add_action('wp_ajax_nopriv_nw_fuel_product_view', 'nw_fuel_ajax_product_view');
add_action('wp_ajax_nw_fuel_product_search_hit', 'nw_fuel_ajax_product_search_hit');
add_action('wp_ajax_nopriv_nw_fuel_product_search_hit', 'nw_fuel_ajax_product_search_hit');
add_action('admin_menu', 'nw_fuel_register_product_report_page');
add_action('admin_post_nw_fuel_product_report_export', 'nw_fuel_handle_product_report_export');
add_action('admin_post_nw_fuel_product_report_keywords_export', 'nw_fuel_handle_product_report_keywords_export');

/**
 * Every administrator can open the report and its export.
 */
function nw_fuel_user_can_view_product_report(): bool
{
    return is_user_logged_in() && current_user_can('manage_options');
}

/* -----------------------------------------------------------------------
 * Storage — lightweight event log (views + searches). Orders are derived
 * live from existing nw_contact_request quote data, nothing new to store.
 * ---------------------------------------------------------------------*/

function nw_fuel_product_report_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'nw_fuel_product_events';
}

/**
 * Raw search-box log — one row per unique (visitor, term, day), regardless of
 * whether the term matched any product. This is what lets the report surface
 * "what are people typing that we don't carry," not just per-product counts.
 */
function nw_fuel_search_query_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'nw_fuel_search_queries';
}

function nw_fuel_product_report_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table           = nw_fuel_product_report_table();
    $queries_table   = nw_fuel_search_query_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        event_type VARCHAR(10) NOT NULL,
        search_term VARCHAR(190) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY product_event (product_id, event_type),
        KEY created_at (created_at)
    ) {$charset_collate};
    CREATE TABLE {$queries_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        search_term VARCHAR(190) NOT NULL,
        result_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        product_ids TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY search_term (search_term),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}

/**
 * Create the table after a plain theme-file update too (not only on Activate).
 */
function nw_fuel_product_report_maybe_install(): void
{
    if (get_option('nw_fuel_product_report_schema_version') === NW_FUEL_PRODUCT_REPORT_SCHEMA_VERSION) {
        return;
    }

    nw_fuel_product_report_install();
    update_option('nw_fuel_product_report_schema_version', NW_FUEL_PRODUCT_REPORT_SCHEMA_VERSION, false);
}

/* -----------------------------------------------------------------------
 * Tracking
 * ---------------------------------------------------------------------*/

/**
 * Whether the current request should be counted. Skips wp-admin screens
 * (but not admin-ajax). Storefront activity is counted for every visitor,
 * including a logged-in administrator browsing the public site.
 */
function nw_fuel_product_report_should_track(): bool
{
    return ! is_admin() || wp_doing_ajax();
}

function nw_fuel_report_visitor_fingerprint(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    return $ip . '|' . $ua;
}

function nw_fuel_product_report_insert_event(int $product_id, string $type, ?string $term): bool
{
    global $wpdb;

    $ok = $wpdb->insert(
        nw_fuel_product_report_table(),
        [
            'product_id'  => $product_id,
            'event_type'  => $type,
            'search_term' => $term,
            'created_at'  => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s']
    );

    return $ok !== false;
}

/**
 * Record a product detail page view. De-duplicated per visitor/product/day
 * so refreshes don't inflate the count.
 */
function nw_fuel_track_product_view(int $product_id): void
{
    if ($product_id < 1 || ! nw_fuel_product_report_should_track()) {
        return;
    }

    $key = 'nw_fuel_pv_' . $product_id . '_' . md5(nw_fuel_report_visitor_fingerprint()) . '_' . current_time('Ymd');
    if (get_transient($key)) {
        return;
    }

    if (nw_fuel_product_report_insert_event($product_id, 'view', null)) {
        set_transient($key, 1, DAY_IN_SECONDS);
    }
}

/**
 * Count a product detail view from the real product request.
 * A separate ajax beacon covers the same view when the HTML is page-cached.
 */
function nw_fuel_product_report_track_singular_view(): void
{
    if (! is_singular('product') || is_preview()) {
        return;
    }

    nw_fuel_track_product_view((int) get_queried_object_id());
}

/**
 * Uncached view beacon. Same daily de-dupe as the template redirect.
 */
function nw_fuel_ajax_product_view(): void
{
    nocache_headers();

    $product_id = isset($_REQUEST['product_id']) ? absint(wp_unslash((string) $_REQUEST['product_id'])) : 0;
    if ($product_id > 0 && get_post_type($product_id) === 'product' && get_post_status($product_id) === 'publish') {
        nw_fuel_track_product_view($product_id);
    }

    wp_send_json_success();
}

/**
 * Uncached search beacon for a catalog results page (covers a cached results URL).
 */
function nw_fuel_ajax_product_search_hit(): void
{
    nocache_headers();

    $term = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['q'])) : '';
    $ids  = isset($_REQUEST['ids']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['ids'])) : '';
    $product_ids = array_values(array_filter(array_map('absint', explode(',', $ids))));

    if ($term !== '') {
        nw_fuel_track_product_search($term, $product_ids);
    }

    wp_send_json_success();
}

/**
 * Log the raw search term once per visitor/term/day, independent of whether
 * it matched anything — a term with zero matches is often the most useful
 * signal (it says what customers want that the catalog doesn't carry).
 *
 * @param array<int, int> $product_ids
 */
function nw_fuel_log_search_query(string $term, array $product_ids): void
{
    global $wpdb;

    $fingerprint = md5(nw_fuel_report_visitor_fingerprint());
    $key         = 'nw_fuel_sq_' . md5($fingerprint . '|' . mb_strtolower($term)) . '_' . current_time('Ymd');
    if (get_transient($key)) {
        return;
    }

    $ids = array_values(array_unique(array_map('intval', $product_ids)));

    $ok = $wpdb->insert(
        nw_fuel_search_query_table(),
        [
            'search_term'  => $term,
            'result_count' => count($ids),
            'product_ids'  => $ids === [] ? '' : implode(',', $ids),
            'created_at'   => current_time('mysql'),
        ],
        ['%s', '%d', '%s', '%s']
    );

    if ($ok !== false) {
        set_transient($key, 1, DAY_IN_SECONDS);
    }
}

/**
 * Record a search event for each product that matched/was shown for the term.
 *
 * @param array<int, int> $product_ids
 */
function nw_fuel_track_product_search(string $term, array $product_ids): void
{
    $term = trim($term);
    if ($term === '' || ! nw_fuel_product_report_should_track()) {
        return;
    }

    if (mb_strlen($term) < 2) {
        return;
    }

    $term        = mb_substr($term, 0, 190);
    $fingerprint = md5(nw_fuel_report_visitor_fingerprint());
    $day         = current_time('Ymd');

    nw_fuel_log_search_query($term, $product_ids);

    foreach (array_unique(array_map('intval', $product_ids)) as $product_id) {
        if ($product_id < 1) {
            continue;
        }

        // Same visitor + product + term counts once per day, so a suggestion
        // request and the results page (or a refresh) do not double-count.
        $key = 'nw_fuel_ps_' . $product_id . '_' . md5($fingerprint . '|' . mb_strtolower($term)) . '_' . $day;
        if (get_transient($key)) {
            continue;
        }

        if (nw_fuel_product_report_insert_event($product_id, 'search', $term)) {
            set_transient($key, 1, DAY_IN_SECONDS);
        }
    }
}

/* -----------------------------------------------------------------------
 * Report data
 * ---------------------------------------------------------------------*/

function nw_fuel_product_report_valid_date(string $value): string
{
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return ($date instanceof DateTime && $date->format('Y-m-d') === $value) ? $value : '';
}

/**
 * Parse + validate all report GET filters in one place (shared by the page and the CSV export).
 *
 * @return array{month:string,start:string,end:string,search:string,orderby:string,order:string,paged:int}
 */
function nw_fuel_product_report_request_filters(): array
{
    $month = isset($_GET['month']) ? sanitize_text_field(wp_unslash((string) $_GET['month'])) : '';

    if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
        $start = $month . '-01';
        $end   = gmdate('Y-m-t', (int) strtotime($start));
    } else {
        $month = '';
        $start = isset($_GET['start']) ? nw_fuel_product_report_valid_date(sanitize_text_field(wp_unslash((string) $_GET['start']))) : '';
        $end   = isset($_GET['end']) ? nw_fuel_product_report_valid_date(sanitize_text_field(wp_unslash((string) $_GET['end']))) : '';

        if ($end === '') {
            $end = current_time('Y-m-d');
        }
        if ($start === '') {
            $start = gmdate('Y-m-d', (int) strtotime($end . ' -29 days'));
        }
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
    }

    $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : 'views';
    if (! in_array($orderby, ['name', 'searches', 'views', 'orders', 'conversion'], true)) {
        $orderby = 'views';
    }
    $order = (isset($_GET['order']) && strtolower((string) wp_unslash($_GET['order'])) === 'asc') ? 'asc' : 'desc';
    $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

    return compact('month', 'start', 'end', 'search', 'orderby', 'order', 'paged');
}

/**
 * Product IDs matching the free-text filter, reusing the existing part
 * number / SKU / alt-codes / title matcher used by the frontend search.
 *
 * @return list<int>
 */
function nw_fuel_product_report_filtered_product_ids(string $search): array
{
    if ($search === '') {
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        return array_map('intval', $ids);
    }

    return nw_fuel_product_search_match_ids($search, 2000);
}

/**
 * @return array<int, int> product_id => count
 */
function nw_fuel_product_report_event_counts(string $event_type, string $start, string $end): array
{
    global $wpdb;

    $table = nw_fuel_product_report_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT product_id, COUNT(*) AS cnt FROM {$table}
         WHERE event_type = %s AND created_at BETWEEN %s AND %s
         GROUP BY product_id",
        $event_type,
        $start . ' 00:00:00',
        $end . ' 23:59:59'
    ));

    $out = [];
    foreach ((array) $rows as $row) {
        $out[(int) $row->product_id] = (int) $row->cnt;
    }

    return $out;
}

/**
 * Product quote request counts per product — the real "order" signal on this
 * catalog-only site (checkout is disabled). Reuses the existing contact
 * request storage; nothing new to track.
 *
 * @return array<int, int> product_id => count
 */
function nw_fuel_product_report_order_counts(string $start, string $end): array
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.meta_value AS product_id, COUNT(*) AS cnt
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_nw_contact_product_id'
         INNER JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_nw_contact_source' AND src.meta_value = 'product_quote'
         WHERE p.post_type = 'nw_contact_request' AND p.post_status = 'publish'
           AND p.post_date BETWEEN %s AND %s
           AND pm.meta_value <> ''
         GROUP BY pm.meta_value",
        $start . ' 00:00:00',
        $end . ' 23:59:59'
    ));

    $out = [];
    foreach ((array) $rows as $row) {
        $id = (int) $row->product_id;
        if ($id > 0) {
            $out[$id] = (int) $row->cnt;
        }
    }

    return $out;
}

/**
 * Build one row per matching product with merged metrics.
 *
 * @return list<array{id:int,name:string,part:string,searches:int,views:int,orders:int,conversion:?float}>
 */
function nw_fuel_product_report_build_rows(string $search, string $start, string $end): array
{
    $product_ids = nw_fuel_product_report_filtered_product_ids($search);
    if ($product_ids === []) {
        return [];
    }

    $views    = nw_fuel_product_report_event_counts('view', $start, $end);
    $searches = nw_fuel_product_report_event_counts('search', $start, $end);
    $orders   = nw_fuel_product_report_order_counts($start, $end);

    $rows = [];
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (! $product) {
            continue;
        }

        $v = $views[$product_id] ?? 0;
        $o = $orders[$product_id] ?? 0;

        $rows[] = [
            'id'         => $product_id,
            'name'       => $product->get_name(),
            'part'       => function_exists('nw_fuel_get_part_number') ? nw_fuel_get_part_number($product_id) : '',
            'searches'   => $searches[$product_id] ?? 0,
            'views'      => $v,
            'orders'     => $o,
            'conversion' => $v > 0 ? round(($o / $v) * 100, 1) : null,
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function nw_fuel_product_report_sort_rows(array $rows, string $orderby, string $order): array
{
    usort($rows, static function (array $a, array $b) use ($orderby, $order): int {
        $av = $a[$orderby] ?? 0;
        $bv = $b[$orderby] ?? 0;

        if ($orderby === 'conversion') {
            $av ??= -1;
            $bv ??= -1;
        }

        $cmp = $orderby === 'name' ? strcasecmp((string) $av, (string) $bv) : ($av <=> $bv);

        return $order === 'asc' ? $cmp : -$cmp;
    });

    return $rows;
}

/**
 * Split the date range into buckets for the time-series chart: daily for
 * short ranges, weekly for medium, monthly for long — keeps the chart readable.
 */
function nw_fuel_product_report_step_days(string $start, string $end): int
{
    $span = (int) round(((int) strtotime($end) - (int) strtotime($start)) / DAY_IN_SECONDS) + 1;

    if ($span <= 62) {
        return 1;
    }

    return $span <= 366 ? 7 : 30;
}

/**
 * @return list<array{label:string}>
 */
function nw_fuel_product_report_buckets(string $start, string $end): array
{
    $step     = nw_fuel_product_report_step_days($start, $end);
    $start_ts = (int) strtotime($start . ' 00:00:00');
    $end_ts   = (int) strtotime($end . ' 00:00:00');

    $buckets = [];
    $cursor  = $start_ts;
    while ($cursor <= $end_ts) {
        $bucket_end_ts = min($end_ts, $cursor + ($step * DAY_IN_SECONDS) - DAY_IN_SECONDS);
        $label         = date_i18n('M j', $cursor);
        if ($bucket_end_ts > $cursor) {
            $label .= '–' . date_i18n('M j', $bucket_end_ts);
        }
        $buckets[] = ['label' => $label];
        $cursor   += $step * DAY_IN_SECONDS;
    }

    return $buckets;
}

/**
 * @param list<int> $product_ids Empty = no product filter (all products).
 * @return array<int, int> bucket index => count
 */
function nw_fuel_product_report_bucketed_event_counts(string $event_type, string $start, string $end, int $start_ts, int $step_days, array $product_ids): array
{
    global $wpdb;

    $table  = nw_fuel_product_report_table();
    $params = [$start_ts, $step_days * DAY_IN_SECONDS, $event_type, $start . ' 00:00:00', $end . ' 23:59:59'];

    $id_clause = '';
    if ($product_ids !== []) {
        $ids       = array_map('intval', $product_ids);
        $id_clause = ' AND product_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
        $params    = array_merge($params, $ids);
    }

    $sql = "SELECT FLOOR((UNIX_TIMESTAMP(created_at) - %d) / %d) AS bucket_idx, COUNT(*) AS cnt
            FROM {$table}
            WHERE event_type = %s AND created_at BETWEEN %s AND %s {$id_clause}
            GROUP BY bucket_idx";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

    $out = [];
    foreach ((array) $rows as $row) {
        $out[(int) $row->bucket_idx] = (int) $row->cnt;
    }

    return $out;
}

/**
 * @param list<int> $product_ids Empty = no product filter (all products).
 * @return array<int, int> bucket index => count
 */
function nw_fuel_product_report_bucketed_order_counts(string $start, string $end, int $start_ts, int $step_days, array $product_ids): array
{
    global $wpdb;

    $params = [$start_ts, $step_days * DAY_IN_SECONDS, $start . ' 00:00:00', $end . ' 23:59:59'];

    $id_clause = '';
    if ($product_ids !== []) {
        $ids       = array_map('intval', $product_ids);
        $id_clause = ' AND pm.meta_value IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
        $params    = array_merge($params, $ids);
    }

    $sql = "SELECT FLOOR((UNIX_TIMESTAMP(p.post_date) - %d) / %d) AS bucket_idx, COUNT(*) AS cnt
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_nw_contact_product_id'
            INNER JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_nw_contact_source' AND src.meta_value = 'product_quote'
            WHERE p.post_type = 'nw_contact_request' AND p.post_status = 'publish'
              AND p.post_date BETWEEN %s AND %s AND pm.meta_value <> '' {$id_clause}
            GROUP BY bucket_idx";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

    $out = [];
    foreach ((array) $rows as $row) {
        $out[(int) $row->bucket_idx] = (int) $row->cnt;
    }

    return $out;
}

/**
 * @param list<int> $product_ids Empty means "no filter" (search box empty) — skips the IN() clause.
 * @return list<array{label:string,searches:int,views:int,orders:int}>
 */
function nw_fuel_product_report_timeseries(string $start, string $end, array $product_ids): array
{
    $buckets   = nw_fuel_product_report_buckets($start, $end);
    $step_days = nw_fuel_product_report_step_days($start, $end);
    $start_ts  = (int) strtotime($start . ' 00:00:00');

    $views    = nw_fuel_product_report_bucketed_event_counts('view', $start, $end, $start_ts, $step_days, $product_ids);
    $searches = nw_fuel_product_report_bucketed_event_counts('search', $start, $end, $start_ts, $step_days, $product_ids);
    $orders   = nw_fuel_product_report_bucketed_order_counts($start, $end, $start_ts, $step_days, $product_ids);

    $series = [];
    foreach ($buckets as $i => $bucket) {
        $series[] = [
            'label'    => $bucket['label'],
            'searches' => $searches[$i] ?? 0,
            'views'    => $views[$i] ?? 0,
            'orders'   => $orders[$i] ?? 0,
        ];
    }

    return $series;
}

/**
 * Top-engagement products (views + searches) for the donut chart, with the
 * long tail folded into "Other products" — answers "who gets the most attention."
 *
 * @param list<array<string, mixed>> $rows Full filtered row set (unpaginated).
 * @return list<array{label:string,url:string,value:int,views:int,searches:int,orders:int,isOther:bool}>
 */
function nw_fuel_product_report_donut_data(array $rows, int $top_n = 7): array
{
    $ranked = array_map(static function (array $row): array {
        $row['engagement'] = $row['views'] + $row['searches'];

        return $row;
    }, $rows);

    usort($ranked, static fn (array $a, array $b): int => $b['engagement'] <=> $a['engagement']);

    $top  = array_slice($ranked, 0, $top_n);
    $rest = array_slice($ranked, $top_n);

    $slices = [];
    foreach ($top as $row) {
        if ($row['engagement'] <= 0) {
            continue;
        }
        $edit = get_edit_post_link((int) $row['id'], 'raw');
        $slices[] = [
            'label'    => $row['name'],
            'url'      => is_string($edit) ? $edit : '',
            'value'    => $row['engagement'],
            'views'    => $row['views'],
            'searches' => $row['searches'],
            'orders'   => $row['orders'],
            'isOther'  => false,
        ];
    }

    $other_engagement = array_sum(array_column($rest, 'engagement'));
    if ($other_engagement > 0) {
        $slices[] = [
            'label'    => __('Other products', 'nw-fuel'),
            'url'      => '',
            'value'    => $other_engagement,
            'views'    => array_sum(array_column($rest, 'views')),
            'searches' => array_sum(array_column($rest, 'searches')),
            'orders'   => array_sum(array_column($rest, 'orders')),
            'isOther'  => true,
        ];
    }

    return $slices;
}

/**
 * Raw search terms within range, most-frequent first — what people actually
 * typed, matched or not. Capped at NW_FUEL_PRODUCT_REPORT_KEYWORD_LIMIT.
 *
 * @return list<array{term:string,count:int,zero_count:int}>
 */
function nw_fuel_product_report_keyword_rows(string $start, string $end): array
{
    global $wpdb;

    $table = nw_fuel_search_query_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT search_term,
                COUNT(*) AS cnt,
                SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) AS zero_cnt
         FROM {$table}
         WHERE created_at BETWEEN %s AND %s
         GROUP BY search_term
         ORDER BY cnt DESC
         LIMIT %d",
        $start . ' 00:00:00',
        $end . ' 23:59:59',
        NW_FUEL_PRODUCT_REPORT_KEYWORD_LIMIT
    ));

    $out = [];
    foreach ((array) $rows as $row) {
        $out[] = [
            'term'       => (string) $row->search_term,
            'count'      => (int) $row->cnt,
            'zero_count' => (int) $row->zero_cnt,
        ];
    }

    return $out;
}

function nw_fuel_product_report_keyword_total(string $start, string $end): int
{
    global $wpdb;

    $table = nw_fuel_search_query_table();

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT search_term) FROM {$table} WHERE created_at BETWEEN %s AND %s",
        $start . ' 00:00:00',
        $end . ' 23:59:59'
    ));
}

/* -----------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------*/

function nw_fuel_register_product_report_page(): void
{
    $hook = add_menu_page(
        __('Product Report', 'nw-fuel'),
        __('Product Report', 'nw-fuel'),
        'manage_options',
        'nw-fuel-product-report',
        'nw_fuel_render_product_report_page',
        'dashicons-chart-area'
    );

    if (is_string($hook) && $hook !== '') {
        add_action('load-' . $hook, 'nw_fuel_product_report_load');
    }
}

function nw_fuel_product_report_load(): void
{
    add_action('admin_enqueue_scripts', 'nw_fuel_product_report_enqueue_assets');
}

function nw_fuel_product_report_enqueue_assets(): void
{
    wp_enqueue_style(
        'nw-fuel-admin-product-report',
        NW_FUEL_URI . '/assets/css/admin-product-report.css',
        [],
        NW_FUEL_VERSION
    );
    wp_enqueue_script(
        'nw-fuel-admin-product-report',
        NW_FUEL_URI . '/assets/js/admin-product-report.js',
        [],
        NW_FUEL_VERSION,
        true
    );
}

/**
 * Build a report page URL with the given query args merged over current filters.
 *
 * @param array<string, mixed> $args
 */
function nw_fuel_product_report_url(array $args): string
{
    $url = add_query_arg($args, admin_url('admin.php?page=nw-fuel-product-report'));

    return is_string($url) ? $url : admin_url('admin.php?page=nw-fuel-product-report');
}

/**
 * @param array{month:string,start:string,end:string,search:string,orderby:string,order:string,paged:int} $filters
 */
function nw_fuel_product_report_base_args(array $filters): array
{
    return array_filter([
        'month'   => $filters['month'],
        'start'   => $filters['month'] === '' ? $filters['start'] : '',
        'end'     => $filters['month'] === '' ? $filters['end'] : '',
        's'       => $filters['search'],
        'orderby' => $filters['orderby'],
        'order'   => $filters['order'],
    ], static fn ($v): bool => $v !== '');
}

/**
 * @param array{month:string,start:string,end:string,search:string,orderby:string,order:string,paged:int} $filters
 */
function nw_fuel_product_report_sort_link(string $column, string $label, array $filters): string
{
    $next_order = ($filters['orderby'] === $column && $filters['order'] === 'desc') ? 'asc' : 'desc';
    $args       = array_merge(nw_fuel_product_report_base_args($filters), [
        'orderby' => $column,
        'order'   => $next_order,
        'paged'   => 1,
    ]);

    $is_active = $filters['orderby'] === $column;
    $arrow     = $is_active ? ($filters['order'] === 'desc' ? ' <span aria-hidden="true">▼</span>' : ' <span aria-hidden="true">▲</span>') : '';
    $class     = $is_active ? ' class="nw-report-sort is-active"' : ' class="nw-report-sort"';

    return '<a href="' . esc_url(nw_fuel_product_report_url($args)) . '"' . $class . '>' . esc_html($label) . $arrow . '</a>';
}

function nw_fuel_render_product_report_page(): void
{
    if (! nw_fuel_user_can_view_product_report()) {
        wp_die(esc_html__('You are not allowed to access this page.', 'nw-fuel'), 403);
    }

    $filters      = nw_fuel_product_report_request_filters();
    $all_rows     = nw_fuel_product_report_build_rows($filters['search'], $filters['start'], $filters['end']);
    $all_rows     = nw_fuel_product_report_sort_rows($all_rows, $filters['orderby'], $filters['order']);
    $total_rows   = count($all_rows);
    $per_page     = NW_FUEL_PRODUCT_REPORT_PER_PAGE;
    $total_pages  = max(1, (int) ceil($total_rows / $per_page));
    $current_page = min($filters['paged'], $total_pages);
    $page_rows    = array_slice($all_rows, ($current_page - 1) * $per_page, $per_page);

    $total_searches = array_sum(array_column($all_rows, 'searches'));
    $total_views    = array_sum(array_column($all_rows, 'views'));
    $total_orders   = array_sum(array_column($all_rows, 'orders'));
    $overall_conversion = $total_views > 0 ? round(($total_orders / $total_views) * 100, 1) : null;

    $timeseries_ids = $filters['search'] !== '' ? array_column($all_rows, 'id') : [];
    $timeseries     = nw_fuel_product_report_timeseries($filters['start'], $filters['end'], $timeseries_ids);
    $donut          = nw_fuel_product_report_donut_data($all_rows);

    $keyword_rows  = nw_fuel_product_report_keyword_rows($filters['start'], $filters['end']);
    $keyword_total = nw_fuel_product_report_keyword_total($filters['start'], $filters['end']);

    $months = nw_fuel_product_report_month_options();
    ?>
    <div class="wrap nw-report">
      <h1 class="nw-report__title"><?php esc_html_e('Product Report', 'nw-fuel'); ?></h1>
      <p class="nw-report__subtitle"><?php esc_html_e('Search activity, page views, and quote requests per product.', 'nw-fuel'); ?></p>

      <form method="get" class="nw-report-filters">
        <input type="hidden" name="page" value="nw-fuel-product-report">
        <div class="nw-report-filters__row">
          <div class="nw-report-filters__field">
            <label for="nw-report-month"><?php esc_html_e('Month', 'nw-fuel'); ?></label>
            <select id="nw-report-month" name="month">
              <option value=""><?php esc_html_e('Custom range', 'nw-fuel'); ?></option>
              <?php foreach ($months as $value => $label) : ?>
              <option value="<?php echo esc_attr($value); ?>"<?php selected($filters['month'], $value); ?>><?php echo esc_html($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="nw-report-filters__field">
            <label for="nw-report-start"><?php esc_html_e('Start date', 'nw-fuel'); ?></label>
            <input type="date" id="nw-report-start" name="start" value="<?php echo esc_attr($filters['start']); ?>">
          </div>
          <div class="nw-report-filters__field">
            <label for="nw-report-end"><?php esc_html_e('End date', 'nw-fuel'); ?></label>
            <input type="date" id="nw-report-end" name="end" value="<?php echo esc_attr($filters['end']); ?>">
          </div>
          <div class="nw-report-filters__field nw-report-filters__field--grow">
            <label for="nw-report-search"><?php esc_html_e('Product / part number', 'nw-fuel'); ?></label>
            <input type="search" id="nw-report-search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search by name or part #', 'nw-fuel'); ?>">
          </div>
          <div class="nw-report-filters__actions">
            <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'nw-fuel'); ?></button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=nw-fuel-product-report')); ?>"><?php esc_html_e('Reset', 'nw-fuel'); ?></a>
          </div>
        </div>
        <input type="hidden" name="orderby" value="<?php echo esc_attr($filters['orderby']); ?>">
        <input type="hidden" name="order" value="<?php echo esc_attr($filters['order']); ?>">
      </form>

      <div class="nw-report-cards">
        <div class="nw-report-card"><span class="nw-report-card__value"><?php echo esc_html(number_format_i18n($total_searches)); ?></span><span class="nw-report-card__label"><?php esc_html_e('Searches', 'nw-fuel'); ?></span></div>
        <div class="nw-report-card"><span class="nw-report-card__value"><?php echo esc_html(number_format_i18n($total_views)); ?></span><span class="nw-report-card__label"><?php esc_html_e('Product Views', 'nw-fuel'); ?></span></div>
        <div class="nw-report-card"><span class="nw-report-card__value"><?php echo esc_html(number_format_i18n($total_orders)); ?></span><span class="nw-report-card__label"><?php esc_html_e('Quote Requests', 'nw-fuel'); ?></span></div>
        <div class="nw-report-card"><span class="nw-report-card__value"><?php echo esc_html($overall_conversion === null ? '—' : number_format_i18n($overall_conversion, 1) . '%'); ?></span><span class="nw-report-card__label"><?php esc_html_e('Conversion Rate', 'nw-fuel'); ?></span></div>
        <div class="nw-report-card"><span class="nw-report-card__value"><?php echo esc_html(number_format_i18n($keyword_total)); ?></span><span class="nw-report-card__label"><?php esc_html_e('Unique Search Keywords', 'nw-fuel'); ?></span></div>
      </div>

      <?php if ($keyword_rows !== []) : ?>
      <div class="nw-report-chart-panel nw-report-keywords">
        <h2><?php esc_html_e('Search Keywords', 'nw-fuel'); ?></h2>
        <p class="nw-report-chart-panel__help"><?php esc_html_e('Every term typed into a search box or bar, most frequent first. "No results" means the term never matched a product — a signal for demand you may not carry yet.', 'nw-fuel'); ?></p>
        <table class="widefat striped nw-report-table">
          <thead>
            <tr>
              <th><?php esc_html_e('Keyword', 'nw-fuel'); ?></th>
              <th><?php esc_html_e('Times Searched', 'nw-fuel'); ?></th>
              <th><?php esc_html_e('Result', 'nw-fuel'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($keyword_rows as $krow) : ?>
            <tr>
              <td><?php echo esc_html($krow['term']); ?></td>
              <td><?php echo esc_html(number_format_i18n($krow['count'])); ?></td>
              <td>
                <?php if ($krow['zero_count'] === $krow['count']) : ?>
                <span class="nw-report-keyword-badge nw-report-keyword-badge--zero"><?php esc_html_e('No results', 'nw-fuel'); ?></span>
                <?php else : ?>
                <?php esc_html_e('Matched products', 'nw-fuel'); ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nw-report-export">
          <?php wp_nonce_field('nw_fuel_product_report_keywords_export'); ?>
          <input type="hidden" name="action" value="nw_fuel_product_report_keywords_export">
          <input type="hidden" name="month" value="<?php echo esc_attr($filters['month']); ?>">
          <input type="hidden" name="start" value="<?php echo esc_attr($filters['start']); ?>">
          <input type="hidden" name="end" value="<?php echo esc_attr($filters['end']); ?>">
          <button type="submit" class="button"><?php esc_html_e('Export Keywords CSV', 'nw-fuel'); ?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($total_rows === 0) : ?>
      <p class="nw-report-empty"><?php esc_html_e('No products match these filters.', 'nw-fuel'); ?></p>
      <?php else : ?>

      <div class="nw-report-charts">
        <div class="nw-report-chart-panel">
          <h2><?php esc_html_e('Product Activity', 'nw-fuel'); ?></h2>
          <p class="nw-report-chart-panel__help"><?php esc_html_e('Share of searches + views by product in the selected range.', 'nw-fuel'); ?></p>
          <div id="nw-report-donut" class="nw-report-chart" role="img" aria-label="<?php esc_attr_e('Donut chart of product activity share', 'nw-fuel'); ?>"></div>
        </div>
        <div class="nw-report-chart-panel">
          <h2><?php esc_html_e('Activity Over Time', 'nw-fuel'); ?></h2>
          <p class="nw-report-chart-panel__help"><?php esc_html_e('Searches, views, and quote requests across the selected range.', 'nw-fuel'); ?></p>
          <div id="nw-report-timeseries" class="nw-report-chart" role="img" aria-label="<?php esc_attr_e('Line chart of searches, views, and orders over time', 'nw-fuel'); ?>"></div>
        </div>
      </div>

      <table class="widefat striped nw-report-table">
        <thead>
          <tr>
            <th><?php echo nw_fuel_product_report_sort_link('name', __('Product', 'nw-fuel'), $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
            <th><?php echo nw_fuel_product_report_sort_link('searches', __('Search Count', 'nw-fuel'), $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
            <th><?php echo nw_fuel_product_report_sort_link('views', __('Page Views', 'nw-fuel'), $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
            <th><?php echo nw_fuel_product_report_sort_link('orders', __('Quote Requests', 'nw-fuel'), $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
            <th><?php echo nw_fuel_product_report_sort_link('conversion', __('Conversion Rate', 'nw-fuel'), $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($page_rows as $row) :
              $edit_link = get_edit_post_link((int) $row['id']);
              ?>
          <tr>
            <td>
              <?php if ($edit_link) : ?>
              <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($row['name']); ?></a>
              <?php else : ?>
              <?php echo esc_html($row['name']); ?>
              <?php endif; ?>
              <?php if ($row['part'] !== '') : ?><br><code class="nw-report-table__part"><?php echo esc_html($row['part']); ?></code><?php endif; ?>
            </td>
            <td><?php echo esc_html(number_format_i18n($row['searches'])); ?></td>
            <td><?php echo esc_html(number_format_i18n($row['views'])); ?></td>
            <td><?php echo esc_html(number_format_i18n($row['orders'])); ?></td>
            <td><?php echo esc_html($row['conversion'] === null ? '—' : number_format_i18n($row['conversion'], 1) . '%'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1) :
          $pagination_base = nw_fuel_product_report_url(array_merge(nw_fuel_product_report_base_args($filters), ['paged' => '%#%']));
          $links = paginate_links([
              'base'      => $pagination_base,
              'format'    => '',
              'current'   => $current_page,
              'total'     => $total_pages,
              'prev_text' => __('« Previous', 'nw-fuel'),
              'next_text' => __('Next »', 'nw-fuel'),
          ]);
          if (is_string($links) && $links !== '') :
          ?>
      <div class="nw-report-pagination"><?php echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
      <?php endif;
      endif; ?>

      <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nw-report-export">
        <?php wp_nonce_field('nw_fuel_product_report_export'); ?>
        <input type="hidden" name="action" value="nw_fuel_product_report_export">
        <input type="hidden" name="month" value="<?php echo esc_attr($filters['month']); ?>">
        <input type="hidden" name="start" value="<?php echo esc_attr($filters['start']); ?>">
        <input type="hidden" name="end" value="<?php echo esc_attr($filters['end']); ?>">
        <input type="hidden" name="s" value="<?php echo esc_attr($filters['search']); ?>">
        <input type="hidden" name="orderby" value="<?php echo esc_attr($filters['orderby']); ?>">
        <input type="hidden" name="order" value="<?php echo esc_attr($filters['order']); ?>">
        <button type="submit" class="button"><?php esc_html_e('Export CSV', 'nw-fuel'); ?></button>
        <span class="description"><?php esc_html_e('Exports every row matching the current filters, not just this page.', 'nw-fuel'); ?></span>
      </form>

      <?php endif; ?>
    </div>
    <script type="application/json" id="nw-report-data"><?php echo wp_json_encode([
        'donut'      => $donut,
        'timeseries' => $timeseries,
        'labels'     => [
            'searches' => __('Searches', 'nw-fuel'),
            'views'    => __('Views', 'nw-fuel'),
            'orders'   => __('Quote Requests', 'nw-fuel'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <?php
}

/**
 * Last 12 months as `['2026-10' => 'October 2026', …]`.
 *
 * @return array<string, string>
 */
function nw_fuel_product_report_month_options(): array
{
    $options = [];
    $cursor  = (int) strtotime(gmdate('Y-m-01'));

    for ($i = 0; $i < 12; $i++) {
        $key             = gmdate('Y-m', $cursor);
        $options[$key]   = date_i18n('F Y', $cursor);
        $cursor          = (int) strtotime('-1 month', $cursor);
    }

    return $options;
}

/* -----------------------------------------------------------------------
 * CSV export
 * ---------------------------------------------------------------------*/

function nw_fuel_handle_product_report_export(): void
{
    if (! nw_fuel_user_can_view_product_report()) {
        wp_die(esc_html__('You are not allowed to access this report.', 'nw-fuel'), 403);
    }

    check_admin_referer('nw_fuel_product_report_export');

    $filters = nw_fuel_product_report_request_filters();
    $rows    = nw_fuel_product_report_build_rows($filters['search'], $filters['start'], $filters['end']);
    $rows    = nw_fuel_product_report_sort_rows($rows, $filters['orderby'], $filters['order']);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="product-report-' . $filters['start'] . '-to-' . $filters['end'] . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die(esc_html__('Unable to generate the export.', 'nw-fuel'));
    }

    fputcsv($out, [
        __('Product', 'nw-fuel'),
        __('Part Number', 'nw-fuel'),
        __('Search Count', 'nw-fuel'),
        __('Page Views', 'nw-fuel'),
        __('Quote Requests', 'nw-fuel'),
        __('Conversion Rate (%)', 'nw-fuel'),
    ]);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['name'],
            $row['part'],
            $row['searches'],
            $row['views'],
            $row['orders'],
            $row['conversion'] === null ? '' : $row['conversion'],
        ]);
    }

    fclose($out);
    exit;
}

function nw_fuel_handle_product_report_keywords_export(): void
{
    if (! nw_fuel_user_can_view_product_report()) {
        wp_die(esc_html__('You are not allowed to access this report.', 'nw-fuel'), 403);
    }

    check_admin_referer('nw_fuel_product_report_keywords_export');

    $filters = nw_fuel_product_report_request_filters();
    $rows    = nw_fuel_product_report_keyword_rows($filters['start'], $filters['end']);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="search-keywords-' . $filters['start'] . '-to-' . $filters['end'] . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die(esc_html__('Unable to generate the export.', 'nw-fuel'));
    }

    fputcsv($out, [
        __('Keyword', 'nw-fuel'),
        __('Times Searched', 'nw-fuel'),
        __('Had Results', 'nw-fuel'),
    ]);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['term'],
            $row['count'],
            $row['zero_count'] === $row['count'] ? __('No', 'nw-fuel') : __('Yes', 'nw-fuel'),
        ]);
    }

    fclose($out);
    exit;
}
