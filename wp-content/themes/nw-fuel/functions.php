<?php
/**
 * NW Fuel theme bootstrap.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('NW_FUEL_VERSION', '1.9.7');
define('NW_FUEL_DIR', get_template_directory());
define('NW_FUEL_URI', get_template_directory_uri());

require_once NW_FUEL_DIR . '/inc/helpers.php';
require_once NW_FUEL_DIR . '/inc/setup.php';
require_once NW_FUEL_DIR . '/inc/assets.php';
require_once NW_FUEL_DIR . '/inc/post-types.php';
require_once NW_FUEL_DIR . '/inc/product-attributes.php';
require_once NW_FUEL_DIR . '/inc/page-content.php';
require_once NW_FUEL_DIR . '/inc/history.php';
require_once NW_FUEL_DIR . '/inc/admin-ui.php';
require_once NW_FUEL_DIR . '/inc/admin-ui-tech.php';
require_once NW_FUEL_DIR . '/inc/admin-ui-product.php';
require_once NW_FUEL_DIR . '/inc/admin-ui-pages.php';
require_once NW_FUEL_DIR . '/inc/meta-boxes.php';
require_once NW_FUEL_DIR . '/inc/seo.php';
require_once NW_FUEL_DIR . '/inc/rank-math.php';
require_once NW_FUEL_DIR . '/inc/nav.php';
require_once NW_FUEL_DIR . '/inc/theme-settings.php';
require_once NW_FUEL_DIR . '/inc/content-guide.php';
require_once NW_FUEL_DIR . '/inc/customizer.php';
require_once NW_FUEL_DIR . '/inc/forms.php';
require_once NW_FUEL_DIR . '/inc/newsletter.php';
require_once NW_FUEL_DIR . '/inc/contact-requests.php';
require_once NW_FUEL_DIR . '/inc/gallery.php';
require_once NW_FUEL_DIR . '/inc/catalog.php';
require_once NW_FUEL_DIR . '/inc/importer.php';
require_once NW_FUEL_DIR . '/inc/inventory-xlsx.php';
require_once NW_FUEL_DIR . '/inc/inventory-api.php';
require_once NW_FUEL_DIR . '/inc/inventory-sync.php';
require_once NW_FUEL_DIR . '/inc/partners.php';
require_once NW_FUEL_DIR . '/inc/rewrites.php';

if (nw_fuel_is_woocommerce_active()) {
    require_once NW_FUEL_DIR . '/inc/woocommerce.php';
    require_once NW_FUEL_DIR . '/inc/product-report.php';
}
