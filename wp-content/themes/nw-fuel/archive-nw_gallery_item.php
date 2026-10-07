<?php
/**
 * Gallery archive — images and videos with titles.
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$items  = nw_fuel_gallery_items();
$copy   = nw_fuel_archive_content('gallery');
?>
<section class="hero hero--gallery">
  <img class="hero__bg" src="<?php echo esc_url($images['industrialWorkshop']['lg'] ?? $images['warehouseParts']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
  </div>
</section>

<section class="gallery-catalog">
  <div class="container">
    <?php if ($items === []) : ?>
    <p class="gallery-empty"><?php esc_html_e('No photos or videos yet.', 'nw-fuel'); ?></p>
    <?php else : ?>
    <div class="gallery-grid">
      <?php foreach ($items as $item) {
          get_template_part('template-parts/gallery-card', null, ['item' => $item]);
      } ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
