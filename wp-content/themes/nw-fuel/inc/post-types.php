<?php
/**
 * Custom post types.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'nw_fuel_register_post_types');
add_filter('use_block_editor_for_post_type', 'nw_fuel_disable_block_editor_for_services', 10, 2);
add_filter('enter_title_here', 'nw_fuel_service_title_placeholder', 10, 2);

/**
 * Force classic edit screen for services and technical pages.
 */
function nw_fuel_disable_block_editor_for_services(bool $use, string $post_type): bool
{
    if (in_array($post_type, ['nw_service', 'nw_tech_resource', 'nw_contact_request', 'nw_gallery_item', 'nw_catalog_item', 'nw_catalog_lead', 'nw_newsletter_signup'], true)) {
        return false;
    }

    return $use;
}

/**
 * Title field placeholder for services.
 */
function nw_fuel_service_title_placeholder(string $title, WP_Post $post): string
{
    if ($post->post_type === 'nw_service') {
        return __('Service title (e.g. Injector Testing and Rebuilding)', 'nw-fuel');
    }

    return $title;
}

/**
 * Register Services and Technical Resources post types.
 */
function nw_fuel_register_post_types(): void
{
    register_post_type('nw_service', [
        'labels' => [
            'name'               => __('Services', 'nw-fuel'),
            'singular_name'      => __('Service', 'nw-fuel'),
            'add_new'            => __('Add New', 'nw-fuel'),
            'add_new_item'       => __('Add New Service', 'nw-fuel'),
            'edit_item'          => __('Edit Service', 'nw-fuel'),
            'new_item'           => __('New Service', 'nw-fuel'),
            'view_item'          => __('View Service', 'nw-fuel'),
            'search_items'       => __('Search Services', 'nw-fuel'),
            'not_found'          => __('No services found', 'nw-fuel'),
            'not_found_in_trash' => __('No services found in Trash', 'nw-fuel'),
            'all_items'          => __('All Services', 'nw-fuel'),
            'menu_name'          => __('Services', 'nw-fuel'),
        ],
        'public'               => true,
        'has_archive'          => true,
        'rewrite'              => ['slug' => 'services', 'with_front' => false],
        'menu_icon'            => 'dashicons-hammer',
        // Title + featured image + order. All page body fields are custom form inputs (not the block editor).
        'supports'             => ['title', 'thumbnail', 'page-attributes'],
        'show_in_rest'         => false,
        'capability_type'      => 'post',
    ]);

    register_post_type('nw_tech_resource', [
        'labels' => [
            'name'          => __('Technical Pages', 'nw-fuel'),
            'singular_name' => __('Technical Page', 'nw-fuel'),
            'add_new_item'  => __('Add New Technical Page', 'nw-fuel'),
            'edit_item'     => __('Edit Technical Page', 'nw-fuel'),
            'menu_name'     => __('Technical Pages', 'nw-fuel'),
            'all_items'     => __('All Technical Pages', 'nw-fuel'),
        ],
        'public'               => true,
        'has_archive'          => true,
        'rewrite'              => ['slug' => 'technical-resources', 'with_front' => false],
        'menu_icon'            => 'dashicons-media-document',
        'supports'             => ['title', 'thumbnail', 'page-attributes'],
        'show_in_rest'         => false,
        'capability_type'      => 'post',
    ]);

    register_post_type('nw_contact_request', [
        'labels' => [
            'name'               => __('Contact Requests', 'nw-fuel'),
            'singular_name'      => __('Contact Request', 'nw-fuel'),
            'edit_item'          => __('View Contact Request', 'nw-fuel'),
            'search_items'       => __('Search Requests', 'nw-fuel'),
            'not_found'          => __('No contact requests yet.', 'nw-fuel'),
            'not_found_in_trash' => __('No contact requests in Trash.', 'nw-fuel'),
            'all_items'          => __('All Requests', 'nw-fuel'),
            'menu_name'          => __('Contact Requests', 'nw-fuel'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'menu_icon'           => 'dashicons-email-alt',
        'menu_position'       => 26,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'capabilities'        => [
            'create_posts' => 'do_not_allow',
        ],
    ]);

    register_post_type('nw_newsletter_signup', [
        'labels' => [
            'name'               => __('Newsletter', 'nw-fuel'),
            'singular_name'      => __('Newsletter Email', 'nw-fuel'),
            'search_items'       => __('Search Emails', 'nw-fuel'),
            'not_found'          => __('No newsletter emails yet.', 'nw-fuel'),
            'not_found_in_trash' => __('No newsletter emails in Trash.', 'nw-fuel'),
            'all_items'          => __('All Emails', 'nw-fuel'),
            'menu_name'          => __('Newsletter', 'nw-fuel'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'menu_icon'           => 'dashicons-email',
        'menu_position'       => 27,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'capabilities'        => [
            'create_posts' => 'do_not_allow',
        ],
    ]);

    register_post_type('nw_gallery_item', [
        'labels' => [
            'name'               => __('Gallery', 'nw-fuel'),
            'singular_name'      => __('Gallery Item', 'nw-fuel'),
            'add_new'            => __('Add New', 'nw-fuel'),
            'add_new_item'       => __('Add Gallery Item', 'nw-fuel'),
            'edit_item'          => __('Edit Gallery Item', 'nw-fuel'),
            'new_item'           => __('New Gallery Item', 'nw-fuel'),
            'view_item'          => __('View Gallery', 'nw-fuel'),
            'search_items'       => __('Search Gallery', 'nw-fuel'),
            'not_found'          => __('No gallery items yet.', 'nw-fuel'),
            'not_found_in_trash' => __('No gallery items in Trash.', 'nw-fuel'),
            'all_items'          => __('All Items', 'nw-fuel'),
            'menu_name'          => __('Gallery', 'nw-fuel'),
        ],
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'gallery', 'with_front' => false],
        'show_in_nav_menus'   => true,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'menu_icon'           => 'dashicons-format-gallery',
        'menu_position'       => 25,
        'supports'            => ['title', 'page-attributes'],
        'capability_type'     => 'post',
    ]);

    register_post_type('nw_catalog_item', [
        'labels' => [
            'name'               => __('Catalog', 'nw-fuel'),
            'singular_name'      => __('Catalog PDF', 'nw-fuel'),
            'add_new'            => __('Add New', 'nw-fuel'),
            'add_new_item'       => __('Add Catalog PDF', 'nw-fuel'),
            'edit_item'          => __('Edit Catalog PDF', 'nw-fuel'),
            'new_item'           => __('New Catalog PDF', 'nw-fuel'),
            'view_item'          => __('View Catalog PDF', 'nw-fuel'),
            'search_items'       => __('Search Catalog', 'nw-fuel'),
            'not_found'          => __('No catalog PDFs yet.', 'nw-fuel'),
            'not_found_in_trash' => __('No catalog PDFs in Trash.', 'nw-fuel'),
            'all_items'          => __('All PDFs', 'nw-fuel'),
            'menu_name'          => __('Catalog', 'nw-fuel'),
        ],
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'catalog', 'with_front' => false],
        'show_in_nav_menus'   => true,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'menu_icon'           => 'dashicons-media-document',
        'menu_position'       => 24,
        'supports'            => ['title', 'page-attributes'],
        'capability_type'     => 'post',
    ]);

    register_post_type('nw_catalog_lead', [
        'labels' => [
            'name'               => __('Download Emails', 'nw-fuel'),
            'singular_name'      => __('Download Email', 'nw-fuel'),
            'search_items'       => __('Search Emails', 'nw-fuel'),
            'not_found'          => __('No download emails yet.', 'nw-fuel'),
            'not_found_in_trash' => __('No download emails in Trash.', 'nw-fuel'),
            'all_items'          => __('Download Emails', 'nw-fuel'),
            'menu_name'          => __('Download Emails', 'nw-fuel'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=nw_catalog_item',
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'capabilities'        => [
            'create_posts' => 'do_not_allow',
        ],
    ]);
}
