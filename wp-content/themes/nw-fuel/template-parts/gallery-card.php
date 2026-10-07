<?php
/**
 * Gallery grid card — media + title only.
 *
 * @package NW_Fuel
 */

$item = $args['item'] ?? null;
if (! $item instanceof WP_Post) {
    return;
}

$id    = (int) $item->ID;
$type  = nw_fuel_gallery_item_type($id);
$title = get_the_title($item);
?>
<figure class="gallery-card">
  <div class="gallery-card__media">
    <?php if ($type === 'video') :
        $video = nw_fuel_gallery_video_url($id);
        $embed = nw_fuel_gallery_embed_url($video);
        if ($embed !== '') : ?>
    <iframe src="<?php echo esc_url($embed); ?>" title="<?php echo esc_attr($title); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
        <?php elseif (nw_fuel_gallery_is_file_video($video)) : ?>
    <video src="<?php echo esc_url($video); ?>" controls preload="metadata" playsinline></video>
        <?php else : ?>
    <span class="gallery-card__missing"><?php esc_html_e('Video unavailable', 'nw-fuel'); ?></span>
        <?php endif; ?>
    <?php else :
        $large = nw_fuel_gallery_image_url($id, 'large');
        $full  = nw_fuel_gallery_image_url($id, 'full');
        if ($large !== '') : ?>
    <a class="gallery-card__image" href="<?php echo esc_url($full !== '' ? $full : $large); ?>" data-gallery-lightbox>
      <img src="<?php echo esc_url($large); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
    </a>
        <?php else : ?>
    <span class="gallery-card__missing"><?php esc_html_e('Image unavailable', 'nw-fuel'); ?></span>
        <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($title !== '') : ?>
  <figcaption class="gallery-card__title"><?php echo esc_html($title); ?></figcaption>
  <?php endif; ?>
</figure>
