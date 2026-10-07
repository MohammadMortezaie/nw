<?php
/**
 * Custom rewrite rules for legacy URL structure.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'nw_fuel_register_rewrites');
add_filter('post_link', 'nw_fuel_blog_post_link', 10, 2);
add_filter('post_type_link', 'nw_fuel_product_post_link', 10, 2);
add_filter('post_type_link', 'nw_fuel_service_post_link', 10, 2);
add_filter('post_type_link', 'nw_fuel_technical_resource_post_link', 10, 2);
add_filter('post_type_link', 'nw_fuel_catalog_post_link', 10, 2);

/**
 * Register pretty routes matching the legacy PHP site.
 */
function nw_fuel_register_rewrites(): void
{
    add_rewrite_rule('^blog/([^/]+)/?$', 'index.php?name=$matches[1]', 'top');
    add_rewrite_rule('^products/page/?([0-9]{1,})/?$', 'index.php?pagename=products&paged=$matches[1]', 'top');
    // Shop page is also /products/; this rule sends /products/{slug}/ to the product CPT.
    add_rewrite_rule('^products/([^/]+)/?$', 'index.php?product=$matches[1]&post_type=product', 'top');
}

/**
 * Product permalinks: /products/{slug}/.
 */
function nw_fuel_product_post_link(string $permalink, WP_Post $post): string
{
    if ($post->post_type !== 'product' || $post->post_status === 'draft') {
        return $permalink;
    }

    return home_url(user_trailingslashit('products/' . $post->post_name));
}

/**
 * Prefix blog singles with /blog/.
 */
function nw_fuel_blog_post_link(string $permalink, WP_Post $post): string
{
    if ($post->post_type !== 'post' || $post->post_status === 'draft') {
        return $permalink;
    }

    return home_url(user_trailingslashit('blog/' . $post->post_name));
}

/**
 * Ensure services use /services/{slug}/.
 */
function nw_fuel_service_post_link(string $permalink, WP_Post $post): string
{
    if ($post->post_type !== 'nw_service') {
        return $permalink;
    }

    return home_url(user_trailingslashit('services/' . $post->post_name));
}

/**
 * Ensure technical resources use /technical-resources/{slug}/.
 */
function nw_fuel_technical_resource_post_link(string $permalink, WP_Post $post): string
{
    if ($post->post_type !== 'nw_tech_resource') {
        return $permalink;
    }

    return home_url(user_trailingslashit('technical-resources/' . $post->post_name));
}

/**
 * Catalog PDFs use /catalog/{slug}/.
 */
function nw_fuel_catalog_post_link(string $permalink, WP_Post $post): string
{
    if ($post->post_type !== 'nw_catalog_item') {
        return $permalink;
    }

    return home_url(user_trailingslashit('catalog/' . $post->post_name));
}
