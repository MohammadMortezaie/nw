<?php
/**
 * Technical resources archive.
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$resources = nw_fuel_technical_resources();
$copy = nw_fuel_archive_content('tech');
?>
<section class="hero hero--tech">
  <img class="hero__bg" src="<?php echo esc_url($images['labEquipment']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html($copy['cta'] ?? ''); ?></a>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="btn btn--outline-white btn--lg"><?php esc_html_e('Our Services', 'nw-fuel'); ?></a>
    </div>
  </div>
</section>

<section class="tech-stats" aria-label="<?php esc_attr_e('Technical resources overview', 'nw-fuel'); ?>">
  <div class="container tech-stats__inner">
    <div class="tech-stats__item"><span class="tech-stats__value"><?php echo esc_html((string) count($resources)); ?></span><span class="tech-stats__label"><?php esc_html_e('Guides', 'nw-fuel'); ?></span></div>
    <div class="tech-stats__item"><span class="tech-stats__value"><?php esc_html_e('Install', 'nw-fuel'); ?></span><span class="tech-stats__label"><?php esc_html_e('Application notes', 'nw-fuel'); ?></span></div>
    <div class="tech-stats__item"><span class="tech-stats__value">OEM</span><span class="tech-stats__label"><?php esc_html_e('Bosch standards', 'nw-fuel'); ?></span></div>
    <div class="tech-stats__item"><span class="tech-stats__value">BC</span><span class="tech-stats__label"><?php esc_html_e('Shop written', 'nw-fuel'); ?></span></div>
  </div>
</section>

<section class="tech-catalog">
  <div class="container">
    <div class="tech-catalog__head">
      <h2 class="tech-catalog__title"><?php esc_html_e('Installation and Reference Guides', 'nw-fuel'); ?></h2>
      <p class="tech-catalog__desc"><?php esc_html_e('Powerstroke, Cummins, and Duramax injector guides plus Bosch filter references.', 'nw-fuel'); ?></p>
    </div>
    <div class="tech-grid">
      <?php foreach ($resources as $resource) {
          get_template_part('template-parts/technical-resource-card', null, ['resource' => $resource]);
      } ?>
    </div>
  </div>
</section>

<?php
$title = __('Need Help With an Application?', 'nw-fuel');
$description = __('Call our Surrey shop for fitment, testing, and parts.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
