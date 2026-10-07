<?php
/**
 * Gallery items — admin CRUD and front-end helpers.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'nw_fuel_gallery_admin_assets');
add_action('add_meta_boxes_nw_gallery_item', 'nw_fuel_gallery_meta_boxes');
add_action('save_post_nw_gallery_item', 'nw_fuel_save_gallery_item', 10, 2);
add_filter('manage_nw_gallery_item_posts_columns', 'nw_fuel_gallery_columns');
add_action('manage_nw_gallery_item_posts_custom_column', 'nw_fuel_gallery_column_content', 10, 2);
add_filter('enter_title_here', 'nw_fuel_gallery_title_placeholder', 10, 2);
add_action('template_redirect', 'nw_fuel_redirect_gallery_singles');
add_action('init', 'nw_fuel_maybe_flush_gallery_rewrites', 30);
add_action('admin_init', 'nw_fuel_ensure_gallery_nav_item');
add_action('admin_menu', 'nw_fuel_hide_gallery_admin_menu', 99);
add_action('admin_init', 'nw_fuel_block_gallery_admin_for_others');
add_action('admin_bar_menu', 'nw_fuel_hide_gallery_admin_bar', 999);
add_filter('wp_nav_menu_objects', 'nw_fuel_filter_gallery_nav_items', 10, 2);
add_filter('post_updated_messages', 'nw_fuel_gallery_updated_messages');
add_filter('post_row_actions', 'nw_fuel_gallery_row_actions', 10, 2);

/**
 * Gallery and Catalog are public. Keep this false.
 */
function nw_fuel_gallery_is_preview_only(): bool
{
    return false;
}

function nw_fuel_gallery_preview_email(): string
{
    return 'e.z.m.computer@gmail.com';
}

function nw_fuel_normalize_email(string $email): string
{
    $email = strtolower(trim($email));
    if (! str_contains($email, '@')) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    if ($domain === 'googlemail.com') {
        $domain = 'gmail.com';
    }
    if ($domain === 'gmail.com') {
        $plus = strpos($local, '+');
        if ($plus !== false) {
            $local = substr($local, 0, $plus);
        }
        $local = str_replace('.', '', $local);
    }

    return $local . '@' . $domain;
}

function nw_fuel_user_can_see_gallery(): bool
{
    if (! nw_fuel_gallery_is_preview_only()) {
        return true;
    }
    if (! is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();
    if (! $user instanceof WP_User || (int) $user->ID < 1) {
        return false;
    }

    $allowed = nw_fuel_normalize_email(nw_fuel_gallery_preview_email());
    $emails  = [
        (string) $user->user_email,
        (string) $user->user_login,
    ];

    foreach ($emails as $candidate) {
        if ($candidate !== '' && nw_fuel_normalize_email($candidate) === $allowed) {
            return true;
        }
    }

    return false;
}

function nw_fuel_gallery_url(): string
{
    return get_post_type_archive_link('nw_gallery_item') ?: home_url('/gallery/');
}

function nw_fuel_render_gallery_nav_link(string $context): void
{
    if (! nw_fuel_user_can_see_gallery()) {
        return;
    }

    $nav    = nw_fuel_nav_state();
    $active = ! empty($nav['gallery_active']);
    $url    = nw_fuel_gallery_url();

    if ($context === 'desktop') {
        echo '<a href="' . esc_url($url) . '" class="nav-link' . ($active ? ' is-active' : '') . '">' . esc_html__('Gallery', 'nw-fuel') . '</a>';
        return;
    }

    echo '<a href="' . esc_url($url) . '"' . ($active ? ' class="is-active"' : '') . '>' . esc_html__('Gallery', 'nw-fuel') . '</a>';
}

function nw_fuel_nav_item_is_gallery(object $item): bool
{
    $title = (string) ($item->title ?? '');
    $url   = (string) ($item->url ?? '');
    $type  = (string) ($item->type ?? '');
    $object = (string) ($item->object ?? '');

    if (strcasecmp($title, 'Gallery') === 0) {
        return true;
    }
    if ($type === 'post_type_archive' && $object === 'nw_gallery_item') {
        return true;
    }

    return str_contains($url, '/gallery');
}

/**
 * @return array<string, string>
 */
function nw_fuel_gallery_types(): array
{
    return [
        'image' => __('Image', 'nw-fuel'),
        'video' => __('Video', 'nw-fuel'),
    ];
}

function nw_fuel_gallery_item_type(int $post_id): string
{
    $type = sanitize_key((string) get_post_meta($post_id, '_nw_gallery_type', true));

    return array_key_exists($type, nw_fuel_gallery_types()) ? $type : 'image';
}

/**
 * Published gallery items, ordered for the public page.
 *
 * @return WP_Post[]
 */
function nw_fuel_gallery_items(): array
{
    return get_posts([
        'post_type'      => 'nw_gallery_item',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

function nw_fuel_gallery_image_id(int $post_id): int
{
    return absint((string) get_post_meta($post_id, '_nw_gallery_image_id', true));
}

function nw_fuel_gallery_video_url(int $post_id): string
{
    return esc_url_raw((string) get_post_meta($post_id, '_nw_gallery_video_url', true));
}

function nw_fuel_gallery_image_url(int $post_id, string $size = 'large'): string
{
    $id = nw_fuel_gallery_image_id($post_id);
    if ($id < 1) {
        return '';
    }
    $url = wp_get_attachment_image_url($id, $size);

    return is_string($url) ? $url : '';
}

/**
 * YouTube / Vimeo embed URL, or empty if the link is a direct file.
 */
function nw_fuel_gallery_embed_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }

    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }

    return '';
}

function nw_fuel_gallery_is_file_video(string $url): bool
{
    return (bool) preg_match('/\.(mp4|webm|ogg)(?:\?|$)/i', $url);
}

function nw_fuel_gallery_title_placeholder(string $title, WP_Post $post): string
{
    if ($post->post_type === 'nw_gallery_item') {
        return __('Title shown under the image or video', 'nw-fuel');
    }

    return $title;
}

function nw_fuel_gallery_admin_assets(string $hook): void
{
    $screen = get_current_screen();
    if (! $screen || $screen->post_type !== 'nw_gallery_item') {
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

function nw_fuel_gallery_meta_boxes(): void
{
    remove_meta_box('slugdiv', 'nw_gallery_item', 'normal');
    add_meta_box(
        'nw-fuel-gallery-item',
        __('Media', 'nw-fuel'),
        'nw_fuel_gallery_item_box',
        'nw_gallery_item',
        'normal',
        'high'
    );
}

function nw_fuel_gallery_item_box(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_gallery_item', 'nw_fuel_gallery_item_nonce');
    $id        = (int) $post->ID;
    $type      = nw_fuel_gallery_item_type($id);
    $image_id  = nw_fuel_gallery_image_id($id);
    $image_url = $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, 'medium') : '';
    $video     = (string) get_post_meta($id, '_nw_gallery_video_url', true);
    ?>
    <p class="description"><?php esc_html_e('The title above is the only text shown on the Gallery page.', 'nw-fuel'); ?></p>
    <p>
      <strong><?php esc_html_e('Type', 'nw-fuel'); ?></strong><br>
      <?php foreach (nw_fuel_gallery_types() as $value => $label) : ?>
      <label style="margin-right:16px;">
        <input type="radio" name="nw_gallery_type" value="<?php echo esc_attr($value); ?>" <?php checked($type, $value); ?> data-nw-gallery-type>
        <?php echo esc_html($label); ?>
      </label>
      <?php endforeach; ?>
    </p>

    <div class="nw-admin-field nw-media-field" data-nw-gallery-panel="image"<?php echo $type === 'video' ? ' hidden' : ''; ?>>
      <label><?php esc_html_e('Image', 'nw-fuel'); ?></label>
      <img class="nw-media-field__preview<?php echo $image_url !== '' ? ' is-visible' : ''; ?>" src="<?php echo esc_url($image_url); ?>" alt="">
      <input type="hidden" name="nw_gallery_image_id" value="<?php echo esc_attr((string) $image_id); ?>">
      <div class="nw-media-field__controls">
        <button type="button" class="button" data-nw-media-id data-title="<?php esc_attr_e('Select gallery image', 'nw-fuel'); ?>"><?php esc_html_e('Select from Media Library', 'nw-fuel'); ?></button>
        <button type="button" class="button" data-nw-media-id-clear><?php esc_html_e('Clear', 'nw-fuel'); ?></button>
      </div>
    </div>

    <div class="nw-admin-field" data-nw-gallery-panel="video"<?php echo $type === 'image' ? ' hidden' : ''; ?>>
      <label for="nw-gallery-video-url"><?php esc_html_e('Video link', 'nw-fuel'); ?></label>
      <input class="widefat" type="url" id="nw-gallery-video-url" name="nw_gallery_video_url" value="<?php echo esc_attr($video); ?>" placeholder="https://www.youtube.com/watch?v=…">
      <p class="description"><?php esc_html_e('YouTube, Vimeo, or a direct .mp4 / .webm file URL.', 'nw-fuel'); ?></p>
    </div>
    <?php
}

function nw_fuel_save_gallery_item(int $post_id, WP_Post $post): void
{
    unset($post);
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! isset($_POST['nw_fuel_gallery_item_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_gallery_item_nonce'])), 'nw_fuel_gallery_item')) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    $type = sanitize_key((string) wp_unslash($_POST['nw_gallery_type'] ?? 'image'));
    if (! array_key_exists($type, nw_fuel_gallery_types())) {
        $type = 'image';
    }
    update_post_meta($post_id, '_nw_gallery_type', $type);
    update_post_meta($post_id, '_nw_gallery_image_id', absint($_POST['nw_gallery_image_id'] ?? 0));
    update_post_meta($post_id, '_nw_gallery_video_url', esc_url_raw(wp_unslash((string) ($_POST['nw_gallery_video_url'] ?? ''))));
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_gallery_columns(array $columns): array
{
    $out = [];
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ($key === 'title') {
            $out['nw_type']    = __('Type', 'nw-fuel');
            $out['nw_preview'] = __('Preview', 'nw-fuel');
        }
    }

    return $out;
}

function nw_fuel_gallery_column_content(string $column, int $post_id): void
{
    if ($column === 'nw_type') {
        echo esc_html(nw_fuel_gallery_types()[nw_fuel_gallery_item_type($post_id)]);
        return;
    }

    if ($column !== 'nw_preview') {
        return;
    }

    if (nw_fuel_gallery_item_type($post_id) === 'image') {
        $url = nw_fuel_gallery_image_url($post_id, 'thumbnail');
        if ($url !== '') {
            echo '<img src="' . esc_url($url) . '" alt="" style="width:56px;height:56px;object-fit:cover;">';
            return;
        }
        echo '—';
        return;
    }

    $video = nw_fuel_gallery_video_url($post_id);
    echo $video !== '' ? '<span class="description">' . esc_html($video) . '</span>' : '—';
}

function nw_fuel_redirect_gallery_singles(): void
{
    if (! is_post_type_archive('nw_gallery_item') && ! is_singular('nw_gallery_item')) {
        return;
    }

    if (! nw_fuel_user_can_see_gallery()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        include get_query_template('404');
        exit;
    }

    if (is_singular('nw_gallery_item')) {
        wp_safe_redirect(get_post_type_archive_link('nw_gallery_item') ?: home_url('/gallery/'), 301);
        exit;
    }
}

function nw_fuel_hide_gallery_admin_menu(): void
{
    if (! nw_fuel_user_can_see_gallery()) {
        remove_menu_page('edit.php?post_type=nw_gallery_item');
    }
}

function nw_fuel_block_gallery_admin_for_others(): void
{
    if (! is_admin() || nw_fuel_user_can_see_gallery()) {
        return;
    }

    $type = sanitize_key((string) ($_GET['post_type'] ?? ''));
    if ($type === 'nw_gallery_item') {
        wp_die(esc_html__('Gallery is not available yet.', 'nw-fuel'), '', ['response' => 403]);
    }

    $post_id = absint($_GET['post'] ?? 0);
    if ($post_id > 0 && get_post_type($post_id) === 'nw_gallery_item') {
        wp_die(esc_html__('Gallery is not available yet.', 'nw-fuel'), '', ['response' => 403]);
    }
}

function nw_fuel_hide_gallery_admin_bar(\WP_Admin_Bar $bar): void
{
    if (! nw_fuel_user_can_see_gallery()) {
        $bar->remove_node('new-nw_gallery_item');
    }
}

/**
 * @param array<int, WP_Post> $items
 * @return array<int, WP_Post>
 */
function nw_fuel_filter_gallery_nav_items(array $items, stdClass $args): array
{
    unset($args);
    if (nw_fuel_user_can_see_gallery()) {
        return $items;
    }

    $out = [];
    foreach ($items as $item) {
        if (! nw_fuel_nav_item_is_gallery($item)) {
            $out[] = $item;
        }
    }

    return $out;
}

function nw_fuel_maybe_flush_gallery_rewrites(): void
{
    if (get_option('nw_fuel_gallery_rewrites') === '1.8.22') {
        return;
    }
    flush_rewrite_rules(false);
    update_option('nw_fuel_gallery_rewrites', '1.8.22', false);
}

/**
 * Add Gallery to the assigned primary menu once, after Blog when possible.
 */
function nw_fuel_ensure_gallery_nav_item(): void
{
    if (nw_fuel_gallery_is_preview_only()) {
        return;
    }
    if (get_option('nw_fuel_gallery_nav_added') === '1') {
        return;
    }

    $locations = get_nav_menu_locations();
    $menu_id   = (int) ($locations['primary'] ?? 0);
    if ($menu_id < 1) {
        return;
    }

    $items = wp_get_nav_menu_items($menu_id);
    if (! is_array($items)) {
        $items = [];
    }

    foreach ($items as $item) {
        if (! $item instanceof WP_Post) {
            continue;
        }
        if (strcasecmp((string) $item->title, 'Gallery') === 0) {
            update_option('nw_fuel_gallery_nav_added', '1', false);
            return;
        }
        if (str_contains((string) $item->url, '/gallery')) {
            update_option('nw_fuel_gallery_nav_added', '1', false);
            return;
        }
        if (($item->type ?? '') === 'post_type_archive' && ($item->object ?? '') === 'nw_gallery_item') {
            update_option('nw_fuel_gallery_nav_added', '1', false);
            return;
        }
    }

    $position = 0;
    foreach ($items as $item) {
        if (! $item instanceof WP_Post || (int) $item->menu_item_parent !== 0) {
            continue;
        }
        if (strcasecmp((string) $item->title, 'Blog') === 0) {
            $position = (int) $item->menu_order + 1;
            break;
        }
    }

    $args = [
        'menu-item-title'  => __('Gallery', 'nw-fuel'),
        'menu-item-type'   => 'post_type_archive',
        'menu-item-object' => 'nw_gallery_item',
        'menu-item-status' => 'publish',
    ];
    if ($position > 0) {
        $args['menu-item-position'] = $position;
    }

    wp_update_nav_menu_item($menu_id, 0, $args);
    update_option('nw_fuel_gallery_nav_added', '1', false);
}

/**
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function nw_fuel_gallery_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type !== 'nw_gallery_item') {
        return $actions;
    }
    unset($actions['view'], $actions['inline hide-if-no-js']);

    return $actions;
}

/**
 * @param array<string, array<int, string>> $messages
 * @return array<string, array<int, string>>
 */
function nw_fuel_gallery_updated_messages(array $messages): array
{
    $messages['nw_gallery_item'] = [
        0  => '',
        1  => __('Gallery item updated.', 'nw-fuel'),
        4  => __('Gallery item updated.', 'nw-fuel'),
        6  => __('Gallery item published.', 'nw-fuel'),
        7  => __('Gallery item saved.', 'nw-fuel'),
    ];

    return $messages;
}
