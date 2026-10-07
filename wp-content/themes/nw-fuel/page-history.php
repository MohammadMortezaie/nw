<?php
/**
 * History of NW Fuel — full company timeline.
 *
 * Template Name: History
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

$images  = nw_fuel_images();
$c       = nw_fuel_history_content();
$stories = nw_fuel_history_stories();
$years   = nw_fuel_history_years($stories);
$hero_img = nw_fuel_asset_url('assets/img/history/2018-celebrating-50-years-in-the-diesel-industry.jpg');
if ($hero_img === '') {
    $hero_img = (string) ($images['hero'] ?? '');
}

$current_year = '';
?>
<section class="hero hero--about hero--history">
  <img class="hero__bg" src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr((string) ($c['hero_title'] ?? '')); ?>">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html((string) ($c['hero_eyebrow'] ?? '')); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html((string) ($c['hero_title'] ?? '')); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html((string) ($c['hero_desc'] ?? '')); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('about')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html((string) ($c['hero_cta_primary'] ?? '')); ?></a>
      <a href="#history-timeline" class="btn btn--outline-white btn--lg"><?php esc_html_e('Jump to Timeline', 'nw-fuel'); ?></a>
    </div>
  </div>
</section>

<?php if ($years) : ?>
<nav class="history-years" aria-label="<?php esc_attr_e('Jump to year', 'nw-fuel'); ?>">
  <div class="container history-years__inner">
    <?php foreach ($years as $year) : ?>
    <a href="#year-<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>

<section class="history-timeline" id="history-timeline">
  <div class="container">
    <?php if ($stories === []) : ?>
    <p class="about-panel__text"><?php esc_html_e('History stories are not available yet.', 'nw-fuel'); ?></p>
    <?php endif; ?>

    <?php foreach ($stories as $index => $story) :
        if (! is_array($story)) {
            continue;
        }
        $year = (string) ($story['year'] ?? '');
        $show_year = $year !== '' && $year !== $current_year;
        if ($show_year) {
            $current_year = $year;
        }
        $side = $index % 2 === 0 ? 'history-story--odd' : 'history-story--even';
        $paras = nw_fuel_history_paragraphs((string) ($story['body'] ?? ''));
        ?>
    <?php if ($show_year) : ?>
    <div class="history-year" id="year-<?php echo esc_attr($year); ?>">
      <span><?php echo esc_html($year); ?></span>
    </div>
    <?php endif; ?>

    <article class="history-story <?php echo esc_attr($side); ?>">
      <div class="history-story__media">
        <?php if ((string) ($story['image'] ?? '') !== '') : ?>
        <img src="<?php echo esc_url((string) $story['image']); ?>" alt="<?php echo esc_attr((string) ($story['title'] ?? '')); ?>" loading="lazy">
        <?php endif; ?>
      </div>
      <div class="history-story__copy">
        <?php if ((string) ($story['date'] ?? '') !== '') : ?>
        <p class="history-story__date"><?php echo esc_html((string) $story['date']); ?></p>
        <?php endif; ?>
        <h2 class="history-story__title"><?php echo esc_html((string) ($story['title'] ?? '')); ?></h2>
        <?php foreach ($paras as $para) : ?>
        <p class="history-story__text"><?php echo esc_html($para); ?></p>
        <?php endforeach; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<?php
get_template_part('template-parts/cta', null, [
    'title'       => (string) ($c['cta_title'] ?? ''),
    'description' => (string) ($c['cta_description'] ?? ''),
]);
get_footer();
