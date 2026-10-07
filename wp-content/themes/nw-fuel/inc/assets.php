<?php
/**
 * Asset enqueueing.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'nw_fuel_dequeue_conflicting_styles', 100);
add_action('wp_enqueue_scripts', 'nw_fuel_enqueue_assets', 20);

/**
 * Remove WordPress / WooCommerce styles that override the custom design.
 */
function nw_fuel_dequeue_conflicting_styles(): void
{
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style');
    wp_dequeue_style('wc-blocks-vendors-style');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
}

/**
 * Enqueue theme styles and scripts.
 */
function nw_fuel_enqueue_assets(): void
{
    wp_enqueue_style(
        'nw-fuel-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        [],
        null
    );

    wp_enqueue_style('nw-fuel-theme', get_stylesheet_uri(), ['nw-fuel-fonts'], NW_FUEL_VERSION);
    wp_enqueue_style('nw-fuel-main', NW_FUEL_URI . '/assets/css/main.css', ['nw-fuel-theme'], NW_FUEL_VERSION);

    $page_style = nw_fuel_current_page_stylesheet();
    if ($page_style === 'history.css') {
        wp_enqueue_style(
            'nw-fuel-about',
            NW_FUEL_URI . '/assets/css/about.css',
            ['nw-fuel-main'],
            NW_FUEL_VERSION
        );
        wp_enqueue_style(
            'nw-fuel-page',
            NW_FUEL_URI . '/assets/css/history.css',
            ['nw-fuel-about'],
            NW_FUEL_VERSION
        );
    } elseif ($page_style !== '') {
        wp_enqueue_style(
            'nw-fuel-page',
            NW_FUEL_URI . '/assets/css/' . $page_style,
            ['nw-fuel-main'],
            NW_FUEL_VERSION
        );
    }

    wp_enqueue_script('nw-fuel-main', NW_FUEL_URI . '/assets/js/main.js', [], NW_FUEL_VERSION, true);

    wp_localize_script('nw-fuel-main', 'nwFuel', [
        'homeUrl'     => home_url('/'),
        'productsUrl' => nw_fuel_products_url(),
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'contactUrl'  => nw_fuel_page_url('contact'),
    ]);
}

/**
 * Resolve the page-specific stylesheet for the current request.
 */
function nw_fuel_current_page_stylesheet(): string
{
    if (is_front_page()) {
        return 'home.css';
    }

    if (is_page('about') || is_page_template('page-about.php')) {
        return 'about.css';
    }

    if (is_page('history-of-nw-fuel') || is_page_template('page-history.php')) {
        return 'history.css';
    }

    if (is_page('contact') || is_page_template('page-contact.php')) {
        return 'contact.css';
    }

    if (nw_fuel_is_product_context()) {
        return 'products.css';
    }

    if (is_post_type_archive('nw_service') || is_singular('nw_service')) {
        return 'services.css';
    }

    if (is_home() || is_singular('post') || is_category() || is_tag() || is_date() || is_author()) {
        return 'blog.css';
    }

    if (is_post_type_archive('nw_tech_resource') || is_singular('nw_tech_resource')) {
        return 'technical-resources.css';
    }

    if (is_post_type_archive('nw_gallery_item') || is_singular('nw_gallery_item')) {
        return 'gallery.css';
    }

    if (is_post_type_archive('nw_catalog_item') || is_singular('nw_catalog_item')) {
        return 'catalog.css';
    }

    return '';
}

/**
 * Whether the current request is part of the product catalog.
 */
function nw_fuel_is_product_context(): bool
{
    if (function_exists('is_shop') && is_shop()) {
        return true;
    }

    if (function_exists('is_product') && is_product()) {
        return true;
    }

    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        return true;
    }

    return is_singular('product') || is_post_type_archive('product');
}

add_filter('body_class', 'nw_fuel_body_class');

/**
 * Add page-specific body classes matching the legacy site.
 */
function nw_fuel_body_class(array $classes): array
{
    if (is_front_page()) {
        $classes[] = 'page-home';
    }

    if (is_page('about') || is_page_template('page-about.php')) {
        $classes[] = 'page-about';
    }

    if (is_page('history-of-nw-fuel') || is_page_template('page-history.php')) {
        $classes[] = 'page-about';
        $classes[] = 'page-history';
    }

    if (is_page('contact') || is_page_template('page-contact.php')) {
        $classes[] = 'page-contact';
    }

    if (is_post_type_archive('nw_service') || is_singular('nw_service')) {
        $classes[] = 'page-services';
    }

    if (is_home() || is_singular('post') || is_category() || is_tag()) {
        $classes[] = 'page-blog';
    }

    if (is_post_type_archive('nw_tech_resource') || is_singular('nw_tech_resource')) {
        $classes[] = 'page-tech-resources';
    }

    if (is_post_type_archive('nw_gallery_item') || is_singular('nw_gallery_item')) {
        $classes[] = 'page-gallery';
    }

    if (is_post_type_archive('nw_catalog_item') || is_singular('nw_catalog_item')) {
        $classes[] = 'page-catalog';
    }

    if (nw_fuel_is_product_context()) {
        $classes[] = 'page-products';
    }

    return $classes;
}
