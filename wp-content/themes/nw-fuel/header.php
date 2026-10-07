<?php
/**
 * Site header.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

$business            = nw_fuel_business();
$search_json         = '[]';
$header_search_value = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" id="site-header">
  <nav class="container site-nav" aria-label="<?php esc_attr_e('Main navigation', 'nw-fuel'); ?>">
    <div class="site-nav__row">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="logo" aria-label="<?php esc_attr_e('NW Fuel home', 'nw-fuel'); ?>">
        <?php
        $logo_id  = (int) get_theme_mod('custom_logo');
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
        if (! $logo_url) {
            $logo_url = nw_fuel_asset_url('assets/img/logo1.png');
        }
        ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('NW Fuel Injection Service, Diesel Done Right', 'nw-fuel'); ?>" class="logo__img" width="320" height="105" fetchpriority="high">
      </a>

      <div class="site-nav__desktop">
        <?php nw_fuel_render_primary_nav_desktop(); ?>
      </div>

      <form class="site-search site-search--desktop" action="<?php echo esc_url(nw_fuel_products_url()); ?>" method="get" role="search" data-site-search data-products="<?php echo esc_attr((string) $search_json); ?>">
        <label class="sr-only" for="header-search"><?php esc_html_e('Search products', 'nw-fuel'); ?></label>
        <button type="button" class="site-search__open" aria-label="<?php esc_attr_e('Open product search', 'nw-fuel'); ?>">
          <span class="site-search__icon" aria-hidden="true"></span>
        </button>
        <input type="search" id="header-search" name="q" value="<?php echo esc_attr($header_search_value); ?>" placeholder="<?php esc_attr_e('Search part number, style, brand', 'nw-fuel'); ?>" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="header-search-suggestions">
        <button type="button" class="site-search__close" aria-label="<?php esc_attr_e('Close search', 'nw-fuel'); ?>" hidden></button>
        <div class="site-search__suggestions" id="header-search-suggestions" hidden role="listbox" aria-label="<?php esc_attr_e('Product suggestions', 'nw-fuel'); ?>"></div>
      </form>
      <?php nw_fuel_render_partner_nav(); ?>
      <button type="button" class="menu-toggle" id="menu-toggle" aria-label="<?php esc_attr_e('Open menu', 'nw-fuel'); ?>" aria-expanded="false" aria-controls="mobile-menu">
        <span></span><span></span><span></span>
      </button>
    </div>

    <div class="mobile-menu" id="mobile-menu" hidden>
      <form class="site-search site-search--mobile" action="<?php echo esc_url(nw_fuel_products_url()); ?>" method="get" role="search" data-site-search data-products="<?php echo esc_attr((string) $search_json); ?>">
        <label class="sr-only" for="mobile-site-search"><?php esc_html_e('Search products', 'nw-fuel'); ?></label>
        <span class="site-search__icon" aria-hidden="true"></span>
        <input type="search" id="mobile-site-search" name="q" value="<?php echo esc_attr($header_search_value); ?>" placeholder="<?php esc_attr_e('Search part number, style, brand', 'nw-fuel'); ?>" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="mobile-search-suggestions">
        <div class="site-search__suggestions" id="mobile-search-suggestions" hidden role="listbox" aria-label="<?php esc_attr_e('Product suggestions', 'nw-fuel'); ?>"></div>
      </form>

      <?php nw_fuel_render_primary_nav_mobile(); ?>
      <?php if (nw_fuel_has_primary_menu()) : ?>
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="mobile-menu__phone"><?php echo esc_html($business['phone'] ?? ''); ?></a>
      <?php endif; ?>
    </div>
  </nav>
</header>
<?php nw_fuel_render_partner_login_modal(); ?>
<main class="site-main">
