<?php
/**
 * Technical resource card — listing and More Guides.
 *
 * @package NW_Fuel
 */

$resource = $args['resource'] ?? ($resource ?? null);
if (! $resource instanceof WP_Post) {
    return;
}

$images = nw_fuel_images();
$image  = get_the_post_thumbnail_url($resource, 'large');
if (! $image) {
    $image = (string) get_post_meta($resource->ID, '_nw_remote_image', true);
}
if (! $image) {
    $image = $images['hero'] ?? '';
}
$permalink = get_permalink($resource);
$title     = get_the_title($resource);
?>
<article class="tech-card">
  <a href="<?php echo esc_url($permalink); ?>" class="tech-card__media">
    <?php if ($image) : ?>
    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
    <?php endif; ?>
    <span class="tech-card__tag"><?php esc_html_e('Technical Guide', 'nw-fuel'); ?></span>
  </a>
  <div class="tech-card__body">
    <h2 class="tech-card__title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h2>
    <p class="tech-card__excerpt"><?php echo esc_html(get_the_excerpt($resource)); ?></p>
    <span class="tech-card__cta"><?php esc_html_e('Read Guide', 'nw-fuel'); ?></span>
  </div>
</article>
