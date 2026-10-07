<?php
/**
 * Catalog list card.
 *
 * @package NW_Fuel
 */

$item = $args['item'] ?? null;
if (! $item instanceof WP_Post) {
    return;
}

$id      = (int) $item->ID;
$title   = get_the_title($item);
$summary = nw_fuel_catalog_display_summary($id);
$cover   = nw_fuel_catalog_cover_url($id);
$is_doc  = nw_fuel_catalog_cover_is_document($id);
$url     = nw_fuel_catalog_item_url($item);
$size    = nw_fuel_catalog_file_size_label($id);
$updated = nw_fuel_catalog_updated_label($id);
$meta    = array_filter([$size !== '' ? $size : '', $updated !== '' ? sprintf(__('Updated %s', 'nw-fuel'), $updated) : '']);
?>
<article class="catalog-card">
  <a class="catalog-card__media<?php echo $is_doc ? ' catalog-card__media--document' : ''; ?>" href="<?php echo esc_url($url); ?>">
    <?php if ($cover !== '') : ?>
    <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
    <?php endif; ?>
    <span class="catalog-card__tag"><?php esc_html_e('PDF', 'nw-fuel'); ?></span>
  </a>
  <div class="catalog-card__body">
    <h2 class="catalog-card__title"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h2>
    <p class="catalog-card__desc"><?php echo esc_html($summary); ?></p>
    <?php if ($meta !== []) : ?>
    <p class="catalog-card__meta"><?php echo esc_html(implode(' · ', $meta)); ?></p>
    <?php endif; ?>
    <a class="catalog-card__cta" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Open catalog', 'nw-fuel'); ?></a>
  </div>
</article>
