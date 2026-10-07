<?php
/**
 * Admin meta boxes for products, services, and technical resources.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'nw_fuel_register_meta_boxes');
add_action('add_meta_boxes', 'nw_fuel_remove_product_image_boxes', 40);
add_action('edit_form_after_title', 'nw_fuel_service_editor_intro');
add_action('save_post_product', 'nw_fuel_save_product_meta', 10, 2);
add_action('save_post_product', 'nw_fuel_save_product_photos_late', 99, 2);
add_action('save_post_product', 'nw_fuel_save_product_brand_late', 99, 2);
add_action('woocommerce_process_product_meta', 'nw_fuel_save_product_photos_from_wc', 99);
add_action('woocommerce_process_product_meta', 'nw_fuel_save_product_brand_from_wc', 99);
add_action('save_post_nw_service', 'nw_fuel_save_service_meta', 10, 2);
add_action('save_post_nw_tech_resource', 'nw_fuel_save_technical_resource_meta', 10, 2);
add_action('save_post_page', 'nw_fuel_save_page_seo_meta', 10, 2);
add_action('save_post_post', 'nw_fuel_save_page_seo_meta', 10, 2);
add_action('save_post_page', 'nw_fuel_save_page_content_meta', 20, 2);

/**
 * Intro banner under the title so admins know fields are below.
 */
function nw_fuel_service_editor_intro(WP_Post $post): void
{
    if ($post->post_type === 'nw_service') {
        ?>
        <div class="notice notice-info inline" style="margin:12px 0 0;padding:12px 16px;">
          <p style="margin:0;font-size:14px;">
            <strong><?php esc_html_e('Service page builder', 'nw-fuel'); ?></strong>
            <?php esc_html_e('Fill every section in the boxes below (Short Description, Overview, Benefits, Detail Sections, Pricing, FAQs, etc.). These map 1:1 to the public service detail page — not a simple text page.', 'nw-fuel'); ?>
          </p>
        </div>
        <?php
        return;
    }

    if ($post->post_type === 'nw_tech_resource') {
        ?>
        <div class="notice notice-info inline" style="margin:12px 0 0;padding:12px 16px;">
          <p style="margin:0;font-size:14px;">
            <strong><?php esc_html_e('Technical page builder', 'nw-fuel'); ?></strong>
            <?php esc_html_e('Use the fields below for excerpt, sections, application table, and downloads. These map 1:1 to the public technical guide page.', 'nw-fuel'); ?>
          </p>
        </div>
        <?php
    }
}

/**
 * Hide WooCommerce's single featured-image / unlimited gallery boxes.
 * Photos are managed in the NW Fuel 1–5 picker instead.
 */
function nw_fuel_remove_product_image_boxes(): void
{
    remove_meta_box('postimagediv', 'product', 'side');
    remove_meta_box('woocommerce-product-images', 'product', 'side');
}

/**
 * Re-apply photos after WooCommerce product meta save.
 */
function nw_fuel_save_product_photos_late(int $post_id, WP_Post $post): void
{
    unset($post);
    if (! isset($_POST['nw_fuel_product_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_product_meta_nonce'])), 'nw_fuel_product_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }
    if (function_exists('nw_fuel_save_product_photos')) {
        nw_fuel_save_product_photos($post_id);
    }
}

/**
 * Re-apply brand after WooCommerce product meta save.
 */
function nw_fuel_save_product_brand_late(int $post_id, WP_Post $post): void
{
    unset($post);
    if (! isset($_POST['nw_fuel_product_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_product_meta_nonce'])), 'nw_fuel_product_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }
    if (function_exists('nw_fuel_save_product_brand')) {
        nw_fuel_save_product_brand($post_id);
    }
}

/**
 * WooCommerce product-data save also re-applies the brand picker.
 */
function nw_fuel_save_product_brand_from_wc(int $post_id): void
{
    if (! isset($_POST['nw_fuel_product_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_product_meta_nonce'])), 'nw_fuel_product_meta')) {
        return;
    }
    if (function_exists('nw_fuel_save_product_brand')) {
        nw_fuel_save_product_brand($post_id);
    }
}

/**
 * WooCommerce product-data save also re-applies the 1–5 photo picker.
 */
function nw_fuel_save_product_photos_from_wc(int $post_id): void
{
    if (! isset($_POST['nw_fuel_product_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_product_meta_nonce'])), 'nw_fuel_product_meta')) {
        return;
    }
    if (function_exists('nw_fuel_save_product_photos')) {
        nw_fuel_save_product_photos($post_id);
    }
}

/**
 * Register meta boxes.
 */
function nw_fuel_register_meta_boxes(): void
{
    add_meta_box(
        'nw-fuel-product-details',
        __('1. NW Fuel Product Details — all fields', 'nw-fuel'),
        'nw_fuel_product_meta_box_render',
        'product',
        'normal',
        'high'
    );

    // One primary box with all service page inputs (classic editor screen).
    add_meta_box(
        'nw-fuel-service-page',
        __('1. Service Page Content — all fields', 'nw-fuel'),
        'nw_fuel_service_meta_box_render',
        'nw_service',
        'normal',
        'high'
    );

    add_meta_box(
        'nw-fuel-tech-resource-details',
        __('1. Technical Page Content — all fields', 'nw-fuel'),
        'nw_fuel_technical_resource_meta_box_render',
        'nw_tech_resource',
        'normal',
        'high'
    );

    add_meta_box(
        'nw-fuel-page-seo',
        __('NW Fuel SEO', 'nw-fuel'),
        'nw_fuel_page_seo_meta_box_render',
        ['page', 'post'],
        'side',
        'default'
    );

    $screen_post = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    $editing     = $screen_post ? get_post($screen_post) : null;
    $is_about    = false;
    $is_contact  = false;
    $is_history  = false;
    $is_home     = false;
    if ($editing instanceof WP_Post) {
        $tpl = get_page_template_slug($editing->ID);
        $is_about   = $editing->post_name === 'about' || $tpl === 'page-about.php';
        $is_contact = $editing->post_name === 'contact' || $tpl === 'page-contact.php';
        $is_history = $editing->post_name === 'history-of-nw-fuel' || $tpl === 'page-history.php';
        $is_home    = $editing->post_name === 'home' || (int) get_option('page_on_front') === $editing->ID;
    }
    if ($is_about) {
        add_meta_box(
            'nw-fuel-about-content',
            __('1. About Page Content — all fields', 'nw-fuel'),
            'nw_fuel_about_meta_box_render',
            'page',
            'normal',
            'high'
        );
    }
    if ($is_contact) {
        add_meta_box(
            'nw-fuel-contact-content',
            __('1. Contact Page Content — all fields', 'nw-fuel'),
            'nw_fuel_contact_meta_box_render',
            'page',
            'normal',
            'high'
        );
    }
    if ($is_history) {
        add_meta_box(
            'nw-fuel-history-content',
            __('1. History Page Content — hero & CTA', 'nw-fuel'),
            'nw_fuel_history_meta_box_render',
            'page',
            'normal',
            'high'
        );
    }
    if ($is_home) {
        add_meta_box(
            'nw-fuel-home-guide',
            __('How to edit the Homepage', 'nw-fuel'),
            'nw_fuel_home_guide_meta_box_render',
            'page',
            'normal',
            'high'
        );
    }
}

/**
 * Product meta box fields (CRUD UI).
 */
function nw_fuel_product_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_product_admin_ui($post);
}

/**
 * Service meta box fields (CRUD UI).
 */
function nw_fuel_service_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_service_admin_ui($post);
}

/**
 * Technical resource meta box (CRUD UI).
 */
function nw_fuel_technical_resource_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_tech_admin_ui($post);
}

/**
 * About page content meta box.
 */
function nw_fuel_about_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_about_admin_ui($post);
}

/**
 * Contact page content meta box.
 */
function nw_fuel_contact_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_contact_admin_ui($post);
}

/**
 * History page content meta box.
 */
function nw_fuel_history_meta_box_render(WP_Post $post): void
{
    nw_fuel_render_history_admin_ui($post);
}

/**
 * Home page guide (copy lives in Customizer).
 */
function nw_fuel_home_guide_meta_box_render(WP_Post $post): void
{
    unset($post);
    $url = admin_url('customize.php?autofocus[section]=nw_fuel_homepage');
    ?>
    <div class="notice notice-info inline" style="margin:0;padding:12px 16px;">
      <p style="margin:0 0 8px;"><strong><?php esc_html_e('Homepage words are edited in the Customizer', 'nw-fuel'); ?></strong></p>
      <p style="margin:0 0 12px;"><?php esc_html_e('Hero, parts, services, brands, blog, newsletter, and trust copy → Appearance → Customize → NW Fuel Homepage.', 'nw-fuel'); ?></p>
      <p style="margin:0;"><a class="button button-primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Open Homepage Customizer', 'nw-fuel'); ?></a>
      <a class="button" href="<?php echo esc_url(admin_url('themes.php?page=nw-fuel-content-guide')); ?>"><?php esc_html_e('Full content map', 'nw-fuel'); ?></a></p>
    </div>
    <?php
}

/**
 * Save About / Contact structured content.
 */
function nw_fuel_save_page_content_meta(int $post_id, WP_Post $post): void
{
    if (! isset($_POST['nw_fuel_page_content_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_page_content_nonce'])), 'nw_fuel_page_content')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if ($post->post_name === 'about' || get_page_template_slug($post_id) === 'page-about.php') {
        nw_fuel_save_about_admin_fields($post_id);
    }
    if ($post->post_name === 'contact' || get_page_template_slug($post_id) === 'page-contact.php') {
        nw_fuel_save_contact_admin_fields($post_id);
    }
    if ($post->post_name === 'history-of-nw-fuel' || get_page_template_slug($post_id) === 'page-history.php') {
        nw_fuel_save_history_admin_fields($post_id);
    }
}

/**
 * SEO fields for pages and blog posts.
 */
function nw_fuel_page_seo_meta_box_render(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_page_seo_meta', 'nw_fuel_page_seo_meta_nonce');
    $title = (string) get_post_meta($post->ID, '_nw_meta_title', true);
    $desc  = (string) get_post_meta($post->ID, '_nw_meta_description', true);
    ?>
    <p>
      <label for="nw-page-meta-title"><strong><?php esc_html_e('SEO Title', 'nw-fuel'); ?></strong></label><br>
      <input type="text" class="widefat" id="nw-page-meta-title" name="_nw_meta_title" value="<?php echo esc_attr($title); ?>">
    </p>
    <p>
      <label for="nw-page-meta-description"><strong><?php esc_html_e('SEO Description', 'nw-fuel'); ?></strong></label><br>
      <textarea class="widefat" rows="3" id="nw-page-meta-description" name="_nw_meta_description"><?php echo esc_textarea($desc); ?></textarea>
    </p>
    <?php
}

/**
 * Save page/post SEO meta.
 */
function nw_fuel_save_page_seo_meta(int $post_id, WP_Post $post): void
{
    if (! isset($_POST['nw_fuel_page_seo_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_page_seo_meta_nonce'])), 'nw_fuel_page_seo_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_nw_meta_title', sanitize_text_field(wp_unslash($_POST['_nw_meta_title'] ?? '')));
    update_post_meta($post_id, '_nw_meta_description', sanitize_textarea_field(wp_unslash($_POST['_nw_meta_description'] ?? '')));
}

/**
 * Save product meta from CRUD form fields.
 */
function nw_fuel_save_product_meta(int $post_id, WP_Post $post): void
{
    if (! isset($_POST['nw_fuel_product_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_product_meta_nonce'])), 'nw_fuel_product_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    nw_fuel_save_product_admin_fields($post_id);
}

/**
 * Save service meta from CRUD form fields.
 */
function nw_fuel_save_service_meta(int $post_id, WP_Post $post): void
{
    if (! isset($_POST['nw_fuel_service_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_service_meta_nonce'])), 'nw_fuel_service_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_nw_icon', sanitize_text_field(wp_unslash($_POST['_nw_icon'] ?? '')));
    update_post_meta($post_id, '_nw_hero_image', esc_url_raw(wp_unslash($_POST['_nw_hero_image'] ?? '')));
    update_post_meta($post_id, '_nw_meta_title', sanitize_text_field(wp_unslash($_POST['_nw_meta_title'] ?? '')));
    update_post_meta($post_id, '_nw_meta_description', sanitize_textarea_field(wp_unslash($_POST['_nw_meta_description'] ?? '')));

    // Short description + overview → post excerpt / content (no block editor).
    $short    = sanitize_textarea_field(wp_unslash($_POST['nw_service_short_description'] ?? ''));
    $overview = sanitize_textarea_field(wp_unslash($_POST['nw_service_overview'] ?? ''));
    remove_action('save_post_nw_service', 'nw_fuel_save_service_meta', 10);
    wp_update_post([
        'ID'           => $post_id,
        'post_excerpt' => $short,
        'post_content' => $overview,
    ]);
    add_action('save_post_nw_service', 'nw_fuel_save_service_meta', 10, 2);

    // Benefits: simple list.
    $benefits_raw = wp_unslash($_POST['nw_benefits'] ?? []);
    $benefits     = [];
    if (is_array($benefits_raw)) {
        foreach ($benefits_raw as $benefit) {
            $benefit = sanitize_text_field((string) $benefit);
            if ($benefit !== '') {
                $benefits[] = $benefit;
            }
        }
    }
    update_post_meta($post_id, '_nw_benefits', nw_fuel_json_encode_meta($benefits));

    // Detail sections.
    $sections = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_detail_sections'] ?? []),
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $text  = sanitize_textarea_field((string) ($row['text'] ?? ''));
            $items = nw_fuel_lines_to_list($row['items'] ?? '');
            $src   = esc_url_raw((string) ($row['image_src'] ?? ''));
            $alt   = sanitize_text_field((string) ($row['image_alt'] ?? ''));
            $label = sanitize_text_field((string) ($row['link_label'] ?? ''));
            $href  = sanitize_text_field((string) ($row['link_href'] ?? ''));

            if ($title === '' && $text === '' && $items === [] && $src === '') {
                return null;
            }

            $out = [
                'eyebrow' => sanitize_text_field((string) ($row['eyebrow'] ?? '')),
                'title'   => $title,
                'text'    => $text,
                'items'   => $items,
            ];
            if ($src !== '') {
                $out['image'] = ['src' => $src, 'alt' => $alt];
            }
            if ($label !== '' && $href !== '') {
                $out['link'] = ['label' => $label, 'href' => $href];
            }

            return $out;
        }
    );
    update_post_meta($post_id, '_nw_detail_sections', nw_fuel_json_encode_meta($sections));

    // Feature cards.
    $cards = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_feature_cards'] ?? []),
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $text  = sanitize_textarea_field((string) ($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                return null;
            }
            return ['title' => $title, 'text' => $text];
        }
    );
    update_post_meta($post_id, '_nw_feature_cards', nw_fuel_json_encode_meta($cards));

    // Video section.
    $video_raw = wp_unslash($_POST['nw_video'] ?? []);
    $video     = [];
    if (is_array($video_raw)) {
        $embed = esc_url_raw((string) ($video_raw['embedUrl'] ?? ''));
        if ($embed !== '') {
            $video = [
                'eyebrow'    => sanitize_text_field((string) ($video_raw['eyebrow'] ?? '')),
                'title'      => sanitize_text_field((string) ($video_raw['title'] ?? '')),
                'text'       => sanitize_textarea_field((string) ($video_raw['text'] ?? '')),
                'embedUrl'   => $embed,
                'videoTitle' => sanitize_text_field((string) ($video_raw['videoTitle'] ?? '')),
                'items'      => nw_fuel_lines_to_list($video_raw['items'] ?? ''),
            ];
        }
    }
    update_post_meta($post_id, '_nw_video_section', nw_fuel_json_encode_meta($video !== [] ? $video : new stdClass()));

    // Pricing.
    $pricing = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_pricing'] ?? []),
        static function (array $row): ?array {
            $item = sanitize_text_field((string) ($row['item'] ?? ''));
            if ($item === '') {
                return null;
            }
            $out = [
                'item'      => $item,
                'price'     => sanitize_text_field((string) ($row['price'] ?? '')),
                'timeframe' => sanitize_text_field((string) ($row['timeframe'] ?? '')),
            ];
            $note = sanitize_text_field((string) ($row['note'] ?? ''));
            if ($note !== '') {
                $out['note'] = $note;
            }
            return $out;
        }
    );
    update_post_meta($post_id, '_nw_pricing', nw_fuel_json_encode_meta($pricing));

    // Process.
    $process = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_process'] ?? []),
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $desc  = sanitize_textarea_field((string) ($row['description'] ?? ''));
            if ($title === '' && $desc === '') {
                return null;
            }
            $step = $row['step'] ?? '';
            $step_num = is_numeric($step) ? (int) $step : sanitize_text_field((string) $step);
            return [
                'step'        => $step_num,
                'title'       => $title,
                'description' => $desc,
            ];
        }
    );
    update_post_meta($post_id, '_nw_process', nw_fuel_json_encode_meta($process));

    // FAQs.
    $faqs = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_faqs'] ?? []),
        static function (array $row): ?array {
            $question = sanitize_text_field((string) ($row['question'] ?? ''));
            $answer   = sanitize_textarea_field((string) ($row['answer'] ?? ''));
            if ($question === '' && $answer === '') {
                return null;
            }
            return ['question' => $question, 'answer' => $answer];
        }
    );
    update_post_meta($post_id, '_nw_service_faqs', nw_fuel_json_encode_meta($faqs));

    // Related services.
    $related_raw = wp_unslash($_POST['nw_related_slugs'] ?? []);
    $related     = [];
    if (is_array($related_raw)) {
        foreach ($related_raw as $slug) {
            $slug = sanitize_title((string) $slug);
            if ($slug !== '') {
                $related[] = $slug;
            }
        }
    }
    update_post_meta($post_id, '_nw_related_slugs', nw_fuel_json_encode_meta(array_values(array_unique($related))));
}

/**
 * Save technical resource meta from CRUD form fields.
 */
function nw_fuel_save_technical_resource_meta(int $post_id, WP_Post $post): void
{
    if (! isset($_POST['nw_fuel_technical_resource_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_technical_resource_meta_nonce'])), 'nw_fuel_technical_resource_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    nw_fuel_save_tech_admin_fields($post_id);
}

/**
 * Validate and save JSON textarea meta.
 */
function nw_fuel_save_json_meta(int $post_id, string $key, mixed $raw): void
{
    $value = is_string($raw) ? trim($raw) : '';
    if ($value === '') {
        update_post_meta($post_id, $key, '[]');
        return;
    }

    $decoded = json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
        return;
    }

    update_post_meta($post_id, $key, nw_fuel_json_encode_meta($decoded));
}
