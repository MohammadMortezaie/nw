<?php
/**
 * Service card (matches static includes/service-catalog-card.php → .service-card).
 *
 * @package NW_Fuel
 */

$service = $args['service'] ?? ($service ?? null);
if (! $service instanceof WP_Post) {
    return;
}

$image = (string) get_post_meta($service->ID, '_nw_hero_image', true);
if ($image === '') {
    $image = (string) (get_the_post_thumbnail_url($service, 'large') ?: '');
}
if ($image === '') {
    $image = (string) get_post_meta($service->ID, '_nw_remote_image', true);
}
$image = nw_fuel_resolve_image_url($image);
$permalink = get_permalink($service) ?: '';
$title = get_the_title($service);
$excerpt = get_the_excerpt($service);
?>
<article class="service-card">
  <a href="<?php echo esc_url($permalink); ?>" class="service-card__media">
    <?php if ($image !== '') : ?>
    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
    <?php endif; ?>
  </a>
  <div class="service-card__body">
    <h3 class="service-card__title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
    <p class="service-card__desc"><?php echo esc_html($excerpt); ?></p>
    <span class="service-card__cta"><?php esc_html_e('Learn More', 'nw-fuel'); ?></span>
  </div>
</article>
