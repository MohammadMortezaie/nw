<?php
/**
 * Shared theme helpers.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_nw_fuel_product_search', 'nw_fuel_ajax_product_search');
add_action('wp_ajax_nopriv_nw_fuel_product_search', 'nw_fuel_ajax_product_search');

/**
 * Whether WooCommerce is active.
 */
function nw_fuel_is_woocommerce_active(): bool
{
    return class_exists('WooCommerce');
}

/**
 * Escape HTML output.
 */
function nw_fuel_e(?string $value): string
{
    return esc_html((string) $value);
}

/**
 * Build tel: href from a phone string.
 */
function nw_fuel_phone_href(string $phone): string
{
    return 'tel:' . preg_replace('/\D/', '', $phone);
}

/**
 * Products catalog URL.
 */
function nw_fuel_products_url(): string
{
    if (nw_fuel_is_woocommerce_active() && function_exists('wc_get_page_id')) {
        $shop_id = wc_get_page_id('shop');
        if ($shop_id > 0) {
            return get_permalink($shop_id) ?: home_url('/products/');
        }
    }

    return home_url('/products/');
}

/**
 * Single product URL (/products/{slug}/).
 */
function nw_fuel_product_url(WC_Product|int $product): string
{
    $id = $product instanceof WC_Product ? $product->get_id() : $product;
    if ($id <= 0) {
        return nw_fuel_products_url();
    }

    $slug = '';
    if ($product instanceof WC_Product) {
        $slug = (string) $product->get_slug();
    }
    if ($slug === '') {
        $post = get_post($id);
        $slug = $post instanceof WP_Post ? (string) $post->post_name : '';
    }
    if ($slug === '') {
        $permalink = $product instanceof WC_Product ? (string) $product->get_permalink() : (string) get_permalink($id);
        return $permalink !== '' ? $permalink : nw_fuel_products_url();
    }

    return home_url(user_trailingslashit('products/' . $slug));
}

/**
 * Page URL by slug.
 */
function nw_fuel_page_url(string $slug): string
{
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return get_permalink($page) ?: home_url('/' . $slug . '/');
    }

    return home_url('/' . trim($slug, '/') . '/');
}

/**
 * Whether the current request is About or History (About family).
 */
function nw_fuel_is_about_context(): bool
{
    return is_page('about')
        || is_page_template('page-about.php')
        || is_page('history-of-nw-fuel')
        || is_page_template('page-history.php');
}

/**
 * Theme asset URL.
 */
function nw_fuel_asset_url(string $path): string
{
    return NW_FUEL_URI . '/' . ltrim($path, '/');
}

/**
 * Default catalog image when a product has no photo.
 */
function nw_fuel_product_placeholder_url(): string
{
    return nw_fuel_asset_url('assets/img/product-placeholder.jpg');
}

/**
 * Resolve image URLs from legacy paths or remote URLs.
 */
function nw_fuel_resolve_image_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '/assets/')) {
        return nw_fuel_asset_url(ltrim($url, '/'));
    }

    if (str_starts_with($url, 'assets/')) {
        return nw_fuel_asset_url($url);
    }

    return $url;
}

/**
 * Recursively resolve image map values to usable URLs.
 *
 * @param array<string, mixed> $images
 * @return array<string, mixed>
 */
function nw_fuel_resolve_images_map(array $images): array
{
    $resolved = [];

    foreach ($images as $key => $value) {
        if (is_array($value)) {
            $resolved[$key] = nw_fuel_resolve_images_map($value);
        } elseif (is_string($value)) {
            $resolved[$key] = nw_fuel_resolve_image_url($value);
        } else {
            $resolved[$key] = $value;
        }
    }

    return $resolved;
}

/**
 * Get theme settings with defaults.
 *
 * @return array<string, mixed>
 */
function nw_fuel_get_settings(): array
{
    $defaults = [
        'business' => [
            'name'        => 'NW Fuel Injection Services Ltd.',
            'shortName'   => 'NW Fuel',
            'tagline'     => 'Diesel Done Right',
            'description' => 'Bosch authorized diesel injection shop in Surrey, BC. Injector testing, pump rebuilds, common rail repair, and OEM diesel parts since 1968.',
            'phone'       => '(604) 882-3835',
            'fax'         => '(604) 882-3887',
            'email'       => 'info@nwfuel.ca',
            'address'     => [
                'street'     => '#101-18940 94th Ave',
                'city'       => 'Surrey',
                'province'   => 'BC',
                'postalCode' => 'V3V 1N1',
                'country'    => 'CA',
            ],
            'hours'  => 'Monday to Friday, 8:00 AM to 4:30 PM',
            'social' => [],
        ],
        'images'       => [],
        'company'      => [],
        'home_faqs'    => [],
        'contact_faqs' => [],
        'home'         => [
            'featured_service_slugs' => [
                'mechanical-injector-rebuild',
                'heui-eui-testing',
                'common-rail-repair',
                'fuel-pump-rebuild',
            ],
        ],
    ];

    $saved = get_option('nw_fuel_settings', []);
    if (! is_array($saved)) {
        $saved = [];
    }

    return array_replace_recursive($defaults, $saved);
}

/**
 * Business info shortcut (settings + Customizer overrides).
 *
 * @return array<string, mixed>
 */
function nw_fuel_business(): array
{
    $settings = nw_fuel_get_settings();
    $business = is_array($settings['business'] ?? null) ? $settings['business'] : [];

    foreach (['phone', 'fax', 'email', 'hours', 'description'] as $key) {
        $mod = get_theme_mod("nw_fuel_business_{$key}", null);
        if (is_string($mod) && $mod !== '') {
            $business[$key] = $mod;
        }
    }

    if (! isset($business['address']) || ! is_array($business['address'])) {
        $business['address'] = [];
    }

    foreach (['street', 'city', 'province', 'postalCode'] as $key) {
        $mod = get_theme_mod("nw_fuel_address_{$key}", null);
        if (is_string($mod) && $mod !== '') {
            $business['address'][$key] = $mod;
        }
    }

    if (! isset($business['social']) || ! is_array($business['social'])) {
        $business['social'] = [];
    }

    foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network) {
        $mod = get_theme_mod("nw_fuel_social_{$network}", null);
        if (is_string($mod) && $mod !== '') {
            $business['social'][$network] = $mod;
        }
    }

    return $business;
}

/**
 * Image map shortcut.
 *
 * @return array<string, mixed>
 */
function nw_fuel_images(): array
{
    $settings = nw_fuel_get_settings();
    $images   = is_array($settings['images'] ?? null) ? $settings['images'] : [];
    $images   = nw_fuel_resolve_images_map($images);

    $hero = (string) ($images['hero'] ?? '');
    if ($hero === '' || str_contains($hero, 'hero1.png')) {
        $images['hero'] = $images['labEquipment']['lg']
            ?? $images['industrialWorkshop']['lg']
            ?? $images['warehouseParts']['lg']
            ?? nw_fuel_asset_url('assets/img/logo1.png');
    }

    return $images;
}

/**
 * Company/about structured data.
 *
 * @return array<string, mixed>
 */
function nw_fuel_company(): array
{
    $settings = nw_fuel_get_settings();
    return is_array($settings['company'] ?? null) ? $settings['company'] : [];
}

/**
 * Decode JSON meta safely.
 *
 * @return array<int|string, mixed>
 */
function nw_fuel_json_meta(int $post_id, string $key, mixed $default = []): array
{
    $raw = get_post_meta($post_id, $key, true);
    if (! is_string($raw) || $raw === '') {
        return is_array($default) ? $default : [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : (is_array($default) ? $default : []);
}

/**
 * Encode JSON meta.
 */
function nw_fuel_json_encode_meta(mixed $value): string
{
    return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * Product part number meta.
 */
function nw_fuel_get_part_number(int $product_id): string
{
    $part = (string) get_post_meta($product_id, '_nw_part_number', true);
    if ($part !== '') {
        return $part;
    }

    if (nw_fuel_is_woocommerce_active()) {
        $product = wc_get_product($product_id);
        if ($product) {
            return (string) $product->get_sku();
        }
    }

    return '';
}

/**
 * Hidden internal product code (never output on frontend).
 */
function nw_fuel_get_product_code(int $product_id): string
{
    return (string) get_post_meta($product_id, '_nw_product_code', true);
}

/**
 * Stock status slug.
 */
function nw_fuel_stock_status(int $quantity): string
{
    if ($quantity <= 0) {
        return 'out_of_stock';
    }
    if ($quantity <= 5) {
        return 'low_stock';
    }
    return 'in_stock';
}

/**
 * Format product price (matches static en_CA NumberFormatter output).
 */
function nw_fuel_format_price(float $price, string $currency = 'CAD'): string
{
    if ($currency === '') {
        $currency = 'CAD';
    }

    if (class_exists(NumberFormatter::class)) {
        $formatter = new NumberFormatter('en_CA', NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($price, $currency);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return '$' . number_format($price, 2);
}

/**
 * Stock label for detail view.
 */
function nw_fuel_stock_label(int $quantity): string
{
    return match (nw_fuel_stock_status($quantity)) {
        'out_of_stock' => __('Out of Stock', 'nw-fuel'),
        'low_stock'    => __('Low Stock', 'nw-fuel'),
        default        => __('In Stock', 'nw-fuel'),
    };
}

/**
 * Quantity label for cards.
 */
function nw_fuel_quantity_display(int $quantity): string
{
    if ($quantity <= 0) {
        return '0';
    }
    if ($quantity > 20) {
        return '20+';
    }

    return (string) $quantity;
}

function nw_fuel_quantity_label(int $quantity): string
{
    if ($quantity <= 0) {
        return __('0 available', 'nw-fuel');
    }
    if ($quantity > 20) {
        return __('20+ in stock', 'nw-fuel');
    }
    if ($quantity === 1) {
        return __('1 in stock', 'nw-fuel');
    }
    return sprintf(__('%d in stock', 'nw-fuel'), $quantity);
}

function nw_fuel_user_can_view_prices(): bool
{
    return nw_fuel_user_discount_level() > 0;
}

/**
 * Logged-in partner discount level (1–4). WordPress admin login does not count.
 */
function nw_fuel_user_discount_level(?int $user_id = null): int
{
    unset($user_id);
    if (function_exists('nw_fuel_current_partner_level')) {
        return nw_fuel_current_partner_level();
    }

    return 0;
}

/**
 * Retail or the logged-in partner's discount-level price.
 */
function nw_fuel_product_price_for_user(int $product_id): float
{
    $retail = (float) get_post_meta($product_id, '_nw_retail', true);
    $level  = nw_fuel_user_discount_level();
    $map    = [
        1 => '_nw_trade_total',
        2 => '_nw_special1_total',
        3 => '_nw_special2_total',
        4 => '_nw_special3_total',
    ];

    if (isset($map[$level])) {
        $tier = (float) get_post_meta($product_id, $map[$level], true);
        if ($tier > 0) {
            return $tier;
        }
    }

    return $retail;
}

/**
 * Unit price shown to the current visitor, plus which list it came from.
 *
 * @return array{price: float, type: string, label: string, partner_level: int}
 */
function nw_fuel_product_quote_price_snapshot(int $product_id): array
{
    $retail = (float) get_post_meta($product_id, '_nw_retail', true);
    $level  = nw_fuel_user_discount_level();
    $map    = [
        1 => ['_nw_trade_total', 'level_1', __('Level 1', 'nw-fuel')],
        2 => ['_nw_special1_total', 'level_2', __('Level 2', 'nw-fuel')],
        3 => ['_nw_special2_total', 'level_3', __('Level 3', 'nw-fuel')],
        4 => ['_nw_special3_total', 'level_4', __('Level 4', 'nw-fuel')],
    ];

    $price = $retail;
    $type  = 'retail';
    $label = __('Retail', 'nw-fuel');

    if (isset($map[$level])) {
        $tier = (float) get_post_meta($product_id, $map[$level][0], true);
        if ($tier > 0) {
            $price = $tier;
            $type  = $map[$level][1];
            $label = $map[$level][2];
        } else {
            $label = sprintf(
                /* translators: %s: discount level name */
                __('Retail (no %s price)', 'nw-fuel'),
                $map[$level][2]
            );
        }
    }

    if ($price <= 0 && function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product) {
            $fallback = (float) $product->get_regular_price();
            if ($fallback <= 0) {
                $fallback = (float) $product->get_price();
            }
            if ($fallback > 0) {
                $price = $fallback;
            }
        }
    }

    return [
        'price'         => $price,
        'type'          => $type,
        'label'         => $label,
        'partner_level' => $level,
    ];
}

/**
 * @return list<string>
 */
function nw_fuel_get_search_alts(int $product_id): array
{
    $stored = nw_fuel_json_meta($product_id, '_nw_secondary_ids');
    if (! is_array($stored) || $stored === []) {
        $plain = (string) get_post_meta($product_id, '_nw_search_alts', true);
        if ($plain === '') {
            return [];
        }
        $stored = preg_split('/\s+/', $plain) ?: [];
    }

    $out = [];
    foreach ($stored as $alt) {
        $alt = trim((string) $alt);
        if ($alt !== '') {
            $out[] = $alt;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Render product/editor HTML (bold, italic, links, lists, line breaks).
 */
function nw_fuel_rich_text(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed = wp_kses_allowed_html('post');
    foreach (['span', 'p', 'li', 'ul', 'ol', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
        if (! isset($allowed[$tag])) {
            $allowed[$tag] = [];
        }
        $allowed[$tag]['style'] = true;
        $allowed[$tag]['class'] = true;
    }

    return wp_kses(wpautop($html), $allowed);
}

/**
 * Format date for display.
 */
function nw_fuel_format_date(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return wp_date('F j, Y', $timestamp);
}

/**
 * JSON-LD script tag.
 */
function nw_fuel_json_ld(array $data): string
{
    return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Product categories for filters.
 *
 * @return string[]
 */
function nw_fuel_product_categories(): array
{
    if (! nw_fuel_is_woocommerce_active()) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'menu_order',
    ]);

    if (is_wp_error($terms) || ! is_array($terms)) {
        return [];
    }

    return array_map(static fn($term) => $term->name, $terms);
}

/**
 * Attribute term names.
 *
 * @return string[]
 */
function nw_fuel_attribute_terms(string $taxonomy): array
{
    if (! taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'menu_order',
    ]);

    if (is_wp_error($terms) || ! is_array($terms)) {
        return [];
    }

    return array_map(static fn($term) => $term->name, $terms);
}

/**
 * Primary product category name.
 */
function nw_fuel_product_category_name(int $product_id): string
{
    $terms = get_the_terms($product_id, 'product_cat');
    if (is_array($terms) && $terms !== []) {
        return $terms[0]->name;
    }
    return '';
}

/**
 * Term names for a product taxonomy.
 *
 * @return string[]
 */
function nw_fuel_product_term_names(int $product_id, string $taxonomy): array
{
    $terms = get_the_terms($product_id, $taxonomy);
    if (! is_array($terms)) {
        return [];
    }

    return array_map(static fn($term) => $term->name, $terms);
}

/**
 * Published catalog size without loading every product.
 */
function nw_fuel_published_product_count(): int
{
    if (! nw_fuel_is_woocommerce_active()) {
        return 0;
    }

    $counts = wp_count_posts('product');
    return isset($counts->publish) ? (int) $counts->publish : 0;
}

/**
 * Suggestion row for header / hero search.
 *
 * @return array<string, string>
 */
function nw_fuel_product_search_suggestion(int $product_id): array
{
    $product = wc_get_product($product_id);
    if (! $product) {
        return [];
    }

    $image = wp_get_attachment_image_url($product->get_image_id(), 'medium');
    if (! $image) {
        $image = (string) get_post_meta($product_id, '_nw_remote_image', true);
    }
    if (! $image) {
        $image = nw_fuel_product_placeholder_url();
    }

    $brands = nw_fuel_product_term_names($product_id, 'pa_brand');

    return [
        'id'       => $product_id,
        'slug'     => $product->get_slug(),
        'name'     => $product->get_name(),
        'part'     => nw_fuel_get_part_number($product_id),
        'alts'     => implode(' ', nw_fuel_get_search_alts($product_id)),
        'brand'    => $brands[0] ?? '',
        'category' => nw_fuel_product_category_name($product_id),
        'image'    => $image ?: '',
    ];
}

/**
 * Live product suggestions. Do not build a full-catalog index on page load.
 *
 * @return list<array<string, string>>
 */
function nw_fuel_product_search_suggestions(string $query, int $limit = 6): array
{
    if (! nw_fuel_is_woocommerce_active() || strlen(trim($query)) < 2) {
        return [];
    }

    $query = sanitize_text_field($query);
    $limit = max(1, min(20, $limit));
    $ids   = nw_fuel_product_search_match_ids($query, max(24, $limit * 4));

    $rows = [];
    foreach ($ids as $product_id) {
        $row = nw_fuel_product_search_suggestion($product_id);
        if ($row !== []) {
            $row['_score'] = nw_fuel_product_search_score($row, $query);
            $rows[] = $row;
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return ((int) $a['_score']) <=> ((int) $b['_score']);
    });

    $out = [];
    foreach (array_slice($rows, 0, $limit) as $row) {
        unset($row['_score']);
        $out[] = $row;
    }

    return $out;
}

/**
 * Product IDs whose part number, SKU, alts, or title match the query.
 *
 * @return list<int>
 */
function nw_fuel_product_search_match_ids(string $term, int $limit): array
{
    global $wpdb;

    $ids = [];
    if (function_exists('wc_get_product_id_by_sku')) {
        $by_sku = (int) wc_get_product_id_by_sku($term);
        if ($by_sku < 1) {
            $stripped = preg_replace('/\s+/', '', $term);
            if (is_string($stripped) && $stripped !== '' && $stripped !== $term) {
                $by_sku = (int) wc_get_product_id_by_sku($stripped);
            }
        }
        if ($by_sku > 0) {
            $ids[$by_sku] = $by_sku;
        }
    }

    $like        = '%' . $wpdb->esc_like($term) . '%';
    $compact     = preg_replace('/[\s\-\/\\\\.]/', '', $term) ?? $term;
    $compact_like = '%' . $wpdb->esc_like($compact) . '%';

    $meta_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pm.post_id
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           AND pm.meta_key IN ('_nw_part_number', '_sku', '_nw_search_alts', '_nw_secondary_ids')
           AND (
             pm.meta_value LIKE %s
             OR REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '-', ''), '/', ''), '.', '') LIKE %s
           )
         LIMIT %d",
        $like,
        $compact_like,
        $limit
    ));

    $title_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'product'
           AND post_status = 'publish'
           AND (post_title LIKE %s OR post_excerpt LIKE %s)
         LIMIT %d",
        $like,
        $like,
        $limit
    ));

    foreach (array_merge($ids, is_array($meta_ids) ? $meta_ids : [], is_array($title_ids) ? $title_ids : []) as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    if (count($ids) < $limit && strlen(nw_fuel_product_search_alnum($term)) >= 4) {
        $candidate_limit = max(120, min(300, $limit * 10));
        $fuzzy_matches   = [];
        foreach (nw_fuel_product_search_fuzzy_candidate_ids($term, $candidate_limit) as $id) {
            if (isset($ids[$id])) {
                continue;
            }

            $row = nw_fuel_product_search_suggestion($id);
            if ($row === []) {
                continue;
            }

            $score = nw_fuel_product_search_score($row, $term);
            if ($score < 99) {
                $fuzzy_matches[$id] = $score;
            }
        }

        asort($fuzzy_matches, SORT_NUMERIC);
        foreach (array_keys($fuzzy_matches) as $id) {
            $ids[$id] = $id;
            if (count($ids) >= $limit) {
                break;
            }
        }
    }

    return array_slice(array_values($ids), 0, $limit);
}

/**
 * Normalize a search value for forgiving part-number comparisons.
 */
function nw_fuel_product_search_alnum(string $value): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($value))) ?? '';
}

/**
 * Candidate products for typo-tolerant matching without loading the full catalog.
 *
 * @return list<int>
 */
function nw_fuel_product_search_fuzzy_candidate_ids(string $term, int $limit): array
{
    global $wpdb;

    $compact = nw_fuel_product_search_alnum($term);
    $length  = strlen($compact);
    if ($length < 4) {
        return [];
    }

    $fragment_length = $length >= 8 ? 4 : ($length >= 5 ? 3 : 2);
    $prefix_like     = '%' . $wpdb->esc_like(substr($compact, 0, $fragment_length)) . '%';
    $suffix_like     = '%' . $wpdb->esc_like(substr($compact, -$fragment_length)) . '%';
    $limit           = max(1, min(300, $limit));
    $normalize_title = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(p.post_title, ' ', ''), '-', ''), '/', ''), '.', ''))";
    $normalize_meta  = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '-', ''), '/', ''), '.', ''))";

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT p.ID
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm
           ON pm.post_id = p.ID
          AND pm.meta_key IN ('_nw_part_number', '_sku', '_nw_search_alts', '_nw_secondary_ids')
         WHERE p.post_type = 'product'
           AND p.post_status = 'publish'
           AND (
             {$normalize_title} LIKE %s
             OR {$normalize_title} LIKE %s
             OR {$normalize_meta} LIKE %s
             OR {$normalize_meta} LIKE %s
           )
         LIMIT %d",
        $prefix_like,
        $suffix_like,
        $prefix_like,
        $suffix_like,
        $limit
    ));

    return array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
}

/**
 * Lower is better for one part number or alternate.
 */
function nw_fuel_product_search_part_token_score(string $query, string $token): int
{
    $q = nw_fuel_product_search_alnum($query);
    $t = nw_fuel_product_search_alnum($token);
    if ($q === '' || $t === '') {
        return 99;
    }
    if ($t === $q) {
        return 0;
    }

    $query_digits = preg_replace('/\D/', '', $query) ?? '';
    $token_digits = preg_replace('/\D/', '', $token) ?? '';
    if ($query_digits !== '' && $token_digits === $query_digits) {
        return 1;
    }
    if (min(strlen($q), strlen($t)) >= 4 && (str_starts_with($t, $q) || str_starts_with($q, $t))) {
        return 2 + min(4, abs(strlen($t) - strlen($q)));
    }
    if (strlen($query_digits) >= 4 && strlen($token_digits) >= 4
        && (str_starts_with($token_digits, $query_digits) || str_starts_with($query_digits, $token_digits))) {
        return 3 + min(4, abs(strlen($token_digits) - strlen($query_digits)));
    }
    if (strlen($query_digits) >= 4 && str_contains($token_digits, $query_digits)) {
        return 8;
    }
    if (str_contains($t, $q) || (strlen($q) >= 4 && strlen($t) >= 4 && str_contains($q, $t))) {
        return 9;
    }
    if (strlen($query_digits) >= 4 && strlen($token_digits) >= 4) {
        $distance = levenshtein($query_digits, $token_digits);
        if ($distance <= 2) {
            return 10 + $distance;
        }
    }
    if (strlen($q) >= 4 && strlen($t) >= 4) {
        $distance = levenshtein($q, $t);
        if ($distance <= 3 && $distance / max(strlen($q), strlen($t)) <= 0.5) {
            return 13 + $distance;
        }
    }

    return 99;
}

/**
 * Match misspelled words in product names and brands.
 */
function nw_fuel_product_search_text_score(string $query, string $text): int
{
    $query_words = preg_split('/[^a-z0-9]+/', strtolower(remove_accents($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $text_words  = preg_split('/[^a-z0-9]+/', strtolower(remove_accents($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($query_words === [] || $text_words === []) {
        return 99;
    }

    $total = 0;
    foreach ($query_words as $query_word) {
        $best = 99;
        foreach ($text_words as $text_word) {
            if ($query_word === $text_word || str_contains($text_word, $query_word) || str_contains($query_word, $text_word)) {
                $best = 0;
                break;
            }
            if (strlen($query_word) < 3 || strlen($text_word) < 3) {
                continue;
            }
            $distance     = levenshtein($query_word, $text_word);
            $max_distance = strlen($query_word) <= 5 ? 1 : 2;
            if ($distance <= $max_distance) {
                $best = min($best, $distance);
            }
        }
        if ($best === 99) {
            return 99;
        }
        $total += $best;
    }

    return 30 + $total;
}

/**
 * Lower is better. Mirrors the old header search ranking for part numbers.
 *
 * @param array<string, string> $row
 */
function nw_fuel_product_search_score(array $row, string $query): int
{
    $q     = strtolower(trim($query));
    $part  = strtolower((string) ($row['part'] ?? ''));
    $alts  = strtolower((string) ($row['alts'] ?? ''));
    $name  = strtolower((string) ($row['name'] ?? ''));
    $brand = strtolower((string) ($row['brand'] ?? ''));

    $best = 99;
    foreach (array_filter(array_merge([$part], preg_split('/[\s,;|\/]+/', $alts) ?: [])) as $token) {
        $best = min($best, nw_fuel_product_search_part_token_score($query, (string) $token));
    }

    if ($best < 99) {
        return $best;
    }
    if ($name !== '' && str_starts_with($name, $q)) {
        return 20;
    }
    if ($name !== '' && str_contains($name, $q)) {
        return 22;
    }
    if ($brand !== '' && str_contains($brand, $q)) {
        return 23;
    }

    $text_score = nw_fuel_product_search_text_score($query, trim($name . ' ' . $brand));
    if ($text_score < 99) {
        return $text_score;
    }

    return 99;
}

/**
 * @deprecated Full index exhausted memory after nightly sync. Use AJAX suggestions.
 *
 * @return array<int, array<string, string>>
 */
function nw_fuel_product_search_index(): array
{
    return [];
}

function nw_fuel_ajax_product_search(): void
{
    $query = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['q'])) : '';
    $limit = isset($_REQUEST['limit']) ? absint($_REQUEST['limit']) : 6;

    $suggestions = nw_fuel_product_search_suggestions($query, $limit);

    if ($query !== '' && function_exists('nw_fuel_track_product_search')) {
        nw_fuel_track_product_search($query, array_map('intval', array_column($suggestions, 'id')));
    }

    wp_send_json_success($suggestions);
}

/**
 * Services for nav/footer.
 *
 * @return WP_Post[]
 */
function nw_fuel_services(): array
{
    return get_posts([
        'post_type'      => 'nw_service',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/**
 * Technical resources list.
 *
 * @return WP_Post[]
 */
function nw_fuel_technical_resources(): array
{
    return get_posts([
        'post_type'      => 'nw_tech_resource',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
}

/**
 * Related services by slug list.
 *
 * @param string[] $slugs
 * @return WP_Post[]
 */
function nw_fuel_related_services(array $slugs): array
{
    $posts = [];
    foreach ($slugs as $slug) {
        $post = get_page_by_path(sanitize_title($slug), OBJECT, 'nw_service');
        if ($post instanceof WP_Post) {
            $posts[] = $post;
        }
    }
    return $posts;
}

/**
 * Related products by slug list.
 *
 * @param string[] $slugs
 * @return WC_Product[]
 */
function nw_fuel_related_products(array $slugs): array
{
    if (! nw_fuel_is_woocommerce_active()) {
        return [];
    }

    $products = [];
    foreach ($slugs as $slug) {
        $post = get_page_by_path(sanitize_title($slug), OBJECT, 'product');
        if ($post instanceof WP_Post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $products[] = $product;
            }
        }
    }
    return $products;
}

/**
 * Max product photos an admin can attach (featured + gallery).
 */
const NW_FUEL_PRODUCT_PHOTOS_MAX = 5;

/**
 * Attached product photo IDs: featured first, then Woo gallery. Capped at 5.
 *
 * @return list<int>
 */
function nw_fuel_product_photo_ids(int $product_id): array
{
    $ids = [];
    $thumb = (int) get_post_thumbnail_id($product_id);
    if ($thumb > 0) {
        $ids[] = $thumb;
    }

    if (nw_fuel_is_woocommerce_active()) {
        $product = wc_get_product($product_id);
        if ($product) {
            foreach ($product->get_gallery_image_ids() as $attachment_id) {
                $attachment_id = (int) $attachment_id;
                if ($attachment_id > 0 && ! in_array($attachment_id, $ids, true)) {
                    $ids[] = $attachment_id;
                }
            }
        }
    } else {
        $gallery = (string) get_post_meta($product_id, '_product_image_gallery', true);
        foreach (array_filter(array_map('intval', explode(',', $gallery))) as $attachment_id) {
            if ($attachment_id > 0 && ! in_array($attachment_id, $ids, true)) {
                $ids[] = $attachment_id;
            }
        }
    }

    return array_slice($ids, 0, NW_FUEL_PRODUCT_PHOTOS_MAX);
}

/**
 * Product gallery URLs (featured + Woo gallery, with remote fallbacks).
 *
 * @return string[]
 */
function nw_fuel_product_gallery_urls(int $product_id): array
{
    $gallery = [];

    foreach (nw_fuel_product_photo_ids($product_id) as $attachment_id) {
        $url = wp_get_attachment_image_url($attachment_id, 'large');
        if ($url) {
            $gallery[] = $url;
        }
    }

    if ($gallery === []) {
        $from_remote = [];
        $remote      = (string) get_post_meta($product_id, '_nw_remote_image', true);
        if ($remote !== '') {
            $from_remote[] = nw_fuel_resolve_image_url($remote);
        }
        foreach (nw_fuel_json_meta($product_id, '_nw_remote_gallery') as $url) {
            if (is_string($url) && $url !== '') {
                $from_remote[] = nw_fuel_resolve_image_url($url);
            }
        }
        $gallery = array_values(array_unique(array_filter($from_remote)));
    }

    $gallery = array_slice(array_values(array_unique($gallery)), 0, NW_FUEL_PRODUCT_PHOTOS_MAX);

    if ($gallery === []) {
        $gallery[] = nw_fuel_product_placeholder_url();
    }

    return $gallery;
}

/**
 * Service icon SVG markup.
 */
function nw_fuel_service_icon_svg(string $icon): string
{
    $attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    return match ($icon) {
        'Gauge' => '<svg ' . $attrs . '><path d="M5 14.5a7 7 0 0 1 14 0"/><path d="M12 14.5V9"/><circle cx="12" cy="14.5" r="1.25"/><path d="M9.5 7.8 7.5 5.5M14.5 7.8l2-2.3"/></svg>',
        'Zap' => '<svg ' . $attrs . '><path d="M13 2.5 6 13.5h5.5L10 21.5l7-10H12l1-9z"/></svg>',
        'Cog' => '<svg ' . $attrs . '><circle cx="12" cy="12" r="2.75"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.55 1.55M16.85 16.85l1.55 1.55M5.6 18.4l1.55-1.55M16.85 7.15l1.55-1.55"/></svg>',
        'Wrench' => '<svg ' . $attrs . '><path d="M14.4 6.6a3.75 3.75 0 0 0-5.3 5.3L3.5 17.5V21h3.5l5.6-5.6a3.75 3.75 0 0 0 5.3-5.3l-2 2-2.8-2.8 2-2z"/></svg>',
        'Cpu' => '<svg ' . $attrs . '><rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M9.5 7V4.5M12 7V4.5M14.5 7V4.5M9.5 17v2.5M12 17v2.5M14.5 17v2.5M7 9.5H4.5M7 12H4.5M7 14.5H4.5M17 9.5h2.5M17 12h2.5M17 14.5h2.5"/></svg>',
        'Droplets' => '<svg ' . $attrs . '><path d="M12 3.5c-2.8 4-5.5 6.8-5.5 10a5.5 5.5 0 0 0 11 0c0-3.2-2.7-6-5.5-10z"/></svg>',
        'Wind' => '<svg ' . $attrs . '><circle cx="12" cy="12" r="2"/><path d="M12 4.5c1.6 1.6 2.2 3.8 1.4 5.8M12 19.5c-1.6-1.6-2.2-3.8-1.4-5.8M4.5 12c1.6-1.6 3.8-2.2 5.8-1.4M19.5 12c-1.6 1.6-3.8 2.2-5.8 1.4"/></svg>',
        'Package' => '<svg ' . $attrs . '><path d="M12 3.5 19.5 7v10L12 21l-7.5-4V7L12 3.5z"/><path d="M12 12.5 19.5 7M12 12.5V21M12 12.5 4.5 7"/></svg>',
        'Search' => '<svg ' . $attrs . '><circle cx="11" cy="11" r="5.25"/><path d="M20 20l-4.2-4.2"/></svg>',
        default => '<svg ' . $attrs . '><circle cx="12" cy="12" r="2.75"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21"/></svg>',
    };
}

/**
 * Category icon SVG for hero/catalog UI.
 */
function nw_fuel_product_category_icon_svg(string $category): string
{
    $attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    return match ($category) {
        'all' => '<svg ' . $attrs . '><path d="M8.5 3.5 12 2l3.5 1.5L19 6v5.5l-1.5 3.5L12 21l-5.5-6L5 11.5V6l3.5-2.5z"/><path d="M12 2v6.5M5 6l7 3.5M19 6l-7 3.5M12 21V14.5"/></svg>',
        'Injectors' => '<svg ' . $attrs . '><path d="M8 4.5h8v3.5l-1.5 9H9.5l-1.5-9V4.5z"/><path d="M10 8h4M9.5 14h5"/></svg>',
        'Pumps & Injectors' => '<svg ' . $attrs . '><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2"/><path d="M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4l1.4-1.4M17 7l1.4-1.4"/></svg>',
        'Turbos' => '<svg ' . $attrs . '><circle cx="12" cy="12" r="2.25"/><path d="M12 4.5v2.25M12 17.25V19.5M4.5 12h2.25M17.25 12H19.5"/><path d="M7.1 7.1l1.6 1.6M15.3 15.3l1.6 1.6M7.1 16.9l1.6-1.6M15.3 8.7l1.6-1.6"/></svg>',
        'Component Parts', 'Engine Parts' => '<svg ' . $attrs . '><path d="M14.4 6.6a3.75 3.75 0 0 0-5.3 5.3L3.5 17.5V21h3.5l5.6-5.6a3.75 3.75 0 0 0 5.3-5.3l-2 2-2.8-2.8 2-2z"/></svg>',
        'Fuel Additives' => '<svg ' . $attrs . '><path d="M12 3.5c-2.8 4-5.5 6.8-5.5 10a5.5 5.5 0 0 0 11 0c0-3.2-2.7-6-5.5-10z"/></svg>',
        'Filters' => '<svg ' . $attrs . '><path d="M4 5h16l-6.5 7.5V19l-3 2v-6.5L4 5z"/></svg>',
        'brands' => '<svg ' . $attrs . '><path d="M4 7.5h16v9H4v-9z"/><path d="M8 7.5V5.5h8v2"/></svg>',
        default => '<svg ' . $attrs . '><path d="M12 3.5 19.5 7v10L12 21l-7.5-4V7L12 3.5z"/></svg>',
    };
}

/**
 * Social icon SVG.
 */
function nw_fuel_social_icon_svg(string $platform): string
{
    $attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"';

    return match (strtolower($platform)) {
        'facebook' => '<svg ' . $attrs . '><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H8.1v-3h2.3V9.8c0-2.3 1.3-3.5 3.4-3.5 1 0 2 .2 2 .2v2.2h-1.1c-1.1 0-1.5.7-1.5 1.4V12h2.6l-.4 2.6h-2.2v7A10 10 0 0 0 22 12Z"/></svg>',
        'twitter', 'x' => '<svg ' . $attrs . '><path d="M18.9 3H22l-7.1 8.1L23 21h-6.6l-5.2-6.8L5.5 21H1.4l7.6-8.7L1 3h6.8l4.7 6.2L18.9 3Zm-1.2 16.2h1.8L7.2 4.9H5.3l12.4 14.3Z"/></svg>',
        'instagram' => '<svg ' . $attrs . '><path d="M7.5 3h9A4.5 4.5 0 0 1 21 7.5v9a4.5 4.5 0 0 1-4.5 4.5h-9A4.5 4.5 0 0 1 3 16.5v-9A4.5 4.5 0 0 1 7.5 3Zm0 2A2.5 2.5 0 0 0 5 7.5v9A2.5 2.5 0 0 0 7.5 19h9a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 16.5 5h-9ZM12 8.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 0 1 12 8.5Zm0 2A1.5 1.5 0 1 0 13.5 12 1.5 1.5 0 0 0 12 10.5ZM17.2 7a1 1 0 1 1-1 1 1 1 0 0 1 1-1Z"/></svg>',
        'linkedin' => '<svg ' . $attrs . '><path d="M6.5 8.5h3v9h-3v-9ZM8 6.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5ZM18 17.5h-3v-4.4c0-1 0-2.3-1.4-2.3-1.4 0-1.7 1.1-1.7 2.2v4.5H9V8.5h3v1.2h.1c.4-.8 1.5-1.7 3.1-1.7 3.3 0 3.9 2.2 3.9 5v4.5Z"/></svg>',
        default => '<svg ' . $attrs . '><path d="M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9Zm0 2a7 7 0 1 1-7 7 7 7 0 0 1 7-7Z"/></svg>',
    };
}

/**
 * Detect active nav section from current request.
 */
function nw_fuel_nav_state(): array
{
    $products_url = trailingslashit(nw_fuel_products_url());
    $current = trailingslashit(home_url(add_query_arg([], $GLOBALS['wp']->request ?? '')));

    if (is_front_page()) {
        $path = '/';
    } else {
        $path = wp_parse_url(get_permalink(), PHP_URL_PATH) ?: '/';
    }

    return [
        'path'              => $path,
        'products_active'   => is_post_type_archive('product') || is_singular('product') || is_tax(get_object_taxonomies('product')),
        'services_active'   => is_post_type_archive('nw_service') || is_singular('nw_service'),
        'technical_active'  => is_post_type_archive('nw_tech_resource') || is_singular('nw_tech_resource'),
        'blog_active'       => is_home() || is_singular('post') || is_category(),
        'gallery_active'    => is_post_type_archive('nw_gallery_item') || is_singular('nw_gallery_item'),
        'catalog_active'    => is_post_type_archive('nw_catalog_item') || is_singular('nw_catalog_item'),
        'products_url'      => $products_url,
    ];
}

/**
 * Render inventory block.
 *
 * @param array{price: float|int, quantity: int, currency?: string} $inventory
 */
function nw_fuel_render_inventory(array $inventory, string $variant = 'card'): void
{
    $currency   = $inventory['currency'] ?? 'CAD';
    $quantity   = (int) ($inventory['quantity'] ?? 0);
    $status     = nw_fuel_stock_status($quantity);
    $show_price = ! empty($inventory['show_price']);
    $price      = (float) ($inventory['display_price'] ?? $inventory['price'] ?? 0);
    $qty_label  = nw_fuel_quantity_display($quantity);

    if ($variant === 'card') {
        echo '<div class="inventory inventory--card">';
        if ($show_price && $price > 0) {
            echo '<p class="inventory__price">' . esc_html(nw_fuel_format_price($price, $currency)) . '</p>';
        } else {
            echo '<p class="inventory__quote">' . esc_html__('Call for price', 'nw-fuel') . '</p>';
        }
        echo '<span class="inventory__badge inventory__badge--' . esc_attr($status) . '">' . esc_html(nw_fuel_quantity_label($quantity)) . '</span>';
        echo '</div>';
        return;
    }

    echo '<div class="inventory inventory--detail">';
    echo '<p class="inventory__label">' . esc_html__('Price', 'nw-fuel') . '</p>';
    if ($show_price && $price > 0) {
        echo '<p class="inventory__price inventory__price--lg">' . esc_html(nw_fuel_format_price($price, $currency)) . '</p>';
    } else {
        echo '<p class="product-buy__quote">' . esc_html__('Call for price and availability', 'nw-fuel') . '</p>';
    }
    echo '<div class="inventory__grid">';
    echo '<div><p class="inventory__label">' . esc_html__('Quantity', 'nw-fuel') . '</p><p class="inventory__qty">' . esc_html($qty_label) . '</p></div>';
    echo '<div><p class="inventory__label">' . esc_html__('Availability', 'nw-fuel') . '</p><span class="inventory__badge inventory__badge--' . esc_attr($status) . '">' . esc_html(nw_fuel_stock_label($quantity)) . '</span></div>';
    echo '</div>';
    if ($status === 'out_of_stock') {
        echo '<p class="inventory__note">' . esc_html__('Out of stock right now. Call us to check lead time or place a special order.', 'nw-fuel') . '</p>';
    }
    echo '</div>';
}

/**
 * Woo inventory array from product.
 *
 * @return array{price: float, quantity: int, currency: string}|null
 */
function nw_fuel_product_inventory(int $product_id): ?array
{
    if (! nw_fuel_is_woocommerce_active()) {
        return null;
    }

    $product = wc_get_product($product_id);
    if (! $product) {
        return null;
    }

    $price = nw_fuel_product_price_for_user($product_id);
    if ($price <= 0) {
        $fallback = (float) $product->get_regular_price();
        if ($fallback <= 0) {
            $fallback = (float) $product->get_price();
        }
        $price = $fallback;
    }

    $quantity = (int) $product->get_stock_quantity();
    if ($price <= 0 && ! $product->managing_stock() && $quantity <= 0) {
        return null;
    }

    return [
        'price'         => $price,
        'display_price' => $price,
        'show_price'    => $price > 0,
        'quantity'      => $quantity,
        'currency'      => 'CAD',
    ];
}

/**
 * Form feedback query flag.
 */
function nw_fuel_form_feedback(string $key): ?string
{
    if (! isset($_GET[$key])) {
        return null;
    }
    $value = sanitize_key((string) wp_unslash($_GET[$key]));
    return in_array($value, ['success', 'error'], true) ? $value : null;
}
