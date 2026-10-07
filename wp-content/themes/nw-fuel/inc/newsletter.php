<?php
/**
 * Homepage newsletter signups — admin list + CSV export.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_filter('manage_nw_newsletter_signup_posts_columns', 'nw_fuel_newsletter_columns');
add_filter('post_row_actions', 'nw_fuel_newsletter_row_actions', 10, 2);
add_action('add_meta_boxes_nw_newsletter_signup', 'nw_fuel_newsletter_meta_boxes');
add_action('restrict_manage_posts', 'nw_fuel_newsletter_export_button', 10, 2);
add_action('admin_post_nw_fuel_export_newsletter_signups', 'nw_fuel_export_newsletter_signups');
add_action('admin_enqueue_scripts', 'nw_fuel_newsletter_admin_assets');

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_newsletter_columns(array $columns): array
{
    return [
        'cb'    => $columns['cb'] ?? '<input type="checkbox" />',
        'title' => __('Email', 'nw-fuel'),
        'date'  => __('Received', 'nw-fuel'),
    ];
}

/**
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function nw_fuel_newsletter_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type === 'nw_newsletter_signup') {
        unset($actions['view'], $actions['inline hide-if-no-js']);
    }

    return $actions;
}

function nw_fuel_newsletter_meta_boxes(): void
{
    remove_meta_box('slugdiv', 'nw_newsletter_signup', 'normal');
}

function nw_fuel_newsletter_admin_assets(string $hook): void
{
    $screen = get_current_screen();
    if (! $screen || $screen->post_type !== 'nw_newsletter_signup' || $hook !== 'edit.php') {
        return;
    }

    wp_enqueue_style(
        'nw-fuel-admin-meta',
        NW_FUEL_URI . '/assets/css/admin-meta.css',
        [],
        NW_FUEL_VERSION
    );
}

function nw_fuel_store_newsletter_signup(string $email): int
{
    $email = sanitize_email($email);
    if ($email === '' || ! is_email($email)) {
        return 0;
    }

    $post_id = wp_insert_post(
        [
            'post_type'   => 'nw_newsletter_signup',
            'post_status' => 'publish',
            'post_title'  => $email,
            'meta_input'  => [
                '_nw_newsletter_email' => $email,
            ],
        ],
        true
    );

    return is_wp_error($post_id) ? 0 : (int) $post_id;
}

function nw_fuel_newsletter_export_button(string $post_type, string $which): void
{
    if ($post_type !== 'nw_newsletter_signup' || $which !== 'top') {
        return;
    }
    if (! current_user_can('edit_posts')) {
        return;
    }

    $export = ['action' => 'nw_fuel_export_newsletter_signups'];
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    if ($search !== '') {
        $export['s'] = $search;
    }
    $status = isset($_GET['post_status']) ? sanitize_key((string) wp_unslash($_GET['post_status'])) : '';
    if ($status === 'trash') {
        $export['post_status'] = 'trash';
    }

    $url = wp_nonce_url(add_query_arg($export, admin_url('admin-post.php')), 'nw_fuel_export_newsletter_signups');
    echo '<a class="button nw-contact-export" href="' . esc_url($url) . '">' . esc_html__('Export CSV', 'nw-fuel') . '</a>';
}

function nw_fuel_export_newsletter_signups(): void
{
    if (! current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to export newsletter emails.', 'nw-fuel'), '', ['response' => 403]);
    }

    check_admin_referer('nw_fuel_export_newsletter_signups');

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    $status = isset($_GET['post_status']) ? sanitize_key((string) wp_unslash($_GET['post_status'])) : 'publish';
    if ($status !== 'trash') {
        $status = 'publish';
    }

    $args = [
        'post_type'      => 'nw_newsletter_signup',
        'post_status'    => $status,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }

    $query    = new WP_Query($args);
    $date_f   = (string) get_option('date_format') . ' ' . (string) get_option('time_format');
    $filename = 'newsletter-emails-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die(esc_html__('Could not start the export.', 'nw-fuel'));
    }

    $cell = static function (string $value): string {
        if (function_exists('nw_fuel_contact_request_csv_cell')) {
            return nw_fuel_contact_request_csv_cell($value);
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    };

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        __('Email', 'nw-fuel'),
        __('Received', 'nw-fuel'),
    ]);

    foreach ($query->posts as $post) {
        if (! $post instanceof WP_Post) {
            continue;
        }
        $email = (string) get_post_meta((int) $post->ID, '_nw_newsletter_email', true);
        if ($email === '') {
            $email = (string) $post->post_title;
        }
        $received = get_post_timestamp($post);

        fputcsv($out, [
            $cell($email),
            $cell($received ? (string) wp_date($date_f, $received) : ''),
        ]);
    }

    fclose($out);
    exit;
}
