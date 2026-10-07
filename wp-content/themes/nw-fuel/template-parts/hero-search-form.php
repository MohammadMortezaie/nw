<?php
/**
 * Hero search form partial.
 *
 * @package NW_Fuel
 * @var string $hero_search_value
 */

$hero_search_value = $args['hero_search_value'] ?? ($hero_search_value ?? '');
$hero_search_products = [];
$product_categories   = nw_fuel_product_categories();
$json_flags           = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<form class="hero-search hero__fade" action="<?php echo esc_url(nw_fuel_products_url()); ?>" method="get" role="search"
  data-categories="<?php echo esc_attr((string) wp_json_encode($product_categories, $json_flags)); ?>"
  data-products="<?php echo esc_attr((string) wp_json_encode($hero_search_products, $json_flags)); ?>">
  <label class="sr-only" for="hero-search"><?php esc_html_e('Search products', 'nw-fuel'); ?></label>
  <div class="hero-search__bar">
    <div class="hero-search__field">
      <div class="hero-search__ghost" id="hero-search-ghost" aria-hidden="true">
        <span class="hero-search__static"><?php esc_html_e('Search for', 'nw-fuel'); ?></span><span class="hero-search__space" id="hero-space" hidden aria-hidden="true">&nbsp;</span><span class="hero-search__typed" id="hero-typed"></span><span class="hero-search__cursor" aria-hidden="true"></span>
      </div>
      <input type="search" id="hero-search" name="q" value="<?php echo esc_attr($hero_search_value); ?>" placeholder=" " autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="hero-search-suggestions">
    </div>
    <button type="submit" class="hero-search__btn"><?php esc_html_e('Search', 'nw-fuel'); ?></button>
  </div>
  <div class="hero-search__suggestions" id="hero-search-suggestions" hidden role="listbox" aria-label="<?php esc_attr_e('Product suggestions', 'nw-fuel'); ?>"></div>
</form>
