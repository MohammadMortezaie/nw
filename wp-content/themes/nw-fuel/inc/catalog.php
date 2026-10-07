<?php
/**
 * Catalog PDFs — admin CRUD, public list/detail, gated download emails.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes_nw_catalog_item', 'nw_fuel_catalog_meta_boxes');
add_action('save_post_nw_catalog_item', 'nw_fuel_save_catalog_item', 10, 2);
add_filter('manage_nw_catalog_item_posts_columns', 'nw_fuel_catalog_columns');
add_action('manage_nw_catalog_item_posts_custom_column', 'nw_fuel_catalog_column_content', 10, 2);
add_filter('manage_nw_catalog_lead_posts_columns', 'nw_fuel_catalog_lead_columns');
add_action('manage_nw_catalog_lead_posts_custom_column', 'nw_fuel_catalog_lead_column_content', 10, 2);
add_filter('enter_title_here', 'nw_fuel_catalog_title_placeholder', 10, 2);
add_action('admin_enqueue_scripts', 'nw_fuel_catalog_admin_assets');
add_action('init', 'nw_fuel_maybe_flush_catalog_rewrites', 30);
add_action('admin_post_nopriv_nw_fuel_catalog_email', 'nw_fuel_handle_catalog_email');
add_action('admin_post_nw_fuel_catalog_email', 'nw_fuel_handle_catalog_email');
add_action('admin_post_nopriv_nw_fuel_catalog_download', 'nw_fuel_handle_catalog_download');
add_action('admin_post_nw_fuel_catalog_download', 'nw_fuel_handle_catalog_download');
add_filter('post_updated_messages', 'nw_fuel_catalog_updated_messages');
add_filter('post_row_actions', 'nw_fuel_catalog_row_actions', 10, 2);
add_action('add_meta_boxes_nw_catalog_lead', 'nw_fuel_catalog_lead_meta_boxes');
add_action('restrict_manage_posts', 'nw_fuel_catalog_lead_export_button', 10, 2);
add_action('admin_post_nw_fuel_export_catalog_leads', 'nw_fuel_export_catalog_leads');
add_filter('wp_nav_menu_objects', 'nw_fuel_filter_catalog_nav_items', 20, 2);
add_action('template_redirect', 'nw_fuel_block_catalog_front_for_others', 1);
add_action('admin_menu', 'nw_fuel_hide_catalog_admin_menu', 99);
add_action('admin_init', 'nw_fuel_block_catalog_admin_for_others');
add_action('admin_bar_menu', 'nw_fuel_hide_catalog_admin_bar', 999);

function nw_fuel_user_can_see_catalog(): bool
{
    return function_exists('nw_fuel_user_can_see_gallery') && nw_fuel_user_can_see_gallery();
}

/**
 * @return WP_Post[]
 */
function nw_fuel_catalog_items(): array
{
    return get_posts([
        'post_type'      => 'nw_catalog_item',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

function nw_fuel_catalog_url(): string
{
    return get_post_type_archive_link('nw_catalog_item') ?: home_url('/catalog/');
}

function nw_fuel_catalog_item_url(WP_Post|int $item): string
{
    $post = $item instanceof WP_Post ? $item : get_post((int) $item);
    if (! $post instanceof WP_Post || $post->post_type !== 'nw_catalog_item') {
        return nw_fuel_catalog_url();
    }

    return home_url(user_trailingslashit('catalog/' . $post->post_name));
}

function nw_fuel_catalog_file_id(int $post_id): int
{
    return absint((string) get_post_meta($post_id, '_nw_catalog_file_id', true));
}

function nw_fuel_catalog_summary(int $post_id): string
{
    return (string) get_post_meta($post_id, '_nw_catalog_summary', true);
}

function nw_fuel_catalog_file_name(int $post_id): string
{
    $id = nw_fuel_catalog_file_id($post_id);
    if ($id < 1) {
        return '';
    }
    $file = get_attached_file($id);

    return is_string($file) && $file !== '' ? basename($file) : '';
}

function nw_fuel_catalog_cover_id(int $post_id): int
{
    return absint((string) get_post_meta($post_id, '_nw_catalog_cover_id', true));
}

/**
 * @return list<string>
 */
function nw_fuel_catalog_fallback_images(): array
{
    $images = nw_fuel_images();
    $urls   = [];
    foreach (['warehouseParts', 'labEquipment', 'industrialWorkshop', 'engineParts', 'turboEngine'] as $key) {
        $url = '';
        if (isset($images[$key]) && is_array($images[$key])) {
            $url = (string) ($images[$key]['lg'] ?? $images[$key]['md'] ?? '');
        } elseif (isset($images[$key]) && is_string($images[$key])) {
            $url = $images[$key];
        }
        if ($url !== '') {
            $urls[] = $url;
        }
    }
    if ($urls === [] && ! empty($images['hero'])) {
        $urls[] = (string) $images['hero'];
    }

    return array_values(array_unique($urls));
}

function nw_fuel_catalog_cover_url(int $post_id, string $size = 'large'): string
{
    $cover_id = nw_fuel_catalog_cover_id($post_id);
    if ($cover_id > 0) {
        $url = wp_get_attachment_image_url($cover_id, $size);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    $file_id = nw_fuel_catalog_file_id($post_id);
    if ($file_id > 0) {
        $url = wp_get_attachment_image_url($file_id, $size);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    $fallbacks = nw_fuel_catalog_fallback_images();
    if ($fallbacks === []) {
        return '';
    }

    return $fallbacks[$post_id % count($fallbacks)];
}

function nw_fuel_catalog_cover_is_document(int $post_id): bool
{
    if (nw_fuel_catalog_cover_id($post_id) > 0) {
        return false;
    }
    $file_id = nw_fuel_catalog_file_id($post_id);
    if ($file_id < 1) {
        return false;
    }
    $url = wp_get_attachment_image_url($file_id, 'medium');

    return is_string($url) && $url !== '';
}

function nw_fuel_catalog_display_summary(int $post_id): string
{
    $summary = trim(nw_fuel_catalog_summary($post_id));
    if ($summary !== '') {
        return $summary;
    }

    $title = get_the_title($post_id);

    return sprintf(
        /* translators: %s catalog title */
        __('%s from NW Fuel. Part numbers, applications, and current listings from our Surrey shop.', 'nw-fuel'),
        $title !== '' ? $title : __('This catalog', 'nw-fuel')
    );
}

/**
 * @return list<string>
 */
function nw_fuel_catalog_highlights(): array
{
    return [
        __('OEM part numbers and cross-references', 'nw-fuel'),
        __('Application notes for common diesel engines', 'nw-fuel'),
        __('Current product photos and specs', 'nw-fuel'),
        __('Shop pricing reference for trade partners', 'nw-fuel'),
    ];
}

function nw_fuel_catalog_file_size_label(int $post_id): string
{
    $file_id = nw_fuel_catalog_file_id($post_id);
    if ($file_id < 1) {
        return '';
    }

    $path  = get_attached_file($file_id);
    $bytes = 0;
    if (is_string($path) && $path !== '' && is_readable($path)) {
        $bytes = (int) filesize($path);
    }
    if ($bytes < 1) {
        $bytes = (int) get_post_meta($file_id, '_wp_attachment_filesize', true);
    }
    if ($bytes < 1) {
        return '';
    }

    $label = size_format($bytes, 1);

    return is_string($label) ? $label : '';
}

function nw_fuel_catalog_updated_label(int $post_id): string
{
    $date = get_the_modified_date('M Y', $post_id);

    return is_string($date) ? $date : '';
}

/**
 * @return WP_Post[]
 */
function nw_fuel_catalog_related_items(int $post_id, int $limit = 3): array
{
    return get_posts([
        'post_type'      => 'nw_catalog_item',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'post__not_in'   => [$post_id],
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ]);
}

/**
 * Keep a single Catalog link (theme appends it after the WP menu).
 *
 * @param array<int, WP_Post> $items
 * @return array<int, WP_Post>
 */
function nw_fuel_filter_catalog_nav_items(array $items, stdClass $args): array
{
    unset($args);
    $out = [];
    foreach ($items as $item) {
        $title = (string) ($item->title ?? '');
        $url   = (string) ($item->url ?? '');
        $type  = (string) ($item->type ?? '');
        $object = (string) ($item->object ?? '');
        if (strcasecmp($title, 'Catalog') === 0) {
            continue;
        }
        if ($type === 'post_type_archive' && $object === 'nw_catalog_item') {
            continue;
        }
        if (preg_match('~/catalog/?$~', $url)) {
            continue;
        }
        $out[] = $item;
    }

    return $out;
}

function nw_fuel_render_catalog_nav_link(string $context): void
{
    if (! nw_fuel_user_can_see_catalog()) {
        return;
    }

    $nav    = nw_fuel_nav_state();
    $active = ! empty($nav['catalog_active']);
    $url    = nw_fuel_catalog_url();

    if ($context === 'desktop') {
        echo '<a href="' . esc_url($url) . '" class="nav-link' . ($active ? ' is-active' : '') . '">' . esc_html__('Catalog', 'nw-fuel') . '</a>';
        return;
    }

    echo '<a href="' . esc_url($url) . '"' . ($active ? ' class="is-active"' : '') . '>' . esc_html__('Catalog', 'nw-fuel') . '</a>';
}

function nw_fuel_catalog_title_placeholder(string $title, WP_Post $post): string
{
    if ($post->post_type === 'nw_catalog_item') {
        return __('Catalog title', 'nw-fuel');
    }

    return $title;
}

function nw_fuel_catalog_admin_assets(string $hook): void
{
    $screen = get_current_screen();
    if (! $screen || ! in_array($screen->post_type, ['nw_catalog_item', 'nw_catalog_lead'], true)) {
        return;
    }
    if (! in_array($hook, ['edit.php', 'post.php', 'post-new.php'], true)) {
        return;
    }

    wp_enqueue_style(
        'nw-fuel-admin-meta',
        NW_FUEL_URI . '/assets/css/admin-meta.css',
        [],
        NW_FUEL_VERSION
    );
}

function nw_fuel_catalog_meta_boxes(): void
{
    remove_meta_box('slugdiv', 'nw_catalog_item', 'normal');
    add_meta_box(
        'nw-fuel-catalog-item',
        __('PDF', 'nw-fuel'),
        'nw_fuel_catalog_item_box',
        'nw_catalog_item',
        'normal',
        'high'
    );
}

function nw_fuel_catalog_item_box(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_catalog_item', 'nw_fuel_catalog_item_nonce');
    $id      = (int) $post->ID;
    $file_id   = nw_fuel_catalog_file_id($id);
    $name      = nw_fuel_catalog_file_name($id);
    $summary   = nw_fuel_catalog_summary($id);
    $cover_id  = nw_fuel_catalog_cover_id($id);
    $cover_url = $cover_id > 0 ? (string) wp_get_attachment_image_url($cover_id, 'medium') : '';
    ?>
    <div class="nw-admin-field nw-media-field">
      <label><?php esc_html_e('PDF file', 'nw-fuel'); ?></label>
      <p class="nw-catalog-file-name" data-nw-file-name><?php echo $name !== '' ? esc_html($name) : esc_html__('No file selected.', 'nw-fuel'); ?></p>
      <input type="hidden" name="nw_catalog_file_id" value="<?php echo esc_attr((string) $file_id); ?>">
      <div class="nw-media-field__controls">
        <button type="button" class="button" data-nw-media-file data-title="<?php esc_attr_e('Select PDF', 'nw-fuel'); ?>"><?php esc_html_e('Select PDF', 'nw-fuel'); ?></button>
        <button type="button" class="button" data-nw-media-file-clear><?php esc_html_e('Clear', 'nw-fuel'); ?></button>
      </div>
    </div>
    <div class="nw-admin-field nw-media-field">
      <label><?php esc_html_e('Cover image', 'nw-fuel'); ?></label>
      <p class="description"><?php esc_html_e('Optional. If empty, the site uses the PDF preview when WordPress can generate one, otherwise a shop photo.', 'nw-fuel'); ?></p>
      <img class="nw-media-field__preview<?php echo $cover_url !== '' ? ' is-visible' : ''; ?>" src="<?php echo esc_url($cover_url); ?>" alt="">
      <input type="hidden" name="nw_catalog_cover_id" value="<?php echo esc_attr((string) $cover_id); ?>">
      <div class="nw-media-field__controls">
        <button type="button" class="button" data-nw-media-id data-title="<?php esc_attr_e('Select cover image', 'nw-fuel'); ?>"><?php esc_html_e('Select cover', 'nw-fuel'); ?></button>
        <button type="button" class="button" data-nw-media-id-clear><?php esc_html_e('Clear', 'nw-fuel'); ?></button>
      </div>
    </div>
    <div class="nw-admin-field">
      <label for="nw-catalog-summary"><?php esc_html_e('Short description', 'nw-fuel'); ?></label>
      <textarea class="widefat" id="nw-catalog-summary" name="nw_catalog_summary" rows="4"><?php echo esc_textarea($summary); ?></textarea>
      <p class="description"><?php esc_html_e('Optional. If empty, the public pages use a default shop description.', 'nw-fuel'); ?></p>
    </div>
    <?php
}

function nw_fuel_save_catalog_item(int $post_id, WP_Post $post): void
{
    unset($post);
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! isset($_POST['nw_fuel_catalog_item_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_catalog_item_nonce'])), 'nw_fuel_catalog_item')) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    $file_id = absint($_POST['nw_catalog_file_id'] ?? 0);
    if ($file_id > 0) {
        $mime = (string) get_post_mime_type($file_id);
        if ($mime !== 'application/pdf') {
            $file_id = 0;
        }
    }

    $cover_id = absint($_POST['nw_catalog_cover_id'] ?? 0);
    if ($cover_id > 0 && ! wp_attachment_is_image($cover_id)) {
        $cover_id = 0;
    }

    update_post_meta($post_id, '_nw_catalog_file_id', $file_id);
    update_post_meta($post_id, '_nw_catalog_cover_id', $cover_id);
    update_post_meta($post_id, '_nw_catalog_summary', sanitize_textarea_field(wp_unslash((string) ($_POST['nw_catalog_summary'] ?? ''))));
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_catalog_columns(array $columns): array
{
    $out = [];
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ($key === 'title') {
            $out['nw_file']    = __('File', 'nw-fuel');
            $out['nw_summary'] = __('Short description', 'nw-fuel');
        }
    }

    return $out;
}

function nw_fuel_catalog_column_content(string $column, int $post_id): void
{
    if ($column === 'nw_file') {
        $name = nw_fuel_catalog_file_name($post_id);
        echo $name !== '' ? esc_html($name) : '—';
        return;
    }
    if ($column === 'nw_summary') {
        $summary = nw_fuel_catalog_summary($post_id);
        echo $summary !== '' ? esc_html(wp_trim_words($summary, 16)) : '—';
    }
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_catalog_lead_columns(array $columns): array
{
    return [
        'cb'         => $columns['cb'] ?? '<input type="checkbox" />',
        'title'      => __('Email', 'nw-fuel'),
        'nw_catalog' => __('Catalog', 'nw-fuel'),
        'date'       => __('Received', 'nw-fuel'),
    ];
}

function nw_fuel_catalog_lead_column_content(string $column, int $post_id): void
{
    if ($column !== 'nw_catalog') {
        return;
    }
    $catalog_id = absint((string) get_post_meta($post_id, '_nw_catalog_item_id', true));
    $label      = (string) get_post_meta($post_id, '_nw_catalog_item_title', true);
    if ($catalog_id > 0) {
        $edit = get_edit_post_link($catalog_id);
        $name = $label !== '' ? $label : get_the_title($catalog_id);
        if ($edit) {
            echo '<a href="' . esc_url($edit) . '">' . esc_html($name !== '' ? $name : '#' . $catalog_id) . '</a>';
            return;
        }
        echo esc_html($name !== '' ? $name : '#' . $catalog_id);
        return;
    }
    echo $label !== '' ? esc_html($label) : '—';
}

function nw_fuel_catalog_lead_export_button(string $post_type, string $which): void
{
    if ($post_type !== 'nw_catalog_lead' || $which !== 'top') {
        return;
    }
    if (! current_user_can('edit_posts') || ! nw_fuel_user_can_see_catalog()) {
        return;
    }

    $export = ['action' => 'nw_fuel_export_catalog_leads'];
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    if ($search !== '') {
        $export['s'] = $search;
    }
    $status = isset($_GET['post_status']) ? sanitize_key((string) wp_unslash($_GET['post_status'])) : '';
    if ($status === 'trash') {
        $export['post_status'] = 'trash';
    }

    $url = wp_nonce_url(add_query_arg($export, admin_url('admin-post.php')), 'nw_fuel_export_catalog_leads');
    echo '<a class="button nw-contact-export" href="' . esc_url($url) . '">' . esc_html__('Export CSV', 'nw-fuel') . '</a>';
}

function nw_fuel_export_catalog_leads(): void
{
    if (! current_user_can('edit_posts') || ! nw_fuel_user_can_see_catalog()) {
        wp_die(esc_html__('You do not have permission to export download emails.', 'nw-fuel'), '', ['response' => 403]);
    }

    check_admin_referer('nw_fuel_export_catalog_leads');

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    $status = isset($_GET['post_status']) ? sanitize_key((string) wp_unslash($_GET['post_status'])) : 'publish';
    if ($status !== 'trash') {
        $status = 'publish';
    }

    $args = [
        'post_type'      => 'nw_catalog_lead',
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
    $filename = 'catalog-download-emails-' . gmdate('Y-m-d') . '.csv';

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
        __('Catalog', 'nw-fuel'),
        __('Received', 'nw-fuel'),
    ]);

    foreach ($query->posts as $post) {
        if (! $post instanceof WP_Post) {
            continue;
        }
        $id         = (int) $post->ID;
        $email      = (string) get_post_meta($id, '_nw_catalog_email', true);
        if ($email === '') {
            $email = (string) $post->post_title;
        }
        $catalog    = (string) get_post_meta($id, '_nw_catalog_item_title', true);
        $catalog_id = absint((string) get_post_meta($id, '_nw_catalog_item_id', true));
        if ($catalog === '' && $catalog_id > 0) {
            $catalog = (string) get_the_title($catalog_id);
        }
        $received = get_post_timestamp($post);

        fputcsv($out, [
            $cell($email),
            $cell($catalog),
            $cell($received ? (string) wp_date($date_f, $received) : ''),
        ]);
    }

    fclose($out);
    exit;
}

function nw_fuel_catalog_lead_meta_boxes(): void
{
    remove_meta_box('slugdiv', 'nw_catalog_lead', 'normal');
}

/**
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function nw_fuel_catalog_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type === 'nw_catalog_lead') {
        unset($actions['view'], $actions['inline hide-if-no-js']);
    }

    return $actions;
}

function nw_fuel_block_catalog_front_for_others(): void
{
    if (! is_post_type_archive('nw_catalog_item') && ! is_singular('nw_catalog_item')) {
        return;
    }
    if (nw_fuel_user_can_see_catalog()) {
        return;
    }

    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
    include get_query_template('404');
    exit;
}

function nw_fuel_hide_catalog_admin_menu(): void
{
    if (! nw_fuel_user_can_see_catalog()) {
        remove_menu_page('edit.php?post_type=nw_catalog_item');
    }
}

function nw_fuel_block_catalog_admin_for_others(): void
{
    if (! is_admin() || nw_fuel_user_can_see_catalog()) {
        return;
    }

    $type = sanitize_key((string) ($_GET['post_type'] ?? ''));
    if (in_array($type, ['nw_catalog_item', 'nw_catalog_lead'], true)) {
        wp_die(esc_html__('Catalog is not available yet.', 'nw-fuel'), '', ['response' => 403]);
    }

    $post_id = absint($_GET['post'] ?? 0);
    if ($post_id > 0 && in_array((string) get_post_type($post_id), ['nw_catalog_item', 'nw_catalog_lead'], true)) {
        wp_die(esc_html__('Catalog is not available yet.', 'nw-fuel'), '', ['response' => 403]);
    }
}

function nw_fuel_hide_catalog_admin_bar(\WP_Admin_Bar $bar): void
{
    if (! nw_fuel_user_can_see_catalog()) {
        $bar->remove_node('new-nw_catalog_item');
    }
}

function nw_fuel_maybe_flush_catalog_rewrites(): void
{
    if (get_option('nw_fuel_catalog_rewrites') === '1.8.25') {
        return;
    }
    flush_rewrite_rules(false);
    update_option('nw_fuel_catalog_rewrites', '1.8.25', false);
}

/**
 * @return list<int>
 */
function nw_fuel_catalog_unlocked_ids(): array
{
    $raw = (string) ($_COOKIE['nw_fuel_cat'] ?? '');
    if ($raw === '' || ! str_contains($raw, '.')) {
        return [];
    }
    [$payload, $sig] = explode('.', $raw, 2);
    $json = base64_decode($payload, true);
    if (! is_string($json) || ! hash_equals(hash_hmac('sha256', $json, wp_salt('auth')), $sig)) {
        return [];
    }
    $ids = json_decode($json, true);
    if (! is_array($ids)) {
        return [];
    }

    return array_values(array_filter(array_map('absint', $ids)));
}

function nw_fuel_catalog_is_unlocked(int $post_id): bool
{
    return $post_id > 0 && in_array($post_id, nw_fuel_catalog_unlocked_ids(), true);
}

function nw_fuel_catalog_unlock(int $post_id): void
{
    $ids   = nw_fuel_catalog_unlocked_ids();
    $ids[] = $post_id;
    $ids   = array_values(array_unique(array_filter($ids)));
    $json  = wp_json_encode($ids);
    if (! is_string($json)) {
        return;
    }
    $value = base64_encode($json) . '.' . hash_hmac('sha256', $json, wp_salt('auth'));
    $path  = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';
    setcookie('nw_fuel_cat', $value, [
        'expires'  => time() + YEAR_IN_SECONDS,
        'path'     => $path !== '' ? $path : '/',
        'domain'   => $domain,
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['nw_fuel_cat'] = $value;
}

function nw_fuel_catalog_download_url(int $post_id): string
{
    return wp_nonce_url(
        add_query_arg(
            [
                'action' => 'nw_fuel_catalog_download',
                'id'     => $post_id,
            ],
            admin_url('admin-post.php')
        ),
        'nw_fuel_catalog_download_' . $post_id
    );
}

function nw_fuel_store_catalog_lead(int $catalog_id, string $email): void
{
    $title = get_the_title($catalog_id);
    wp_insert_post([
        'post_type'   => 'nw_catalog_lead',
        'post_status' => 'publish',
        'post_title'  => $email,
        'meta_input'  => [
            '_nw_catalog_item_id'    => (string) $catalog_id,
            '_nw_catalog_item_title' => is_string($title) ? $title : '',
            '_nw_catalog_email'      => $email,
        ],
    ]);
}

function nw_fuel_handle_catalog_email(): void
{
    if (function_exists('nw_fuel_gallery_is_preview_only') && nw_fuel_gallery_is_preview_only() && ! nw_fuel_user_can_see_catalog()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $id = absint($_POST['catalog_id'] ?? 0);
    $redirect = $id > 0 ? nw_fuel_catalog_item_url($id) : nw_fuel_catalog_url();

    if (! isset($_POST['nw_fuel_catalog_email_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_catalog_email_nonce'])), 'nw_fuel_catalog_email')) {
        wp_safe_redirect(add_query_arg('catalog', 'error', $redirect));
        exit;
    }

    $honeypot = trim((string) wp_unslash($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        wp_safe_redirect(add_query_arg('catalog', 'ok', $redirect));
        exit;
    }

    $email = sanitize_email(wp_unslash((string) ($_POST['email'] ?? '')));
    $item  = $id > 0 ? get_post($id) : null;
    if ($email === '' || ! is_email($email) || ! $item instanceof WP_Post || $item->post_type !== 'nw_catalog_item' || $item->post_status !== 'publish') {
        wp_safe_redirect(add_query_arg('catalog', 'error', $redirect));
        exit;
    }

    nw_fuel_store_catalog_lead($id, $email);
    nw_fuel_catalog_unlock($id);
    wp_safe_redirect(add_query_arg('catalog', 'ok', $redirect));
    exit;
}

function nw_fuel_handle_catalog_download(): void
{
    if (function_exists('nw_fuel_gallery_is_preview_only') && nw_fuel_gallery_is_preview_only() && ! nw_fuel_user_can_see_catalog()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $id = absint($_GET['id'] ?? 0);
    $item = $id > 0 ? get_post($id) : null;
    $back = $item instanceof WP_Post ? nw_fuel_catalog_item_url($item) : nw_fuel_catalog_url();

    if (! $item instanceof WP_Post || $item->post_type !== 'nw_catalog_item' || $item->post_status !== 'publish') {
        wp_safe_redirect($back);
        exit;
    }

    if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'nw_fuel_catalog_download_' . $id)) {
        wp_safe_redirect(add_query_arg('catalog', 'locked', $back));
        exit;
    }

    if (! nw_fuel_catalog_is_unlocked($id)) {
        wp_safe_redirect(add_query_arg('catalog', 'locked', $back));
        exit;
    }

    $file_id = nw_fuel_catalog_file_id($id);
    $path    = $file_id > 0 ? get_attached_file($file_id) : '';
    if (! is_string($path) || $path === '' || ! is_readable($path)) {
        wp_safe_redirect(add_query_arg('catalog', 'missing', $back));
        exit;
    }

    $name = nw_fuel_catalog_file_name($id) ?: ($item->post_name . '.pdf');
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($name) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

/**
 * @param array<string, array<int, string>> $messages
 * @return array<string, array<int, string>>
 */
function nw_fuel_catalog_updated_messages(array $messages): array
{
    $messages['nw_catalog_item'] = [
        0 => '',
        1 => __('Catalog PDF updated.', 'nw-fuel'),
        4 => __('Catalog PDF updated.', 'nw-fuel'),
        6 => __('Catalog PDF published.', 'nw-fuel'),
        7 => __('Catalog PDF saved.', 'nw-fuel'),
    ];

    return $messages;
}
