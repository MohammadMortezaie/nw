<?php
/**
 * Theme setup, menus, and activation tasks.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'nw_fuel_setup');
add_action('after_switch_theme', 'nw_fuel_after_switch_theme');
add_action('admin_notices', 'nw_fuel_woocommerce_admin_notice');
add_action('wp_head', 'nw_fuel_output_favicons', 1);

/**
 * Favicons matching the static site head.php links.
 */
function nw_fuel_output_favicons(): void
{
    $ico  = NW_FUEL_URI . '/favicon.ico';
    $png32 = nw_fuel_asset_url('assets/img/favicon-32x32.png');
    $apple = nw_fuel_asset_url('assets/img/apple-touch-icon.png');
    echo '<link rel="icon" href="' . esc_url($ico) . '" sizes="any">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($png32) . '">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url($apple) . '">' . "\n";
}

/**
 * Register theme supports and menus.
 */
function nw_fuel_setup(): void
{
    load_theme_textdomain('nw-fuel', NW_FUEL_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 105,
        'width'       => 320,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'nw-fuel'),
        'footer'  => __('Footer Navigation', 'nw-fuel'),
    ]);

    add_theme_support('woocommerce');
}

add_action('widgets_init', 'nw_fuel_widgets_init');

/**
 * Register widget areas (footer / optional sidebars).
 */
function nw_fuel_widgets_init(): void
{
    register_sidebar([
        'name'          => __('Footer Widgets', 'nw-fuel'),
        'id'            => 'footer-widgets',
        'description'   => __('Optional widgets below the main footer columns.', 'nw-fuel'),
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<p class="footer-widget__title">',
        'after_title'   => '</p>',
    ]);
}

/**
 * Flush rewrite rules and ensure baseline options after activation.
 */
function nw_fuel_after_switch_theme(): void
{
    nw_fuel_register_post_types();
    nw_fuel_register_product_attributes();
    flush_rewrite_rules();
}

/**
 * Show admin notice when WooCommerce is missing.
 */
function nw_fuel_woocommerce_admin_notice(): void
{
    if (nw_fuel_is_woocommerce_active()) {
        return;
    }

    if (! current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('NW Fuel theme requires WooCommerce for the products catalog. Install and activate WooCommerce to enable product management.', 'nw-fuel');
    echo '</p></div>';
}
