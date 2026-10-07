<?php
/**
 * Hero category marquee links.
 *
 * @package NW_Fuel
 */

$product_categories = nw_fuel_product_categories();
?>
<a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="hero-cats__item">
  <span class="hero-cats__icon"><?php echo nw_fuel_product_category_icon_svg('all'); ?></span>
  <span class="hero-cats__label"><?php esc_html_e('All Parts', 'nw-fuel'); ?></span>
</a>
<?php foreach ($product_categories as $cat) : ?>
<a href="<?php echo esc_url(add_query_arg('category', $cat, nw_fuel_products_url())); ?>" class="hero-cats__item">
  <span class="hero-cats__icon"><?php echo nw_fuel_product_category_icon_svg($cat); ?></span>
  <span class="hero-cats__label"><?php echo esc_html($cat); ?></span>
</a>
<?php endforeach; ?>
<a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="hero-cats__item hero-cats__item--brands">
  <span class="hero-cats__icon"><?php echo nw_fuel_product_category_icon_svg('brands'); ?></span>
  <span class="hero-cats__label"><?php esc_html_e('Brands', 'nw-fuel'); ?></span>
</a>
