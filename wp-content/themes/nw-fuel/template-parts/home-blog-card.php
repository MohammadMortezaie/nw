<?php
/**
 * Homepage blog card — matched to static includes/home-blog-card.php
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

$post = $args['post'] ?? null;
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
$category   = $categories[0]->name ?? '';
$read_time  = (string) get_post_meta($post->ID, '_nw_read_time', true);
$tag_parts  = array_filter([$category, $read_time]);
$tag_line   = implode(', ', $tag_parts);
$permalink  = get_permalink($post);
?>
<article class="home-blog">
  <a href="<?php echo esc_url($permalink); ?>" class="home-blog__media">
    <?php if ($image) : ?>
    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title($post)); ?>" loading="lazy">
    <?php endif; ?>
  </a>
  <div class="home-blog__body">
    <?php if ($tag_line !== '') : ?>
    <p class="home-blog__tag"><?php echo esc_html($tag_line); ?></p>
    <?php endif; ?>
    <h3 class="home-blog__title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html(get_the_title($post)); ?></a></h3>
    <p class="home-blog__excerpt"><?php echo esc_html(get_the_excerpt($post)); ?></p>
  </div>
</article>
