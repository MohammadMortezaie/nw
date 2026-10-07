<?php
/**
 * WooCommerce integration and catalog-only behavior.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'nw_fuel_woocommerce_setup');
add_action('init', 'nw_fuel_ensure_product_permalinks', 40);
add_action('wp', 'nw_fuel_disable_woocommerce_checkout');
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
add_filter('woocommerce_placeholder_img_src', 'nw_fuel_woocommerce_placeholder_img_src');
add_filter('body_class', 'nw_fuel_woocommerce_body_class');
add_filter('pre_get_posts', 'nw_fuel_products_per_page');
add_filter('loop_shop_per_page', 'nw_fuel_catalog_per_page');
add_action('woocommerce_product_query', 'nw_fuel_apply_shop_catalog_search');
add_filter('woocommerce_product_query_tax_query', 'nw_fuel_shop_catalog_tax_query', 10, 2);
add_filter('register_taxonomy_product_brand', 'nw_fuel_hide_woocommerce_product_brands');
add_action('add_meta_boxes', 'nw_fuel_remove_woocommerce_product_brands_box', 40);
add_action('admin_menu', 'nw_fuel_remove_woocommerce_product_brands_menu', 99);
add_filter('manage_edit-product_columns', 'nw_fuel_remove_product_brand_column', 30);
add_filter('woocommerce_products_admin_list_table_filters', 'nw_fuel_remove_product_brand_list_filter', 20);

// Hide default add-to-cart and checkout UI.
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
add_filter('woocommerce_is_purchasable', '__return_false');

/**
 * Hide WooCommerce's built-in Brands taxonomy. The site uses Attributes → Brand (pa_brand).
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function nw_fuel_hide_woocommerce_product_brands(array $args): array
{
    $args['show_ui']            = false;
    $args['show_admin_column']  = false;
    $args['show_in_nav_menus']  = false;
    $args['show_in_quick_edit'] = false;
    $args['show_in_rest']       = false;
    $args['meta_box_cb']        = false;
    $args['public']             = false;
    $args['publicly_queryable'] = false;
    $args['rewrite']            = false;

    return $args;
}

/**
 * Remove the leftover Brands metabox if the taxonomy UI still registered.
 */
function nw_fuel_remove_woocommerce_product_brands_box(): void
{
    remove_meta_box('product_branddiv', 'product', 'side');
    remove_meta_box('tagsdiv-product_brand', 'product', 'side');
}

/**
 * Remove Products → Brands.
 */
function nw_fuel_remove_woocommerce_product_brands_menu(): void
{
    remove_submenu_page('edit.php?post_type=product', 'edit-tags.php?taxonomy=product_brand&post_type=product');
}

/**
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_remove_product_brand_column(array $columns): array
{
    unset($columns['taxonomy-product_brand']);
    return $columns;
}

/**
 * @param array<string, mixed> $filters
 * @return array<string, mixed>
 */
function nw_fuel_remove_product_brand_list_filter(array $filters): array
{
    unset($filters['product_brand']);
    return $filters;
}

/**
 * Declare WooCommerce support.
 */
function nw_fuel_woocommerce_setup(): void
{
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}

/**
 * Theme placeholder for products with no photo.
 */
function nw_fuel_woocommerce_placeholder_img_src($src): string
{
    unset($src);
    return nw_fuel_product_placeholder_url();
}

/**
 * Redirect cart/checkout pages and disable purchasing.
 */
function nw_fuel_disable_woocommerce_checkout(): void
{
    if (is_cart() || is_checkout()) {
        wp_safe_redirect(nw_fuel_products_url());
        exit;
    }
}

/**
 * Ensure product archive uses theme body class.
 */
function nw_fuel_woocommerce_body_class(array $classes): array
{
    if (function_exists('nw_fuel_is_product_context') && nw_fuel_is_product_context()) {
        $classes[] = 'page-products';
    }
    return $classes;
}

/**
 * Products shown on each catalog page.
 */
function nw_fuel_catalog_per_page(): int
{
    return 18;
}

/**
 * Sanitize a catalog filter from the request.
 */
function nw_fuel_catalog_request_text(string $key): string
{
    if (! isset($_GET[$key])) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string) $_GET[$key]));
}

/**
 * Limit the shop archive to 18 products per page.
 */
function nw_fuel_products_per_page(WP_Query $query): void
{
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    if ((function_exists('is_shop') && is_shop()) || is_post_type_archive('product')) {
        $query->set('posts_per_page', nw_fuel_catalog_per_page());
    }
}

/**
 * Apply the catalog search box to the shop query.
 */
function nw_fuel_apply_shop_catalog_search(WP_Query $query): void
{
    if (is_admin()) {
        return;
    }

    $q = nw_fuel_catalog_request_text('q');
    if ($q !== '') {
        $query->set('s', $q);
    }

    $query->set('posts_per_page', nw_fuel_catalog_per_page());

    $brand = nw_fuel_catalog_request_text('brand');
    if ($brand !== '') {
        $ids = nw_fuel_product_ids_matching_brand($brand);
        $query->set('post__in', $ids === [] ? [0] : $ids);
    }
}

/**
 * Apply category / brand / vehicle / engine filters on the shop.
 *
 * @param array<int, mixed> $tax_query
 */
function nw_fuel_shop_catalog_tax_query(array $tax_query, WC_Query $wc_query): array
{
    unset($wc_query);

    if (! ((function_exists('is_shop') && is_shop()) || is_post_type_archive('product'))) {
        return $tax_query;
    }

    $filters = [
        'category' => 'product_cat',
        'vehicle'  => 'pa_vehicle-type',
        'engine'   => 'pa_engine-type',
    ];

    foreach ($filters as $param => $taxonomy) {
        $value = nw_fuel_catalog_request_text($param);
        if ($value === '' || ! taxonomy_exists($taxonomy)) {
            continue;
        }

        $term = get_term_by('name', $value, $taxonomy);
        if (! $term instanceof WP_Term) {
            $term = get_term_by('slug', sanitize_title($value), $taxonomy);
        }
        if (! $term instanceof WP_Term) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => [$term->term_id],
        ];
    }

    return $tax_query;
}

/**
 * Product IDs for a catalog brand filter (pa_brand name/slug or PriceBook meta).
 *
 * @return list<int>
 */
function nw_fuel_product_ids_matching_brand(string $brand): array
{
    $brand = trim($brand);
    if ($brand === '') {
        return [];
    }

    $ids = [];
    if (taxonomy_exists('pa_brand')) {
        $term = get_term_by('name', $brand, 'pa_brand');
        if (! $term instanceof WP_Term) {
            $term = get_term_by('slug', sanitize_title($brand), 'pa_brand');
        }
        if ($term instanceof WP_Term) {
            $ids = array_map('intval', get_objects_in_term((int) $term->term_id, 'pa_brand'));
        }
    }

    global $wpdb;
    $meta_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND LOWER(meta_value) = LOWER(%s)",
            '_nw_price_book',
            $brand
        )
    );
    if (is_array($meta_ids)) {
        $ids = array_merge($ids, array_map('intval', $meta_ids));
    }

    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * Catalog page numbers under the product grid.
 */
function nw_fuel_render_catalog_pagination(): void
{
    global $wp_query;

    $total = (int) $wp_query->max_num_pages;
    if ($total < 2) {
        return;
    }

    $current = max(1, (int) $wp_query->get('paged'), (int) get_query_var('paged'));
    $links   = paginate_links([
        'total'     => $total,
        'current'   => $current,
        'mid_size'  => 2,
        'end_size'  => 1,
        'prev_text' => __('Previous', 'nw-fuel'),
        'next_text' => __('Next', 'nw-fuel'),
        'type'      => 'list',
    ]);

    if (! is_string($links) || $links === '') {
        return;
    }

    echo '<nav class="catalog-pagination" aria-label="' . esc_attr__('Product pages', 'nw-fuel') . '">';
    echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() is escaped.
    echo '</nav>';
}

/**
 * Extend product search admin-side meta query for part numbers.
 */
add_filter('posts_search', 'nw_fuel_product_search_part_number', 10, 2);

function nw_fuel_product_search_part_number(string $search, WP_Query $query): string
{
    if (is_admin() && ! wp_doing_ajax()) {
        return $search;
    }

    $post_type = $query->get('post_type');
    $is_product_query = $post_type === 'product'
        || (is_array($post_type) && in_array('product', $post_type, true));

    if (! $is_product_query) {
        return $search;
    }

    global $wpdb;
    $term = $query->get('s');
    if (! is_string($term) || $term === '') {
        return $search;
    }

    $like = '%' . $wpdb->esc_like($term) . '%';
    $compact = '%' . $wpdb->esc_like(str_replace(' ', '', $term)) . '%';
    $digits = preg_replace('/\D+/', '', $term);

    $search .= $wpdb->prepare(
        " OR ({$wpdb->posts}.post_type = 'product' AND {$wpdb->posts}.ID IN (
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_nw_part_number', '_sku', '_nw_search_alts', '_nw_secondary_ids')
            AND (meta_value LIKE %s OR REPLACE(meta_value, ' ', '') LIKE %s)
        ))",
        $like,
        $compact
    );

    if (is_string($digits) && strlen($digits) >= 4) {
        $digit_like = '%' . $wpdb->esc_like($digits) . '%';
        $search .= $wpdb->prepare(
            " OR ({$wpdb->posts}.post_type = 'product' AND {$wpdb->posts}.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key IN ('_nw_part_number', '_sku', '_nw_search_alts', '_nw_secondary_ids')
                AND REPLACE(REPLACE(REPLACE(meta_value, '-', ''), ' ', ''), '/', '') LIKE %s
            ))",
            $digit_like
        );
    }

    return $search;
}

/**
 * Product permalink base helper used during import/setup.
 */
function nw_fuel_configure_permalinks(): void
{
    if (! nw_fuel_is_woocommerce_active()) {
        return;
    }

    update_option('woocommerce_permalinks', array_merge(
        (array) get_option('woocommerce_permalinks', []),
        [
            'product_base'            => 'products',
            'category_base'           => 'product-category',
            'tag_base'                => 'product-tag',
            'use_verbose_page_rules'  => true,
        ]
    ));

    update_option('permalink_structure', '/%postname%/');
    update_option('category_base', 'category');
    update_option('tag_base', 'tag');
}

/**
 * Ensure required Woo pages exist with expected slugs.
 */
function nw_fuel_ensure_woocommerce_pages(): void
{
    if (! nw_fuel_is_woocommerce_active()) {
        return;
    }

    $shop = get_page_by_path('products');
    if (! $shop) {
        $shop_id = wp_insert_post([
            'post_title'  => 'Products',
            'post_name'   => 'products',
            'post_status' => 'publish',
            'post_type'   => 'page',
        ]);
    } else {
        $shop_id = $shop->ID;
    }

    if ($shop_id && ! is_wp_error($shop_id)) {
        update_option('woocommerce_shop_page_id', (int) $shop_id);
    }
}

/**
 * Keep shop at /products/ and product details at /products/{slug}/.
 */
function nw_fuel_ensure_product_permalinks(): void
{
    if (! nw_fuel_is_woocommerce_active()) {
        return;
    }

    $permalinks = (array) get_option('woocommerce_permalinks', []);
    $changed    = false;

    if (($permalinks['product_base'] ?? '') !== 'products') {
        $permalinks['product_base'] = 'products';
        $changed = true;
    }
    if (empty($permalinks['use_verbose_page_rules'])) {
        $permalinks['use_verbose_page_rules'] = true;
        $changed = true;
    }

    if ($changed) {
        update_option('woocommerce_permalinks', $permalinks);
    }

    if ((string) get_option('nw_fuel_product_rewrite_version') !== '3') {
        flush_rewrite_rules(false);
        update_option('nw_fuel_product_rewrite_version', '3', false);
    }
}
