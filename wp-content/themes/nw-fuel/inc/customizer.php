<?php
/**
 * Theme Customizer — global business + homepage content.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('customize_register', 'nw_fuel_customize_register');

/**
 * Register Customizer sections and settings.
 */
function nw_fuel_customize_register(WP_Customize_Manager $wp_customize): void
{
    $wp_customize->add_section('nw_fuel_business', [
        'title'    => __('NW Fuel Business Info', 'nw-fuel'),
        'priority' => 30,
    ]);

    $business_fields = [
        'phone'       => __('Phone', 'nw-fuel'),
        'fax'         => __('Fax', 'nw-fuel'),
        'email'       => __('Email', 'nw-fuel'),
        'hours'       => __('Hours', 'nw-fuel'),
        'description' => __('Footer Description', 'nw-fuel'),
    ];

    foreach ($business_fields as $key => $label) {
        $setting_id = "nw_fuel_business_{$key}";
        $wp_customize->add_setting($setting_id, [
            'default'           => '',
            'sanitize_callback' => $key === 'email' ? 'sanitize_email' : ($key === 'description' ? 'sanitize_textarea_field' : 'sanitize_text_field'),
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control($setting_id, [
            'label'   => $label,
            'section' => 'nw_fuel_business',
            'type'    => $key === 'description' ? 'textarea' : 'text',
        ]);
    }

    foreach (['street' => __('Street', 'nw-fuel'), 'city' => __('City', 'nw-fuel'), 'province' => __('Province', 'nw-fuel'), 'postalCode' => __('Postal Code', 'nw-fuel')] as $key => $label) {
        $setting_id = "nw_fuel_address_{$key}";
        $wp_customize->add_setting($setting_id, [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control($setting_id, [
            'label'   => $label,
            'section' => 'nw_fuel_business',
            'type'    => 'text',
        ]);
    }

    foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network) {
        $setting_id = "nw_fuel_social_{$network}";
        $wp_customize->add_setting($setting_id, [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp_customize->add_control($setting_id, [
            'label'   => sprintf(/* translators: %s: social network name */ __('Social: %s', 'nw-fuel'), ucfirst($network)),
            'section' => 'nw_fuel_business',
            'type'    => 'url',
        ]);
    }

    $wp_customize->add_section('nw_fuel_homepage', [
        'title'       => __('NW Fuel Homepage', 'nw-fuel'),
        'description' => __('Edit homepage hero and section copy without touching templates.', 'nw-fuel'),
        'priority'    => 31,
    ]);

    $wp_customize->add_section('nw_fuel_archives', [
        'title'       => __('NW Fuel Archives', 'nw-fuel'),
        'description' => __('Hero and intro copy for Services, Products, Technical Resources, Blog, and Gallery listing pages.', 'nw-fuel'),
        'priority'    => 32,
    ]);

    foreach (nw_fuel_archive_content_defaults() as $archive_key => $fields) {
        foreach ($fields as $field_key => $default) {
            $setting_id = "nw_fuel_archive_{$archive_key}_{$field_key}";
            $wp_customize->add_setting($setting_id, [
                'default'           => $default,
                'sanitize_callback' => strlen((string) $default) > 80 ? 'sanitize_textarea_field' : 'sanitize_text_field',
            ]);
            $wp_customize->add_control($setting_id, [
                'label'   => ucwords(str_replace('_', ' ', $archive_key . ' ' . $field_key)),
                'section' => 'nw_fuel_archives',
                'type'    => strlen((string) $default) > 80 ? 'textarea' : 'text',
            ]);
        }
    }
}

/**
 * Default homepage copy matching the static site.
 *
 * @return array<string, string>
 */
function nw_fuel_home_content_defaults(): array
{
    return [
        'hero_eyebrow'          => 'Bosch Authorized. Since 1968.',
        'hero_title'            => 'Diesel Injection Parts and Repair',
        'hero_desc'             => 'Injector testing, pump rebuilds, and OEM diesel parts for repair shops across Western Canada. Founded in 1968, now based in Surrey, BC.',
        'portal_parts_label'    => 'Parts Catalog',
        'portal_parts_title'    => 'OEM Diesel Parts',
        'portal_parts_cta'      => 'Browse Parts',
        'portal_services_label' => 'Our Services',
        'portal_services_title' => 'Injection Repair',
        'portal_services_cta'   => 'View Services',
        'hero_cta_primary'      => 'Request a Quote',
        'hero_cta_secondary'    => 'Call Us',
        'parts_title'           => 'Parts in Stock',
        'parts_text'            => 'Common rail injectors, pumps, turbos, and hard parts from Bosch, Denso, Delphi, and more. These are the lines we keep on the shelf for BC repair shops.',
        'parts_cta'             => 'View all parts',
        'services_title'        => 'NW Fuel Services',
        'services_link'         => 'See all services',
        'brands_title'          => 'Brands We Carry',
        'brands_link'           => 'Browse by brand',
        'duo_shop_label'        => 'Our Shop',
        'duo_shop_title'        => 'Injector and Pump Workshop',
        'duo_shop_text'         => 'Injector testing, pump rebuilds, turbo work, and OEM parts under one roof in Surrey. We work with diesel repair shops every day.',
        'duo_promise_label'     => 'Our Promise',
        'duo_promise_title'     => 'Fix What Actually Needs Work',
        'duo_promise_text'      => 'Solid diagnostics mean you replace the parts that are worn, not everything in the assembly. Rebuilds include a 12 month unlimited kilometre warranty.',
        'banner_eyebrow'        => 'Beyond the Catalog',
        'banner_title'          => 'We Fix It. We Build It.',
        'banner_text'           => 'Cannot find the exact part or service you need? Our Surrey shop repairs, rebuilds, and fabricates diesel injection components that do not always fit a standard catalog line. Tell us what you are working on and we will find a path forward.',
        'banner_cta'            => 'Request a Quote',
        'more_parts_title'      => 'More Diesel Parts',
        'more_parts_text'       => 'Injectors, pumps, turbos, and component parts for Cummins, Powerstroke, and Duramax applications. Browse the catalog or call the shop when you need parts today.',
        'blog_title'            => 'From Our Blog',
        'blog_link'             => 'Read the blog',
        'newsletter_title'      => 'Email Updates from the Shop',
        'newsletter_sub'        => 'Technical notes, product news, and shop announcements. No junk mail.',
        'newsletter_cta'        => 'Subscribe',
        'trust_1_title'         => 'Talk to a Technician',
        'trust_1_text'          => 'Real diesel injection staff before and after you buy. Not a call centre.',
        'trust_2_title'         => 'Bosch Authorized',
        'trust_2_text'          => 'Parts and shop work held to OEM standards.',
        'trust_3_title'         => '50+ Years in Diesel Injection',
        'trust_3_text'          => 'Founded in New Westminster in 1968, now serving repairers from Surrey, BC.',
    ];
}

/**
 * Resolved homepage content (Customizer → settings → defaults).
 *
 * @return array<string, string>
 */
function nw_fuel_home_content(): array
{
    $defaults = nw_fuel_home_content_defaults();
    $settings = nw_fuel_get_settings();
    $from_settings = is_array($settings['home']['content'] ?? null) ? $settings['home']['content'] : [];
    $merged = array_merge($defaults, $from_settings);

    foreach (array_keys($defaults) as $key) {
        $mod = get_theme_mod("nw_fuel_home_{$key}", null);
        if (is_string($mod) && $mod !== '') {
            $merged[$key] = $mod;
        }
    }

    return $merged;
}
