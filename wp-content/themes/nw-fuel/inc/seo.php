<?php
/**
 * SEO meta tags and JSON-LD schema (matches static head.php).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_filter('document_title_parts', 'nw_fuel_filter_document_title_parts');
add_filter('pre_get_document_title', 'nw_fuel_pre_get_document_title', 20);
add_action('wp_head', 'nw_fuel_output_seo_tags', 2);
add_action('wp_head', 'nw_fuel_output_json_ld', 5);
add_action('after_setup_theme', 'nw_fuel_disable_default_canonical');

/**
 * Use theme canonical tags only (avoid duplicate link rel=canonical).
 */
function nw_fuel_disable_default_canonical(): void
{
    remove_action('wp_head', 'rel_canonical');
}

/**
 * Default SEO strings matching the static site create_meta() calls.
 *
 * @return array{title: string, description: string}
 */
function nw_fuel_default_seo_for_context(): array
{
    if (is_front_page()) {
        return [
            'title'       => 'Diesel Injection Repair and Parts | NW Fuel Surrey BC',
            'description' => 'Bosch authorized diesel injection shop in Surrey, BC. Injector testing, pump rebuilds, common rail repair, and OEM parts since 1968.',
        ];
    }
    if (is_page('about')) {
        return [
            'title'       => 'About NW Fuel Injection Services | Since 1968',
            'description' => 'NW Fuel Injection Services in Surrey, BC. Bosch authorized diesel injection shop serving repair shops since 1968.',
        ];
    }
    if (is_page('history-of-nw-fuel')) {
        return [
            'title'       => 'History of NW Fuel',
            'description' => 'The history of NW Fuel Injection Services — from Hans Rusch’s 1968 shop in New Westminster to a Bosch authorized diesel injection facility in Surrey, BC.',
        ];
    }
    if (is_page('contact')) {
        return [
            'title'       => 'Contact NW Fuel Injection Services Surrey BC',
            'description' => 'Call NW Fuel in Surrey, BC at (604) 882-3835. Request a quote for injector testing, pump rebuilds, and diesel parts. Open Monday to Friday, 8 AM to 4:30 PM.',
        ];
    }
    if (is_home()) {
        return [
            'title'       => 'Diesel Injection Blog & Technical Articles',
            'description' => 'Technical articles and diesel fuel injection expertise from NW Fuel Injection Services in Surrey, BC.',
        ];
    }
    if (is_post_type_archive('nw_service')) {
        return [
            'title'       => 'Diesel Injection Services Surrey BC',
            'description' => 'Injector testing, common rail repair, pump rebuilds, turbo service, and diesel parts from a Bosch authorized shop in Surrey, BC.',
        ];
    }
    if (is_post_type_archive('nw_tech_resource')) {
        return [
            'title'       => 'Technical Resources',
            'description' => 'Installation guides, filter references, and diesel injection technical notes from NW Fuel Injection Services in Surrey, BC.',
        ];
    }
    if (is_post_type_archive('nw_gallery_item')) {
        return [
            'title'       => 'Gallery | NW Fuel Surrey BC',
            'description' => 'Photos and videos from the NW Fuel Injection Services shop in Surrey, BC.',
        ];
    }
    if (is_post_type_archive('nw_catalog_item')) {
        return [
            'title'       => 'Catalog | NW Fuel Surrey BC',
            'description' => 'Bosch, diesel injection, and parts catalogs from NW Fuel Injection Services in Surrey, BC. Enter your email to download a PDF.',
        ];
    }
    if (function_exists('is_shop') && is_shop()) {
        return [
            'title'       => 'Diesel Parts Catalog | NW Fuel Surrey BC',
            'description' => 'OEM diesel injectors, Pumps & Injectors, turbos, filters, and component parts. Request a quote from our Surrey, BC shop.',
        ];
    }

    return [
        'title'       => '',
        'description' => '',
    ];
}

/**
 * Resolve SEO title + description for the current request.
 *
 * @return array{title: string, description: string, canonical: string, image: string, og_type: string}
 */
function nw_fuel_resolve_seo(): array
{
    $defaults  = nw_fuel_default_seo_for_context();
    $title     = $defaults['title'];
    $desc      = $defaults['description'];
    $canonical = '';
    $image     = '';
    $og_type   = 'website';

    if (is_singular()) {
        $post_id = get_queried_object_id();
        $custom_title = (string) get_post_meta($post_id, '_nw_meta_title', true);
        $custom_desc  = (string) get_post_meta($post_id, '_nw_meta_description', true);

        if ($custom_title !== '') {
            $title = $custom_title;
        } elseif ($title === '') {
            $title = get_the_title($post_id);
        }

        if ($custom_desc !== '') {
            $desc = $custom_desc;
        } elseif ($desc === '') {
            $excerpt = get_the_excerpt($post_id);
            if ($excerpt === '') {
                $excerpt = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
            }
            $desc = wp_trim_words($excerpt, 32, '…');
        }

        $canonical = get_permalink($post_id) ?: '';
        $og_type   = is_singular('post') ? 'article' : 'website';

        if (has_post_thumbnail($post_id)) {
            $image = (string) get_the_post_thumbnail_url($post_id, 'large');
        } else {
            $remote = (string) get_post_meta($post_id, '_nw_remote_image', true);
            if ($remote !== '') {
                $image = nw_fuel_resolve_image_url($remote);
            } elseif (is_singular('product')) {
                $image = nw_fuel_product_placeholder_url();
            }
        }
    } elseif (is_post_type_archive() || (function_exists('is_shop') && is_shop()) || is_home()) {
        $canonical = get_pagenum_link(1);
        if (is_home()) {
            $posts_page = (int) get_option('page_for_posts');
            if ($posts_page) {
                $canonical = get_permalink($posts_page) ?: $canonical;
                $custom_title = (string) get_post_meta($posts_page, '_nw_meta_title', true);
                $custom_desc  = (string) get_post_meta($posts_page, '_nw_meta_description', true);
                if ($custom_title !== '') {
                    $title = $custom_title;
                }
                if ($custom_desc !== '') {
                    $desc = $custom_desc;
                }
            }
        }
    } elseif (is_front_page()) {
        $canonical = home_url('/');
        $front_id  = (int) get_option('page_on_front');
        if ($front_id) {
            $custom_title = (string) get_post_meta($front_id, '_nw_meta_title', true);
            $custom_desc  = (string) get_post_meta($front_id, '_nw_meta_description', true);
            if ($custom_title !== '') {
                $title = $custom_title;
            }
            if ($custom_desc !== '') {
                $desc = $custom_desc;
            }
        }
    }

    if ($canonical === '') {
        $canonical = home_url(add_query_arg([]));
    }

    if ($image === '') {
        $images = nw_fuel_images();
        $image  = (string) ($images['hero'] ?? '');
        if ($image !== '') {
            $image = nw_fuel_resolve_image_url($image);
        }
    }

    if ($title === '') {
        $title = wp_get_document_title();
    }

    return [
        'title'       => $title,
        'description' => $desc,
        'canonical'   => $canonical,
        'image'       => $image,
        'og_type'     => $og_type,
    ];
}

/**
 * Prefer custom meta titles (without appending site name twice awkwardly).
 *
 * @param array<string, string> $parts
 * @return array<string, string>
 */
function nw_fuel_filter_document_title_parts(array $parts): array
{
    if (function_exists('nw_fuel_rank_math_owns_seo') && nw_fuel_rank_math_owns_seo()) {
        return $parts;
    }

    $seo = nw_fuel_resolve_seo();
    if ($seo['title'] === '') {
        return $parts;
    }

    return ['title' => $seo['title']];
}

/**
 * Prefer resolved SEO title as the full document title (no site-name suffix).
 */
function nw_fuel_pre_get_document_title(string $title): string
{
    if (function_exists('nw_fuel_rank_math_owns_seo') && nw_fuel_rank_math_owns_seo()) {
        return $title;
    }

    $seo = nw_fuel_resolve_seo();
    return $seo['title'] !== '' ? $seo['title'] : $title;
}

/**
 * Output description, canonical, Open Graph, Twitter cards.
 */
function nw_fuel_output_seo_tags(): void
{
    if (is_admin()) {
        return;
    }

    if (function_exists('nw_fuel_rank_math_owns_seo') && nw_fuel_rank_math_owns_seo()) {
        return;
    }

    $seo = nw_fuel_resolve_seo();
    if ($seo['description'] !== '') {
        echo '<meta name="description" content="' . esc_attr($seo['description']) . '">' . "\n";
    }
    if ($seo['canonical'] !== '') {
        echo '<link rel="canonical" href="' . esc_url($seo['canonical']) . '">' . "\n";
    }
    echo '<meta property="og:title" content="' . esc_attr($seo['title']) . '">' . "\n";
    if ($seo['description'] !== '') {
        echo '<meta property="og:description" content="' . esc_attr($seo['description']) . '">' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url($seo['canonical']) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($seo['og_type']) . '">' . "\n";
    echo '<meta property="og:locale" content="en_CA">' . "\n";
    if ($seo['image'] !== '') {
        echo '<meta property="og:image" content="' . esc_url($seo['image']) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}

/**
 * LocalBusiness / AutoRepair schema.
 *
 * @return array<string, mixed>
 */
function nw_fuel_local_business_schema(): array
{
    $business = nw_fuel_business();
    $address  = is_array($business['address'] ?? null) ? $business['address'] : [];

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'AutoRepair',
        'name'     => (string) ($business['name'] ?? 'NW Fuel Injection Services'),
        'url'      => home_url('/'),
        'telephone'=> (string) ($business['phone'] ?? ''),
        'email'    => (string) ($business['email'] ?? ''),
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => (string) ($address['street'] ?? ''),
            'addressLocality' => (string) ($address['city'] ?? ''),
            'addressRegion'   => (string) ($address['province'] ?? ''),
            'postalCode'      => (string) ($address['postalCode'] ?? ''),
            'addressCountry'  => (string) ($address['country'] ?? 'CA'),
        ],
        'foundingDate' => '1968',
    ];
}

/**
 * BreadcrumbList schema.
 *
 * @param list<array{name: string, url?: string}> $items
 * @return array<string, mixed>
 */
function nw_fuel_breadcrumb_schema(array $items): array
{
    $list = [];
    foreach ($items as $i => $item) {
        $entry = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => (string) ($item['name'] ?? ''),
        ];
        if (! empty($item['url'])) {
            $entry['item'] = $item['url'];
        }
        $list[] = $entry;
    }

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

/**
 * FAQPage schema.
 *
 * @param list<array{question?: string, answer?: string}> $faqs
 * @return array<string, mixed>|null
 */
function nw_fuel_faq_schema(array $faqs): ?array
{
    $entities = [];
    foreach ($faqs as $faq) {
        if (! is_array($faq)) {
            continue;
        }
        $q = (string) ($faq['question'] ?? '');
        $a = (string) ($faq['answer'] ?? '');
        if ($q === '' || $a === '') {
            continue;
        }
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $a,
            ],
        ];
    }

    if ($entities === []) {
        return null;
    }

    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

/**
 * Service schema for a service post.
 *
 * @return array<string, mixed>
 */
function nw_fuel_service_schema(WP_Post $post): array
{
    $business = nw_fuel_business();
    $address  = is_array($business['address'] ?? null) ? $business['address'] : [];
    $desc     = (string) get_post_meta($post->ID, '_nw_meta_description', true);
    if ($desc === '') {
        $desc = (string) $post->post_excerpt;
    }

    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => get_the_title($post),
        'description' => $desc,
        'provider'    => [
            '@type'     => 'AutoRepair',
            'name'      => (string) ($business['name'] ?? 'NW Fuel Injection Services'),
            'telephone' => (string) ($business['phone'] ?? ''),
            'address'   => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => (string) ($address['street'] ?? ''),
                'addressLocality' => (string) ($address['city'] ?? ''),
                'addressRegion'   => (string) ($address['province'] ?? ''),
                'postalCode'      => (string) ($address['postalCode'] ?? ''),
                'addressCountry'  => (string) ($address['country'] ?? 'CA'),
            ],
        ],
        'areaServed' => [
            '@type' => 'AdministrativeArea',
            'name'  => 'British Columbia',
        ],
        'url' => get_permalink($post) ?: '',
    ];
}

/**
 * Echo JSON-LD blocks for the current view.
 */
function nw_fuel_output_json_ld(): void
{
    if (is_admin()) {
        return;
    }

    // Sitewide business entity (same as static local_business usage on key pages).
    echo nw_fuel_json_ld(nw_fuel_local_business_schema()) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    if (is_singular('nw_service')) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            echo nw_fuel_json_ld(nw_fuel_breadcrumb_schema([
                ['name' => __('Services', 'nw-fuel'), 'url' => get_post_type_archive_link('nw_service') ?: ''],
                ['name' => get_the_title($post), 'url' => get_permalink($post) ?: ''],
            ])) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo nw_fuel_json_ld(nw_fuel_service_schema($post)) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            $faqs = nw_fuel_json_meta($post->ID, '_nw_service_faqs');
            $faq_schema = nw_fuel_faq_schema($faqs);
            if ($faq_schema) {
                echo nw_fuel_json_ld($faq_schema) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
        return;
    }

    if (is_singular('nw_tech_resource')) {
        if (function_exists('nw_fuel_rank_math_owns_seo') && nw_fuel_rank_math_owns_seo()) {
            return;
        }

        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            echo nw_fuel_json_ld(nw_fuel_breadcrumb_schema([
                ['name' => __('Technical Resources', 'nw-fuel'), 'url' => get_post_type_archive_link('nw_tech_resource') ?: ''],
                ['name' => get_the_title($post), 'url' => get_permalink($post) ?: ''],
            ])) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        return;
    }

    if (is_singular('product')) {
        if (function_exists('nw_fuel_rank_math_owns_seo') && nw_fuel_rank_math_owns_seo()) {
            return;
        }

        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            echo nw_fuel_json_ld(nw_fuel_breadcrumb_schema([
                ['name' => __('Products', 'nw-fuel'), 'url' => nw_fuel_products_url()],
                ['name' => get_the_title($post), 'url' => get_permalink($post) ?: ''],
            ])) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            $faqs = nw_fuel_json_meta($post->ID, '_nw_product_faqs');
            $faq_schema = nw_fuel_faq_schema($faqs);
            if ($faq_schema) {
                echo nw_fuel_json_ld($faq_schema) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
        return;
    }

    if (is_singular('post')) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $blog_url = get_permalink((int) get_option('page_for_posts')) ?: home_url('/blog/');
            echo nw_fuel_json_ld(nw_fuel_breadcrumb_schema([
                ['name' => __('Blog', 'nw-fuel'), 'url' => $blog_url],
                ['name' => get_the_title($post), 'url' => get_permalink($post) ?: ''],
            ])) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
