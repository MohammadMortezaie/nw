<?php
/**
 * Theme settings admin page.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'nw_fuel_register_settings_page');
add_action('admin_init', 'nw_fuel_register_settings');

/**
 * Add settings page under Appearance.
 */
function nw_fuel_register_settings_page(): void
{
    add_theme_page(
        __('NW Fuel Settings', 'nw-fuel'),
        __('NW Fuel Settings', 'nw-fuel'),
        'manage_options',
        'nw-fuel-settings',
        'nw_fuel_render_settings_page'
    );
}

/**
 * Register settings.
 */
function nw_fuel_register_settings(): void
{
    register_setting('nw_fuel_settings_group', 'nw_fuel_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'nw_fuel_sanitize_settings',
        'default'           => [],
    ]);
}

/**
 * Sanitize settings array.
 *
 * @param mixed $input Raw input.
 * @return array<string, mixed>
 */
function nw_fuel_sanitize_settings(mixed $input): array
{
    if (! is_array($input)) {
        return nw_fuel_get_settings();
    }

    $clean = nw_fuel_get_settings();

    if (isset($input['business']) && is_array($input['business'])) {
        $b = $input['business'];
        $clean['business']['phone'] = sanitize_text_field($b['phone'] ?? '');
        $clean['business']['fax'] = sanitize_text_field($b['fax'] ?? '');
        $clean['business']['email'] = sanitize_email($b['email'] ?? '');
        $clean['business']['hours'] = sanitize_text_field($b['hours'] ?? '');
        $clean['business']['description'] = sanitize_textarea_field($b['description'] ?? '');
        if (isset($b['address']) && is_array($b['address'])) {
            $clean['business']['address']['street'] = sanitize_text_field($b['address']['street'] ?? '');
            $clean['business']['address']['city'] = sanitize_text_field($b['address']['city'] ?? '');
            $clean['business']['address']['province'] = sanitize_text_field($b['address']['province'] ?? '');
            $clean['business']['address']['postalCode'] = sanitize_text_field($b['address']['postalCode'] ?? '');
        }
        if (isset($b['social']) && is_array($b['social'])) {
            $social = [];
            foreach ($b['social'] as $key => $url) {
                $social[sanitize_key((string) $key)] = esc_url_raw((string) $url);
            }
            $clean['business']['social'] = $social;
        }
    }

    foreach (['images', 'company', 'home_faqs', 'contact_faqs'] as $json_key) {
        if (! isset($input[$json_key])) {
            continue;
        }
        $raw = is_string($input[$json_key]) ? trim($input[$json_key]) : '';
        if ($raw === '') {
            $clean[$json_key] = [];
            continue;
        }
        $decoded = json_decode(wp_unslash($raw), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $clean[$json_key] = $decoded;
        }
    }

    if (isset($input['home']['featured_service_slugs']) && is_string($input['home']['featured_service_slugs'])) {
        $slugs = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $input['home']['featured_service_slugs']))));
        $clean['home']['featured_service_slugs'] = array_values($slugs);
    }

    return $clean;
}

/**
 * Render settings page.
 */
function nw_fuel_render_settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $settings = nw_fuel_get_settings();
    $business = $settings['business'];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('NW Fuel Theme Settings', 'nw-fuel'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('nw_fuel_settings_group'); ?>
            <h2><?php esc_html_e('Business Contact', 'nw-fuel'); ?></h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="nw-phone"><?php esc_html_e('Phone', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][phone]" id="nw-phone" class="regular-text" value="<?php echo esc_attr($business['phone']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-email"><?php esc_html_e('Email', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][email]" id="nw-email" type="email" class="regular-text" value="<?php echo esc_attr($business['email']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-hours"><?php esc_html_e('Hours', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][hours]" id="nw-hours" class="regular-text" value="<?php echo esc_attr($business['hours']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-street"><?php esc_html_e('Street', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][address][street]" id="nw-street" class="regular-text" value="<?php echo esc_attr($business['address']['street']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-city"><?php esc_html_e('City', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][address][city]" id="nw-city" class="regular-text" value="<?php echo esc_attr($business['address']['city']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-province"><?php esc_html_e('Province', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][address][province]" id="nw-province" class="regular-text" value="<?php echo esc_attr($business['address']['province']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-postal"><?php esc_html_e('Postal Code', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[business][address][postalCode]" id="nw-postal" class="regular-text" value="<?php echo esc_attr($business['address']['postalCode']); ?>"></td></tr>
                <tr><th scope="row"><label for="nw-description"><?php esc_html_e('Footer Description', 'nw-fuel'); ?></label></th>
                <td><textarea name="nw_fuel_settings[business][description]" id="nw-description" class="large-text" rows="3"><?php echo esc_textarea($business['description']); ?></textarea></td></tr>
            </table>

            <h2><?php esc_html_e('Social Links', 'nw-fuel'); ?></h2>
            <table class="form-table" role="presentation">
                <?php foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network) : ?>
                <tr><th scope="row"><label for="nw-social-<?php echo esc_attr($network); ?>"><?php echo esc_html(ucfirst($network)); ?></label></th>
                <td><input name="nw_fuel_settings[business][social][<?php echo esc_attr($network); ?>]" id="nw-social-<?php echo esc_attr($network); ?>" type="url" class="regular-text" value="<?php echo esc_url($business['social'][$network] ?? ''); ?>"></td></tr>
                <?php endforeach; ?>
            </table>

            <h2><?php esc_html_e('Structured JSON Settings', 'nw-fuel'); ?></h2>
            <p class="description"><?php esc_html_e('Paste valid JSON exported from seed data or edit carefully. Used for images map, About page content, and FAQs.', 'nw-fuel'); ?></p>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="nw-images-json"><?php esc_html_e('Images Map (JSON)', 'nw-fuel'); ?></label></th>
                <td><textarea name="nw_fuel_settings[images]" id="nw-images-json" class="large-text code" rows="8"><?php echo esc_textarea(wp_json_encode($settings['images'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></td></tr>
                <tr><th scope="row"><label for="nw-company-json"><?php esc_html_e('About / Company Data (JSON)', 'nw-fuel'); ?></label></th>
                <td><textarea name="nw_fuel_settings[company]" id="nw-company-json" class="large-text code" rows="10"><?php echo esc_textarea(wp_json_encode($settings['company'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></td></tr>
                <tr><th scope="row"><label for="nw-home-faqs-json"><?php esc_html_e('Home FAQs (JSON array)', 'nw-fuel'); ?></label></th>
                <td><textarea name="nw_fuel_settings[home_faqs]" id="nw-home-faqs-json" class="large-text code" rows="6"><?php echo esc_textarea(wp_json_encode($settings['home_faqs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></td></tr>
                <tr><th scope="row"><label for="nw-contact-faqs-json"><?php esc_html_e('Contact FAQs (JSON array)', 'nw-fuel'); ?></label></th>
                <td><textarea name="nw_fuel_settings[contact_faqs]" id="nw-contact-faqs-json" class="large-text code" rows="6"><?php echo esc_textarea(wp_json_encode($settings['contact_faqs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></td></tr>
                <tr><th scope="row"><label for="nw-featured-services"><?php esc_html_e('Home Featured Service Slugs', 'nw-fuel'); ?></label></th>
                <td><input name="nw_fuel_settings[home][featured_service_slugs]" id="nw-featured-services" class="large-text" value="<?php echo esc_attr(implode(', ', $settings['home']['featured_service_slugs'] ?? [])); ?>">
                <p class="description"><?php esc_html_e('Comma-separated service slugs for the homepage grid.', 'nw-fuel'); ?></p></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
