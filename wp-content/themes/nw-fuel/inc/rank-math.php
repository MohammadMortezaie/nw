<?php
/**
 * Rank Math owns SEO for products and technical pages.
 *
 * Other site pages keep the theme SEO fields. Rank Math frontend output is
 * limited to those screens so tags are not duplicated.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'nw_fuel_migrate_product_seo_to_rank_math');
add_action('admin_init', 'nw_fuel_migrate_tech_seo_to_rank_math');
add_action('wp', 'nw_fuel_rank_math_scope_frontend', 0);
add_action('add_meta_boxes', 'nw_fuel_limit_rank_math_metabox', 99);
add_action('rank_math/vars/register_extra_replacements', 'nw_fuel_rank_math_register_vars');
add_filter('rank_math/snippet/rich_snippet_product_entity', 'nw_fuel_rank_math_strip_product_offers');
add_filter('rank_math/opengraph/facebook/product_price_amount', '__return_false');
add_filter('rank_math/opengraph/facebook/product_price_currency', '__return_false');
add_filter('rank_math/opengraph/facebook/image', 'nw_fuel_rank_math_product_og_image');
add_filter('rank_math/opengraph/twitter/image', 'nw_fuel_rank_math_product_og_image');
add_filter('rank_math/json_ld', 'nw_fuel_rank_math_product_faq_schema', 99, 2);
add_filter('rank_math/excluded_post_types', 'nw_fuel_rank_math_keep_tech_post_type');

/**
 * Whether Rank Math is loaded.
 */
function nw_fuel_rank_math_active(): bool
{
    return defined('RANK_MATH_VERSION');
}

/**
 * Post types whose SEO Rank Math owns.
 *
 * @return list<string>
 */
function nw_fuel_rank_math_post_types(): array
{
    return ['product', 'nw_tech_resource'];
}

/**
 * Rank Math should output SEO for this request.
 */
function nw_fuel_rank_math_owns_seo(): bool
{
    if (! nw_fuel_rank_math_active()) {
        return false;
    }

    if (function_exists('is_product') && is_product()) {
        return true;
    }

    return is_singular(nw_fuel_rank_math_post_types());
}

/**
 * @param array<string, string>|mixed $types
 * @return array<string, string>|mixed
 */
function nw_fuel_rank_math_keep_tech_post_type($types)
{
    if (is_array($types)) {
        unset($types['nw_tech_resource']);
    }

    return $types;
}

/**
 * Copy existing theme product title/description into Rank Math once.
 */
function nw_fuel_migrate_product_seo_to_rank_math(): void
{
    if (! nw_fuel_rank_math_active()) {
        return;
    }
    if (get_option('nw_fuel_rank_math_product_seo_migrated')) {
        return;
    }

    $ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($ids as $id) {
        $id    = (int) $id;
        $title = (string) get_post_meta($id, '_nw_meta_title', true);
        $desc  = (string) get_post_meta($id, '_nw_meta_description', true);

        if ($title !== '' && (string) get_post_meta($id, 'rank_math_title', true) === '') {
            update_post_meta($id, 'rank_math_title', $title);
        }
        if ($desc !== '' && (string) get_post_meta($id, 'rank_math_description', true) === '') {
            update_post_meta($id, 'rank_math_description', $desc);
        }
    }

    update_option('nw_fuel_rank_math_product_seo_migrated', '1', false);
}

/**
 * Copy existing theme technical-page title/description into Rank Math once.
 */
function nw_fuel_migrate_tech_seo_to_rank_math(): void
{
    if (! nw_fuel_rank_math_active()) {
        return;
    }
    if (get_option('nw_fuel_rank_math_tech_seo_migrated')) {
        return;
    }

    $ids = get_posts([
        'post_type'      => 'nw_tech_resource',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($ids as $id) {
        $id    = (int) $id;
        $title = (string) get_post_meta($id, '_nw_meta_title', true);
        $desc  = (string) get_post_meta($id, '_nw_meta_description', true);

        if ($title !== '' && (string) get_post_meta($id, 'rank_math_title', true) === '') {
            update_post_meta($id, 'rank_math_title', $title);
        }
        if ($desc !== '' && (string) get_post_meta($id, 'rank_math_description', true) === '') {
            update_post_meta($id, 'rank_math_description', $desc);
        }
    }

    update_option('nw_fuel_rank_math_tech_seo_migrated', '1', false);
}

/**
 * Rank Math frontend only on product and technical pages; theme SEO covers the rest.
 */
function nw_fuel_rank_math_scope_frontend(): void
{
    if (! nw_fuel_rank_math_active() || is_admin()) {
        return;
    }

    if (! nw_fuel_rank_math_owns_seo()) {
        add_filter('rank_math/frontend/disable', '__return_true');
        remove_all_actions('rank_math/head');
    }
}

/**
 * Keep the Rank Math editor on products and technical pages.
 */
function nw_fuel_limit_rank_math_metabox(): void
{
    if (! nw_fuel_rank_math_active()) {
        return;
    }

    $screen = get_current_screen();
    if (! $screen || in_array($screen->post_type, nw_fuel_rank_math_post_types(), true)) {
        return;
    }

    remove_meta_box('rank_math_metabox', $screen->id, 'normal');
    remove_meta_box('rank_math_metabox', $screen->id, 'advanced');
    remove_meta_box('rank_math_metabox', $screen->id, 'side');
}

/**
 * %nw_part% replacement for Rank Math title/description templates.
 */
function nw_fuel_rank_math_register_vars(): void
{
    static $registered = false;
    if ($registered || ! function_exists('rank_math_register_var_replacement') || ! function_exists('rank_math')) {
        return;
    }

    $plugin = rank_math();
    if (! is_object($plugin) || empty($plugin->variables)) {
        return;
    }

    $registered = true;

    rank_math_register_var_replacement(
        'nw_part',
        [
            'name'        => esc_html__('Part number', 'nw-fuel'),
            'description' => esc_html__('NW Fuel item / part number', 'nw-fuel'),
            'variable'    => 'nw_part',
            'example'     => '0445120237',
        ],
        static function () {
            $id = get_the_ID();
            return $id ? nw_fuel_get_part_number((int) $id) : '';
        }
    );
}

/**
 * Do not publish wholesale/retail in Product schema (catalog is quote-only).
 *
 * @param mixed $entity
 * @return mixed
 */
function nw_fuel_rank_math_strip_product_offers($entity)
{
    if (! is_array($entity)) {
        return $entity;
    }

    unset($entity['offers']);
    return $entity;
}

/**
 * Featured image, then remote catalog image fallback.
 *
 * @param mixed $image
 * @return mixed
 */
function nw_fuel_rank_math_product_og_image($image)
{
    if (is_array($image)) {
        $image = (string) ($image['url'] ?? '');
    } elseif (! is_string($image)) {
        $image = (string) $image;
    }

    if (! nw_fuel_rank_math_owns_seo()) {
        return $image;
    }

    if ($image !== '') {
        return $image;
    }

    $post_id = get_queried_object_id();
    if (! $post_id) {
        return $image;
    }

    $remote = (string) get_post_meta($post_id, '_nw_remote_image', true);
    if ($remote !== '') {
        return nw_fuel_resolve_image_url($remote);
    }

    return nw_fuel_product_placeholder_url();
}

/**
 * Keep product FAQ schema from the NW Fuel FAQ repeater.
 *
 * @param mixed $data
 * @param mixed $jsonld
 * @return mixed
 */
function nw_fuel_rank_math_product_faq_schema($data, $jsonld = null)
{
    unset($jsonld);

    if (! is_array($data) || ! nw_fuel_rank_math_owns_seo()) {
        return $data;
    }

    $post_id = get_queried_object_id();
    if (! $post_id) {
        return $data;
    }

    $faqs = nw_fuel_faq_schema(nw_fuel_json_meta($post_id, '_nw_product_faqs'));
    if ($faqs === null) {
        return $data;
    }

    unset($faqs['@context']);
    $data['FAQPage'] = $faqs;

    return $data;
}
