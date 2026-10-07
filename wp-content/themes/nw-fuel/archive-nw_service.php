<?php
/**
 * Services archive template.
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$business = nw_fuel_business();
$services = nw_fuel_services();
$copy = nw_fuel_archive_content('services');
?>
<section class="hero hero--services">
  <img class="hero__bg" src="<?php echo esc_url($images['labEquipment']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html($copy['cta'] ?? ''); ?></a>
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline-white btn--lg"><?php echo esc_html($business['phone'] ?? ''); ?></a>
    </div>
  </div>
</section>

<section class="services-stats" aria-label="<?php esc_attr_e('Services overview', 'nw-fuel'); ?>">
  <div class="container services-stats__inner">
    <div class="services-stats__item"><span class="services-stats__value"><?php echo esc_html((string) count($services)); ?></span><span class="services-stats__label"><?php esc_html_e('Shop services', 'nw-fuel'); ?></span></div>
    <div class="services-stats__item"><span class="services-stats__value">1968</span><span class="services-stats__label"><?php esc_html_e('In Surrey since', 'nw-fuel'); ?></span></div>
    <div class="services-stats__item"><span class="services-stats__value">12 mo</span><span class="services-stats__label"><?php esc_html_e('Service warranty', 'nw-fuel'); ?></span></div>
    <div class="services-stats__item"><span class="services-stats__value">EPS205</span><span class="services-stats__label"><?php esc_html_e('Test bench', 'nw-fuel'); ?></span></div>
  </div>
</section>

<section class="services-catalog">
  <div class="container">
    <div class="services-catalog__head">
      <h2 class="services-catalog__title"><?php echo esc_html($copy['catalog_title'] ?? ''); ?></h2>
      <p class="services-catalog__desc"><?php echo esc_html($copy['catalog_desc'] ?? ''); ?></p>
    </div>
    <div class="services-grid">
      <?php foreach ($services as $service) {
          get_template_part('template-parts/service-catalog-card', null, ['service' => $service]);
      } ?>
    </div>
  </div>
</section>

<?php
$title = __('Need Help Diagnosing a Fuel System Issue?', 'nw-fuel');
$description = __('Our technicians provide expert guidance over the phone and comprehensive testing in our Surrey workshop.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
