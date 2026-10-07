<?php
/**
 * WooCommerce global product attributes.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'nw_fuel_register_product_attributes', 20);

/**
 * Register Brand, Vehicle Type, and Engine Type attributes.
 */
function nw_fuel_register_product_attributes(): void
{
    if (! nw_fuel_is_woocommerce_active() || ! function_exists('wc_create_attribute')) {
        return;
    }

    $attributes = [
        'brand'        => 'Brand',
        'vehicle-type' => 'Vehicle Type',
        'engine-type'  => 'Engine Type',
    ];

    foreach ($attributes as $slug => $label) {
        $taxonomy = wc_attribute_taxonomy_name($slug);
        if (taxonomy_exists($taxonomy)) {
            continue;
        }

        $existing = wc_get_attribute_taxonomies();
        $found    = false;
        if (is_array($existing)) {
            foreach ($existing as $attribute) {
                if (($attribute->attribute_name ?? '') === $slug) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            continue;
        }

        wc_create_attribute([
            'name'         => $label,
            'slug'         => $slug,
            'type'         => 'select',
            'order_by'     => 'name',
            'has_archives' => false,
        ]);
    }
}

/**
 * Attribute taxonomy slugs used by the theme.
 *
 * @return array<string, string>
 */
function nw_fuel_product_attribute_taxonomies(): array
{
    return [
        'brand'        => 'pa_brand',
        'vehicle_type' => 'pa_vehicle-type',
        'engine_type'  => 'pa_engine-type',
    ];
}
