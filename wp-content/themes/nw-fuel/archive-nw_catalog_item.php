<?php
/**
 * Catalog PDF list.
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$items  = nw_fuel_catalog_items();
$copy   = nw_fuel_archive_content('catalog');
$count  = count($items);

$hero_image = $images['industrialWorkshop']['lg'] ?? $images['warehouseParts']['lg'] ?? $images['hero'] ?? '';
$band       = [
    [
        'title' => __('Parts on the shelf', 'nw-fuel'),
        'text'  => __('Injectors, pumps, filters, and hard parts we keep for Western Canada shops.', 'nw-fuel'),
        'image' => $images['warehouseParts']['lg'] ?? $hero_image,
    ],
    [
        'title' => __('Tested in our shop', 'nw-fuel'),
        'text'  => __('Catalog listings match the lines we diagnose, rebuild, and stock in Surrey.', 'nw-fuel'),
        'image' => $images['labEquipment']['lg'] ?? $hero_image,
    ],
    [
        'title' => __('Application ready', 'nw-fuel'),
        'text'  => __('Use the PDFs for part numbers, engine notes, and current product photos.', 'nw-fuel'),
        'image' => $images['engineParts']['lg'] ?? $images['turboEngine']['lg'] ?? $hero_image,
    ],
];
?>
<section class="hero hero--catalog">
  <img class="hero__bg" src="<?php echo esc_url($hero_image); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
    <div class="hero__actions hero__fade">
      <a href="#catalog-library" class="btn btn--primary btn--lg"><?php esc_html_e('Browse PDFs', 'nw-fuel'); ?></a>
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--outline-white btn--lg"><?php echo esc_html($copy['cta'] ?? __('Contact', 'nw-fuel')); ?></a>
    </div>
  </div>
</section>

<section class="catalog-stats" aria-label="<?php esc_attr_e('Catalog overview', 'nw-fuel'); ?>">
  <div class="container catalog-stats__inner">
    <div class="catalog-stats__item"><span class="catalog-stats__value"><?php echo esc_html((string) $count); ?></span><span class="catalog-stats__label"><?php esc_html_e('PDFs', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value"><?php esc_html_e('Email', 'nw-fuel'); ?></span><span class="catalog-stats__label"><?php esc_html_e('Unlock download', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value">OEM</span><span class="catalog-stats__label"><?php esc_html_e('Bosch references', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value">BC</span><span class="catalog-stats__label"><?php esc_html_e('Surrey shop', 'nw-fuel'); ?></span></div>
  </div>
</section>

<section class="catalog-steps">
  <div class="container">
    <div class="catalog-steps__grid">
      <article class="catalog-step">
        <span class="catalog-step__num">01</span>
        <h2><?php esc_html_e('Choose a catalog', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('Open the PDF that matches the parts, brand, or application you need.', 'nw-fuel'); ?></p>
      </article>
      <article class="catalog-step">
        <span class="catalog-step__num">02</span>
        <h2><?php esc_html_e('Enter your work email', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('We send no junk. The address unlocks the file for this browser.', 'nw-fuel'); ?></p>
      </article>
      <article class="catalog-step">
        <span class="catalog-step__num">03</span>
        <h2><?php esc_html_e('Download the PDF', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('Keep it for part numbers, photos, and shop pricing reference.', 'nw-fuel'); ?></p>
      </article>
    </div>
  </div>
</section>

<section class="catalog-band">
  <div class="container catalog-band__grid">
    <?php foreach ($band as $panel) : ?>
    <figure class="catalog-band__item">
      <?php if (! empty($panel['image'])) : ?>
      <img src="<?php echo esc_url((string) $panel['image']); ?>" alt="" loading="lazy">
      <?php endif; ?>
      <figcaption>
        <strong><?php echo esc_html((string) $panel['title']); ?></strong>
        <span><?php echo esc_html((string) $panel['text']); ?></span>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
</section>

<section class="catalog-list" id="catalog-library">
  <div class="container">
    <div class="catalog-list__head">
      <h2><?php esc_html_e('PDF library', 'nw-fuel'); ?></h2>
      <p><?php esc_html_e('Each file is a current shop reference. Open a catalog for the cover, file details, and download.', 'nw-fuel'); ?></p>
    </div>
    <?php if ($items === []) : ?>
    <p class="catalog-empty"><?php esc_html_e('No catalogs yet.', 'nw-fuel'); ?></p>
    <?php else : ?>
    <div class="catalog-grid">
      <?php foreach ($items as $item) {
          get_template_part('template-parts/catalog-pdf-card', null, ['item' => $item]);
      } ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
$title       = __('Need a printed catalog or a quote?', 'nw-fuel');
$description = __('Call the Surrey shop for the latest PDF, fitment, or trade pricing.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
