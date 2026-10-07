<?php
/**
 * Blog card partial.
 *
 * @package NW_Fuel
 */

$post = $args['post'] ?? ($post ?? null);
if (! $post instanceof WP_Post) {
    return;
}

$images = nw_fuel_images();
$image  = get_the_post_thumbnail_url($post, 'large');
if (! $image) {
    $image = (string) get_post_meta($post->ID, '_nw_remote_image', true);
}
if (! $image) {
    $image = $images['hero'] ?? '';
}
$categories = get_the_category($post->ID);
$category = $categories[0]->name ?? '';
?>
<article class="blog-card">
  <a href="<?php echo esc_url(get_permalink($post)); ?>" class="blog-card__media">
    <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title($post)); ?>" loading="lazy"><?php endif; ?>
  </a>
  <div class="blog-card__body">
    <?php if ($category) : ?><span class="blog-card__tag"><?php echo esc_html($category); ?></span><?php endif; ?>
    <h3 class="blog-card__title"><a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></h3>
    <p class="blog-card__excerpt"><?php echo esc_html(get_the_excerpt($post)); ?></p>
    <p class="blog-card__meta"><time datetime="<?php echo esc_attr(get_the_date('c', $post)); ?>"><?php echo esc_html(get_the_date('', $post)); ?></time></p>
  </div>
</article>
