<?php
/**
 * Catalog product card.
 *
 * @package NW_Fuel
 */

$product = $args['product'] ?? ($product ?? null);
if (! $product instanceof WC_Product) {
    return;
}

$product_id   = $product->get_id();
$part_number  = nw_fuel_get_part_number($product_id);
$category     = nw_fuel_product_category_name($product_id);
$brands       = nw_fuel_product_term_names($product_id, 'pa_brand');
$brand        = $brands[0] ?? '';
$vehicles     = nw_fuel_product_term_names($product_id, 'pa_vehicle-type');
$engines      = nw_fuel_product_term_names($product_id, 'pa_engine-type');
$gallery      = nw_fuel_product_gallery_urls($product_id);
$image        = $gallery[0] ?? '';
$inventory    = nw_fuel_product_inventory($product_id);
$alts         = nw_fuel_get_search_alts($product_id);
$permalink    = nw_fuel_product_url($product);
$label        = $product->get_name();
if ($part_number !== '') {
    $label .= ', ' . sprintf(/* translators: %s: part number */ __('part %s', 'nw-fuel'), $part_number);
}
?>
<a href="<?php echo esc_url($permalink); ?>" class="catalog-product"
  data-catalog-product
  data-name="<?php echo esc_attr(strtolower($product->get_name())); ?>"
  data-part="<?php echo esc_attr(strtolower($part_number)); ?>"
  data-alts="<?php echo esc_attr(strtolower(implode(' ', $alts))); ?>"
  data-brand="<?php echo esc_attr($brand); ?>"
  data-category="<?php echo esc_attr($category); ?>"
  data-vehicle="<?php echo esc_attr(implode(',', $vehicles)); ?>"
  data-engine="<?php echo esc_attr(implode(',', $engines)); ?>"
  aria-label="<?php echo esc_attr($label); ?>">
  <span class="catalog-product__media">
    <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="" loading="lazy"><?php endif; ?>
    <span class="catalog-product__tag"><?php echo esc_html($category); ?></span>
  </span>
  <span class="catalog-product__body">
    <p class="catalog-product__brand"><?php echo esc_html($brand); ?></p>
    <h3 class="catalog-product__title"><?php echo esc_html($product->get_name()); ?></h3>
    <?php if ($part_number) : ?><p class="catalog-product__part"><?php esc_html_e('Part #', 'nw-fuel'); ?> <?php echo esc_html($part_number); ?></p><?php endif; ?>
    <?php if ($inventory && ! empty($inventory['show_price']) && (float) ($inventory['display_price'] ?? 0) > 0) : ?>
      <p class="catalog-product__price"><?php echo esc_html(nw_fuel_format_price((float) $inventory['display_price'], $inventory['currency'])); ?></p>
    <?php else : ?>
      <p class="catalog-product__quote"><?php esc_html_e('Call for price', 'nw-fuel'); ?></p>
    <?php endif; ?>
    <?php if ($inventory) : ?>
      <p class="catalog-product__stock"><?php echo esc_html(nw_fuel_quantity_label((int) $inventory['quantity'])); ?></p>
    <?php endif; ?>
  </span>
</a>
