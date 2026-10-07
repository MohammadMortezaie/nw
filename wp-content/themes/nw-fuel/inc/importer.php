<?php
/**
 * Seed data importer (Tools > NW Fuel Import).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'nw_fuel_register_importer_page');
add_action('admin_post_nw_fuel_run_import', 'nw_fuel_run_import');
add_action('admin_post_nw_fuel_import_tech_resources', 'nw_fuel_run_tech_resources_import');

/**
 * Register importer under Tools.
 */
function nw_fuel_register_importer_page(): void
{
    add_management_page(
        __('NW Fuel Import', 'nw-fuel'),
        __('NW Fuel Import', 'nw-fuel'),
        'manage_options',
        'nw-fuel-import',
        'nw_fuel_render_importer_page'
    );
}

/**
 * Render importer admin screen.
 */
function nw_fuel_render_importer_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $log = get_transient('nw_fuel_import_log');
    delete_transient('nw_fuel_import_log');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('NW Fuel Data Import', 'nw-fuel'); ?></h1>
        <p><?php esc_html_e('Imports bundled seed JSON into WooCommerce products, taxonomies, services, technical resources, blog posts, pages, and theme settings. Safe to run multiple times — records are matched by slug or SKU.', 'nw-fuel'); ?></p>
        <h2><?php esc_html_e('Technical resources only', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('Creates or updates the five shop guides (Bosch urea filter, Powerstroke, Cummins, Duramax install, Duramax injector) without touching products or other content.', 'nw-fuel'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('nw_fuel_import_tech_resources', 'nw_fuel_import_tech_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_import_tech_resources">
            <?php submit_button(__('Import technical resources', 'nw-fuel'), 'primary', 'submit', false); ?>
        </form>
        <hr>
        <h2><?php esc_html_e('Full import', 'nw-fuel'); ?></h2>
        <p><strong><?php esc_html_e('Do not use this on a live catalog.', 'nw-fuel'); ?></strong> <?php esc_html_e('It also restores seed products and pages.', 'nw-fuel'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('nw_fuel_run_import', 'nw_fuel_import_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_run_import">
            <?php submit_button(__('Run full import', 'nw-fuel'), 'secondary'); ?>
        </form>
        <?php if (is_array($log) && $log !== []) : ?>
            <h2><?php esc_html_e('Import Log', 'nw-fuel'); ?></h2>
            <ul>
                <?php foreach ($log as $line) : ?>
                    <li><?php echo esc_html($line); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <h2><?php esc_html_e('After Import', 'nw-fuel'); ?></h2>
        <ol>
            <li><?php esc_html_e('Visit Settings → Permalinks and click Save to flush rewrite rules.', 'nw-fuel'); ?></li>
            <li><?php esc_html_e('Confirm WooCommerce → Settings → Products uses the Products page at /products/.', 'nw-fuel'); ?></li>
            <li><?php esc_html_e('Set Settings → Reading → Posts page to the imported Blog page.', 'nw-fuel'); ?></li>
            <li><?php esc_html_e('Menus are imported to Appearance → Menus (Primary + Footer). Edit anytime.', 'nw-fuel'); ?></li>
            <li><?php esc_html_e('Configure a static front page if desired (Home page template is used automatically for front page).', 'nw-fuel'); ?></li>
        </ol>
    </div>
    <?php
}

/**
 * Execute import.
 */
function nw_fuel_run_import(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }

    if (! isset($_POST['nw_fuel_import_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_import_nonce'])), 'nw_fuel_run_import')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $log = [];
    nw_fuel_register_post_types();
    nw_fuel_register_product_attributes();

    nw_fuel_import_settings($log);
    nw_fuel_import_pages($log);
    nw_fuel_import_services($log);
    nw_fuel_import_technical_resources($log);
    nw_fuel_import_blog_posts($log);

    if (nw_fuel_is_woocommerce_active()) {
        nw_fuel_configure_permalinks();
        nw_fuel_ensure_woocommerce_pages();
        nw_fuel_import_products($log);
    } else {
        $log[] = 'Skipped products: WooCommerce is not active.';
    }

    nw_fuel_import_menus($log);
    nw_fuel_seed_page_content_meta();
    $log[] = 'Seeded About/Contact page content fields (if empty).';

    flush_rewrite_rules(false);
    set_transient('nw_fuel_import_log', $log, MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-import&imported=1'));
    exit;
}

/**
 * Import only the five technical resource guides.
 */
function nw_fuel_run_tech_resources_import(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }

    if (! isset($_POST['nw_fuel_import_tech_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_import_tech_nonce'])), 'nw_fuel_import_tech_resources')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $log = [];
    nw_fuel_register_post_types();
    nw_fuel_import_technical_resources($log);
    flush_rewrite_rules(false);
    $log[] = 'Technical resources import finished. Products and other content were not changed.';
    set_transient('nw_fuel_import_log', $log, MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('tools.php?page=nw-fuel-import&imported=1'));
    exit;
}

/**
 * Load JSON seed file.
 *
 * @return array<string, mixed>|array<int, mixed>
 */
function nw_fuel_seed_json(string $filename): array
{
    $path = NW_FUEL_DIR . '/seed-data/' . $filename;
    if (! file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/**
 * Upsert post by slug.
 */
function nw_fuel_upsert_post(array $args): int
{
    $slug = sanitize_title($args['post_name'] ?? '');
    $type = $args['post_type'] ?? 'post';
    $existing = get_page_by_path($slug, OBJECT, $type);

    $postarr = [
        'post_title'   => $args['post_title'] ?? '',
        'post_name'    => $slug,
        'post_content' => $args['post_content'] ?? '',
        'post_excerpt' => $args['post_excerpt'] ?? '',
        'post_status'  => $args['post_status'] ?? 'publish',
        'post_type'    => $type,
        'menu_order'   => (int) ($args['menu_order'] ?? 0),
    ];

    if ($existing instanceof WP_Post) {
        $postarr['ID'] = $existing->ID;
        $post_id = wp_update_post($postarr, true);
    } else {
        $post_id = wp_insert_post($postarr, true);
    }

    return is_wp_error($post_id) ? 0 : (int) $post_id;
}

/**
 * Import global settings.
 *
 * @param string[] $log
 */
function nw_fuel_import_settings(array &$log): void
{
    $business = nw_fuel_seed_json('business.json');
    $company  = nw_fuel_seed_json('company.json');
    $images   = nw_fuel_seed_json('images.json');
    $faqs     = nw_fuel_seed_json('faqs.json');

    $settings = nw_fuel_get_settings();
    if ($business !== []) {
        $settings['business'] = array_replace_recursive($settings['business'], $business);
    }
    if ($company !== []) {
        $settings['company'] = $company;
    }
    if ($images !== []) {
        $settings['images'] = $images;
    }
    $settings['home_faqs']    = $faqs['homeFaqs'] ?? $settings['home_faqs'];
    $settings['contact_faqs'] = $faqs['contactFaqs'] ?? $settings['contact_faqs'];
    $settings['home']['featured_service_slugs'] = [
        'mechanical-injector-rebuild',
        'heui-eui-testing',
        'common-rail-repair',
        'fuel-pump-rebuild',
    ];

    update_option('nw_fuel_settings', $settings);
    $log[] = 'Imported theme settings, company data, images, and FAQs.';
}

/**
 * Import core pages.
 *
 * @param string[] $log
 */
function nw_fuel_import_pages(array &$log): void
{
    $pages = [
        'home'    => [
            'title'       => 'Home',
            'content'     => '<!-- NW Fuel homepage uses front-page.php -->',
            'metaTitle'   => 'Diesel Injection Repair and Parts | NW Fuel Surrey BC',
            'metaDescription' => 'Bosch authorized diesel injection shop in Surrey, BC. Injector testing, pump rebuilds, common rail repair, and OEM parts since 1968.',
        ],
        'about'   => [
            'title'       => 'About Us',
            'content'     => '<p>About NW Fuel Injection Services.</p>',
            'metaTitle'   => 'About NW Fuel Injection Services | Since 1968',
            'metaDescription' => 'NW Fuel Injection Services in Surrey, BC. Bosch authorized diesel injection shop serving repair shops since 1968.',
        ],
        'contact' => [
            'title'       => 'Contact Us',
            'content'     => '<p>Contact our Surrey shop.</p>',
            'metaTitle'   => 'Contact NW Fuel Injection Services Surrey BC',
            'metaDescription' => 'Call NW Fuel in Surrey, BC at (604) 882-3835. Request a quote for injector testing, pump rebuilds, and diesel parts. Open Monday to Friday, 8 AM to 4:30 PM.',
        ],
        'blog'    => [
            'title'       => 'Blog',
            'content'     => '',
            'metaTitle'   => 'Diesel Injection Blog & Technical Articles',
            'metaDescription' => 'Technical articles and diesel fuel injection expertise from NW Fuel Injection Services in Surrey, BC.',
        ],
        'history-of-nw-fuel' => [
            'title'       => 'History of NW Fuel',
            'content'     => '',
            'metaTitle'   => 'History of NW Fuel',
            'metaDescription' => 'The history of NW Fuel Injection Services — from Hans Rusch’s 1968 shop in New Westminster to a Bosch authorized diesel injection facility in Surrey, BC.',
        ],
    ];

    $home_id = 0;
    foreach ($pages as $slug => $page) {
        $id = nw_fuel_upsert_post([
            'post_title'   => $page['title'],
            'post_name'    => $slug,
            'post_content' => $page['content'],
            'post_type'    => 'page',
        ]);
        if ($id) {
            update_post_meta($id, '_nw_meta_title', sanitize_text_field($page['metaTitle'] ?? ''));
            update_post_meta($id, '_nw_meta_description', sanitize_textarea_field($page['metaDescription'] ?? ''));
        }
        if ($slug === 'home') {
            $home_id = $id;
        }
        if ($slug === 'blog' && $id) {
            update_option('page_for_posts', $id);
        }
    }

    if ($home_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home_id);
    }

    $about = get_page_by_path('about');
    if ($about instanceof WP_Post) {
        update_post_meta($about->ID, '_wp_page_template', 'page-about.php');
    }
    $contact = get_page_by_path('contact');
    if ($contact instanceof WP_Post) {
        update_post_meta($contact->ID, '_wp_page_template', 'page-contact.php');
    }
    $history = get_page_by_path('history-of-nw-fuel');
    if ($history instanceof WP_Post) {
        update_post_meta($history->ID, '_wp_page_template', 'page-history.php');
    }

    $log[] = 'Imported/updated Home, About, Contact, History, and Blog pages.';
}

/**
 * Import services CPT.
 *
 * @param string[] $log
 */
function nw_fuel_import_services(array &$log): void
{
    $services = nw_fuel_seed_json('services.json');
    $count = 0;
    foreach ($services as $index => $service) {
        if (! is_array($service)) {
            continue;
        }
        $id = nw_fuel_upsert_post([
            'post_title'   => $service['title'] ?? '',
            'post_name'    => $service['slug'] ?? '',
            'post_excerpt' => $service['shortDescription'] ?? '',
            'post_content' => $service['description'] ?? '',
            'post_type'    => 'nw_service',
            'menu_order'   => $index,
        ]);
        if (! $id) {
            continue;
        }

        update_post_meta($id, '_nw_icon', sanitize_text_field($service['icon'] ?? ''));
        update_post_meta($id, '_nw_hero_image', esc_url_raw($service['heroImage'] ?? $service['image'] ?? ''));
        update_post_meta($id, '_nw_meta_title', sanitize_text_field($service['metaTitle'] ?? ''));
        update_post_meta($id, '_nw_meta_description', sanitize_textarea_field($service['metaDescription'] ?? ''));
        update_post_meta($id, '_nw_benefits', nw_fuel_json_encode_meta($service['benefits'] ?? []));
        update_post_meta($id, '_nw_process', nw_fuel_json_encode_meta($service['process'] ?? []));
        update_post_meta($id, '_nw_pricing', nw_fuel_json_encode_meta($service['pricing'] ?? []));
        update_post_meta($id, '_nw_service_faqs', nw_fuel_json_encode_meta($service['faqs'] ?? []));
        update_post_meta($id, '_nw_related_slugs', nw_fuel_json_encode_meta($service['relatedSlugs'] ?? []));
        update_post_meta($id, '_nw_detail_sections', nw_fuel_json_encode_meta($service['detailSections'] ?? []));
        update_post_meta($id, '_nw_feature_cards', nw_fuel_json_encode_meta($service['featureCards'] ?? []));
        update_post_meta($id, '_nw_video_section', nw_fuel_json_encode_meta($service['videoSection'] ?? new stdClass()));

        nw_fuel_maybe_sideload_featured_image($id, (string) ($service['heroImage'] ?? $service['image'] ?? ''));
        $count++;
    }
    $log[] = "Imported/updated {$count} services.";
}

/**
 * Import technical resources.
 *
 * @param string[] $log
 */
function nw_fuel_import_technical_resources(array &$log): void
{
    $resources = nw_fuel_seed_json('technical-resources.json');
    $count = 0;
    foreach ($resources as $index => $resource) {
        if (! is_array($resource)) {
            continue;
        }
        $id = nw_fuel_upsert_post([
            'post_title'   => $resource['title'] ?? '',
            'post_name'    => $resource['slug'] ?? '',
            'post_excerpt' => $resource['excerpt'] ?? '',
            'post_content' => $resource['description'] ?? ($resource['excerpt'] ?? ''),
            'post_type'    => 'nw_tech_resource',
            'menu_order'   => $index,
        ]);
        if (! $id) {
            continue;
        }
        update_post_meta($id, '_nw_meta_title', sanitize_text_field($resource['metaTitle'] ?? $resource['title'] ?? ''));
        update_post_meta($id, '_nw_meta_description', sanitize_textarea_field($resource['metaDescription'] ?? $resource['excerpt'] ?? ''));
        update_post_meta($id, '_nw_remote_image', esc_url_raw($resource['image'] ?? ''));
        update_post_meta($id, '_nw_image_contain', ! empty($resource['imageContain']) ? '1' : '');
        update_post_meta($id, '_nw_resource_sections', nw_fuel_json_encode_meta($resource['resourceSections'] ?? []));
        update_post_meta($id, '_nw_application_table', nw_fuel_json_encode_meta($resource['applicationTable'] ?? new stdClass()));
        update_post_meta($id, '_nw_downloads', nw_fuel_json_encode_meta($resource['downloads'] ?? []));
        nw_fuel_maybe_sideload_featured_image($id, (string) ($resource['image'] ?? ''));
        $count++;
    }
    $log[] = "Imported/updated {$count} technical resources.";
}

/**
 * Import blog posts.
 *
 * @param string[] $log
 */
function nw_fuel_import_blog_posts(array &$log): void
{
    $posts = nw_fuel_seed_json('blog.json');
    $count = 0;
    foreach ($posts as $post) {
        if (! is_array($post)) {
            continue;
        }
        $date = $post['date'] ?? current_time('mysql');
        $id = nw_fuel_upsert_post([
            'post_title'   => $post['title'] ?? '',
            'post_name'    => $post['slug'] ?? '',
            'post_excerpt' => $post['excerpt'] ?? '',
            'post_content' => '<p>' . esc_html($post['excerpt'] ?? '') . '</p><p>Full article content can be edited in the WordPress dashboard.</p>',
            'post_type'    => 'post',
        ]);
        if (! $id) {
            continue;
        }
        wp_update_post(['ID' => $id, 'post_date' => $date, 'post_date_gmt' => get_gmt_from_date($date)]);

        if (! empty($post['category'])) {
            wp_set_post_terms($id, [(string) $post['category']], 'category', false);
        }
        update_post_meta($id, '_nw_read_time', sanitize_text_field($post['readTime'] ?? ''));
        update_post_meta($id, '_nw_remote_image', esc_url_raw($post['image'] ?? ''));
        nw_fuel_maybe_sideload_featured_image($id, (string) ($post['image'] ?? ''));
        $count++;
    }
    $log[] = "Imported/updated {$count} blog posts.";
}

/**
 * Import WooCommerce products, categories, attributes, inventory.
 *
 * @param string[] $log
 */
function nw_fuel_import_products(array &$log): void
{
    $data      = nw_fuel_seed_json('products.json');
    $inventory = nw_fuel_seed_json('inventory.json');
    $products  = $data['products'] ?? [];
    $categories = $data['productCategories'] ?? [];
    $brands     = $data['brands'] ?? [];
    $vehicleTypes = $data['vehicleTypes'] ?? [];
    $engineTypes  = $data['engineTypes'] ?? [];

    foreach ($categories as $cat_name) {
        if (! term_exists($cat_name, 'product_cat')) {
            wp_insert_term($cat_name, 'product_cat');
        }
    }

    nw_fuel_import_attribute_terms('pa_brand', $brands);
    nw_fuel_import_attribute_terms('pa_vehicle-type', $vehicleTypes);
    nw_fuel_import_attribute_terms('pa_engine-type', $engineTypes);

    $count = 0;
    foreach ($products as $item) {
        if (! is_array($item)) {
            continue;
        }
        $slug = sanitize_title($item['slug'] ?? '');
        $existing = get_page_by_path($slug, OBJECT, 'product');
        $product_id = 0;

        if ($existing instanceof WP_Post) {
            $product_id = $existing->ID;
            wp_update_post([
                'ID'           => $product_id,
                'post_title'   => $item['name'] ?? '',
                'post_content' => $item['description'] ?? '',
                'post_excerpt' => $item['shortDescription'] ?? '',
            ]);
        } else {
            $product_id = wp_insert_post([
                'post_title'   => $item['name'] ?? '',
                'post_name'    => $slug,
                'post_content' => $item['description'] ?? '',
                'post_excerpt' => $item['shortDescription'] ?? '',
                'post_status'  => 'publish',
                'post_type'    => 'product',
            ], true);
            if (is_wp_error($product_id)) {
                continue;
            }
            $product_id = (int) $product_id;
        }

        $product = wc_get_product($product_id);
        if (! $product) {
            continue;
        }

        $part = (string) ($item['partNumber'] ?? '');
        $product->set_sku($part !== '' ? $part : $slug);
        $product->set_catalog_visibility('visible');
        $product->set_status('publish');

        $stock = $inventory[$slug]['quantity'] ?? null;
        $price = $inventory[$slug]['price'] ?? null;
        if ($price !== null) {
            $product->set_regular_price((string) $price);
            $product->set_price((string) $price);
        }
        if ($stock !== null) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $stock);
            $product->set_stock_status((int) $stock > 0 ? 'instock' : 'outofstock');
        }

        $product->save();

        update_post_meta($product_id, '_nw_part_number', sanitize_text_field($part));
        update_post_meta($product_id, '_nw_product_code', sanitize_text_field($item['code'] ?? ''));
        update_post_meta($product_id, '_nw_short_description', sanitize_textarea_field($item['shortDescription'] ?? ''));
        update_post_meta($product_id, '_nw_compatible_vehicles', nw_fuel_json_encode_meta($item['compatibleVehicles'] ?? []));
        update_post_meta($product_id, '_nw_features', nw_fuel_json_encode_meta($item['features'] ?? []));
        update_post_meta($product_id, '_nw_specifications', nw_fuel_json_encode_meta($item['specifications'] ?? []));
        update_post_meta($product_id, '_nw_product_faqs', nw_fuel_json_encode_meta($item['faqs'] ?? []));
        update_post_meta($product_id, '_nw_related_slugs', nw_fuel_json_encode_meta($item['relatedSlugs'] ?? []));
        update_post_meta($product_id, '_nw_remote_image', esc_url_raw($item['image'] ?? ''));
        update_post_meta($product_id, '_nw_remote_gallery', nw_fuel_json_encode_meta($item['gallery'] ?? []));
        update_post_meta($product_id, '_nw_meta_title', sanitize_text_field($item['metaTitle'] ?? ''));
        update_post_meta($product_id, '_nw_meta_description', sanitize_textarea_field($item['metaDescription'] ?? ''));

        if (! empty($item['category'])) {
            wp_set_object_terms($product_id, [(string) $item['category']], 'product_cat', false);
        }
        if (! empty($item['brand'])) {
            wp_set_object_terms($product_id, [(string) $item['brand']], 'pa_brand', false);
        }
        if (! empty($item['vehicleType']) && is_array($item['vehicleType'])) {
            wp_set_object_terms($product_id, array_map('strval', $item['vehicleType']), 'pa_vehicle-type', false);
        }
        if (! empty($item['engineType']) && is_array($item['engineType'])) {
            wp_set_object_terms($product_id, array_map('strval', $item['engineType']), 'pa_engine-type', false);
        }

        nw_fuel_maybe_sideload_featured_image($product_id, (string) ($item['image'] ?? ''));
        $count++;
    }

    $log[] = "Imported/updated {$count} WooCommerce products with categories, attributes, and inventory.";
}

/**
 * Ensure attribute terms exist.
 *
 * @param string[] $terms
 */
function nw_fuel_import_attribute_terms(string $taxonomy, array $terms): void
{
    if (! taxonomy_exists($taxonomy)) {
        return;
    }
    foreach ($terms as $term_name) {
        if (! term_exists($term_name, $taxonomy)) {
            wp_insert_term($term_name, $taxonomy);
        }
    }
}

/**
 * Attempt media sideload; fall back silently to remote URL meta.
 */
function nw_fuel_maybe_sideload_featured_image(int $post_id, string $url): void
{
    if ($url === '' || has_post_thumbnail($post_id)) {
        return;
    }

    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $attachment_id = media_sideload_image($url, $post_id, null, 'id');
    if (is_wp_error($attachment_id)) {
        return;
    }

    set_post_thumbnail($post_id, (int) $attachment_id);
}

/**
 * Create Primary + Footer menus from seed nav + live CPT links.
 *
 * @param string[] $log
 */
function nw_fuel_import_menus(array &$log): void
{
    $primary_name = 'NW Fuel Primary';
    $footer_name  = 'NW Fuel Footer';

    $primary_id = wp_get_nav_menu_object($primary_name);
    $primary_id = $primary_id instanceof WP_Term ? (int) $primary_id->term_id : (int) wp_create_nav_menu($primary_name);

    $footer_id = wp_get_nav_menu_object($footer_name);
    $footer_id = $footer_id instanceof WP_Term ? (int) $footer_id->term_id : (int) wp_create_nav_menu($footer_name);

    if ($primary_id <= 0 || $footer_id <= 0) {
        $log[] = 'Could not create navigation menus.';
        return;
    }

    // Clear existing items so re-import is idempotent.
    foreach ([$primary_id, $footer_id] as $menu_id) {
        $items = wp_get_nav_menu_items($menu_id);
        if (is_array($items)) {
            foreach ($items as $item) {
                wp_delete_post((int) $item->ID, true);
            }
        }
    }

    $nav = nw_fuel_seed_json('nav.json');

    // --- Primary ---
    nw_fuel_menu_add_link($primary_id, 'Home', home_url('/'));

    $products_parent = nw_fuel_menu_add_link($primary_id, 'Products', nw_fuel_products_url());
    foreach (($nav['productsMenu'] ?? []) as $item) {
        if (! is_array($item)) {
            continue;
        }
        $href = (string) ($item['href'] ?? '');
        if (str_starts_with($href, '/')) {
            $href = home_url($href);
        }
        nw_fuel_menu_add_link($primary_id, (string) ($item['label'] ?? ''), $href, $products_parent);
    }

    $services_parent = nw_fuel_menu_add_link(
        $primary_id,
        'Services',
        get_post_type_archive_link('nw_service') ?: home_url('/services/'),
        0,
        ['dropdown-wide']
    );
    foreach (($nav['servicesMenu'] ?? []) as $item) {
        if (! is_array($item)) {
            continue;
        }
        $href = (string) ($item['href'] ?? '');
        if (str_starts_with($href, '/')) {
            $href = home_url($href);
        }
        $classes = ! empty($item['all']) ? ['nav-all'] : [];
        nw_fuel_menu_add_link($primary_id, (string) ($item['label'] ?? ''), $href, $services_parent, $classes);
    }

    $tech_parent = nw_fuel_menu_add_link(
        $primary_id,
        'Technical Resources',
        get_post_type_archive_link('nw_tech_resource') ?: home_url('/technical-resources/'),
        0,
        ['dropdown-wide']
    );
    foreach (nw_fuel_technical_resources() as $resource) {
        nw_fuel_menu_add_link($primary_id, get_the_title($resource), get_permalink($resource) ?: '', $tech_parent);
    }

    $about = get_page_by_path('about');
    if ($about instanceof WP_Post) {
        nw_fuel_menu_add_object($primary_id, $about->ID, 'page', 'About');
    } else {
        nw_fuel_menu_add_link($primary_id, 'About', nw_fuel_page_url('about'));
    }

    nw_fuel_menu_add_link(
        $primary_id,
        'Gallery',
        get_post_type_archive_link('nw_gallery_item') ?: home_url('/gallery/')
    );

    nw_fuel_menu_add_link(
        $primary_id,
        'Catalog',
        get_post_type_archive_link('nw_catalog_item') ?: home_url('/catalog/')
    );

    $contact = get_page_by_path('contact');
    if ($contact instanceof WP_Post) {
        nw_fuel_menu_add_object($primary_id, $contact->ID, 'page', 'Contact');
    } else {
        nw_fuel_menu_add_link($primary_id, 'Contact', nw_fuel_page_url('contact'));
    }

    // --- Footer (services list) ---
    foreach (array_slice(nw_fuel_services(), 0, 6) as $service) {
        nw_fuel_menu_add_object($footer_id, $service->ID, 'nw_service');
    }
    nw_fuel_menu_add_link(
        $footer_id,
        'All Services',
        get_post_type_archive_link('nw_service') ?: home_url('/services/'),
        0,
        ['nav-all']
    );

    $locations = get_theme_mod('nav_menu_locations', []);
    if (! is_array($locations)) {
        $locations = [];
    }
    $locations['primary'] = $primary_id;
    $locations['footer']  = $footer_id;
    set_theme_mod('nav_menu_locations', $locations);

    $log[] = 'Imported Primary and Footer navigation menus.';
}

/**
 * Add a custom-link menu item.
 *
 * @param string[] $classes
 */
function nw_fuel_menu_add_link(int $menu_id, string $title, string $url, int $parent = 0, array $classes = []): int
{
    $item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title'     => $title,
        'menu-item-url'       => $url,
        'menu-item-status'    => 'publish',
        'menu-item-type'      => 'custom',
        'menu-item-parent-id' => $parent,
        'menu-item-classes'   => implode(' ', $classes),
    ]);

    return is_wp_error($item_id) ? 0 : (int) $item_id;
}

/**
 * Add an object-based menu item (page / CPT).
 *
 * @param string[] $classes
 */
function nw_fuel_menu_add_object(int $menu_id, int $object_id, string $object_type, string $title = '', array $classes = []): int
{
    $item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title'     => $title !== '' ? $title : get_the_title($object_id),
        'menu-item-object'    => $object_type,
        'menu-item-object-id' => $object_id,
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
        'menu-item-classes'   => implode(' ', $classes),
    ]);

    return is_wp_error($item_id) ? 0 : (int) $item_id;
}
