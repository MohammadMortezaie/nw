<?php
/**
 * Homepage product card — matched to static includes/home-product-card.php
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

$product = $args['product'] ?? null;
if (! $product instanceof WC_Product) {
    return;
}

$product_id = $product->get_id();
$gallery    = nw_fuel_product_gallery_urls($product_id);
$image      = $gallery[0] ?? '';
if ($image === '' && $product->get_image_id()) {
    $image = (string) wp_get_attachment_image_url($product->get_image_id(), 'large');
}
if ($image === '') {
    $image = (string) get_post_meta($product_id, '_nw_remote_image', true);
}
$brand      = nw_fuel_product_term_names($product_id, 'pa_brand')[0] ?? '';
$part       = nw_fuel_get_part_number($product_id);
$inventory  = nw_fuel_product_inventory($product_id);
$permalink  = nw_fuel_product_url($product);
?>
<a href="<?php echo esc_url($permalink); ?>" class="home-product" aria-label="<?php echo esc_attr($product->get_name()); ?>">
  <span class="home-product__media">
    <?php if ($image) : ?>
    <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy">
    <?php endif; ?>
  </span>
  <span class="home-product__body">
    <p class="home-product__brand"><?php echo esc_html($brand); ?></p>
    <h3 class="home-product__title"><?php echo esc_html($product->get_name()); ?></h3>
    <?php if ($part !== '') : ?>
    <p class="home-product__part"><?php echo esc_html(sprintf(/* translators: %s: part number */ __('Part # %s', 'nw-fuel'), $part)); ?></p>
    <?php endif; ?>
    <?php if ($inventory && ! empty($inventory['show_price']) && (float) ($inventory['display_price'] ?? 0) > 0) : ?>
    <p class="home-product__price"><?php echo esc_html(nw_fuel_format_price((float) $inventory['display_price'], (string) ($inventory['currency'] ?? 'CAD'))); ?></p>
    <?php else : ?>
    <p class="home-product__badge"><?php esc_html_e('Call for price', 'nw-fuel'); ?></p>
    <?php endif; ?>
  </span>
</a>
