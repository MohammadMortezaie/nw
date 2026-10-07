<?php
/**
 * About page template — all copy from Pages → About Us content editor.
 *
 * Template Name: About
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

$images  = nw_fuel_images();
$c       = nw_fuel_about_content();
$values  = is_array($c['values'] ?? null) ? $c['values'] : [];
$certs   = is_array($c['certifications'] ?? null) ? $c['certifications'] : [];
$timeline = is_array($c['timeline'] ?? null) ? $c['timeline'] : [];
$team    = is_array($c['team'] ?? null) ? $c['team'] : [];
$equipment = is_array($c['equipment'] ?? null) ? $c['equipment'] : [];
$stats   = is_array($c['stats'] ?? null) ? $c['stats'] : [];
$trust   = is_array($c['trust'] ?? null) ? $c['trust'] : [];
?>
<section class="hero hero--about">
  <img class="hero__bg" src="<?php echo esc_url($images['hero'] ?? ''); ?>" alt="<?php echo esc_attr($c['hero_title'] ?? ''); ?>">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html((string) ($c['hero_eyebrow'] ?? '')); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html((string) ($c['hero_title'] ?? '')); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html((string) ($c['hero_desc'] ?? '')); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html((string) ($c['hero_cta_primary'] ?? '')); ?></a>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="btn btn--outline-white btn--lg"><?php echo esc_html((string) ($c['hero_cta_secondary'] ?? '')); ?></a>
    </div>
  </div>
</section>

<?php if ($stats) : ?>
<section class="about-stats" aria-label="<?php echo esc_attr((string) ($c['hero_title'] ?? '')); ?>">
  <div class="container about-stats__inner">
    <?php foreach ($stats as $stat) : if (! is_array($stat)) { continue; } ?>
    <div class="about-stats__item">
      <span class="about-stats__value"><?php echo esc_html((string) ($stat['value'] ?? '')); ?></span>
      <span class="about-stats__label"><?php echo esc_html((string) ($stat['label'] ?? '')); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="about-panel">
  <div class="container about-story">
    <div class="about-story__copy">
      <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['story_eyebrow'] ?? '')); ?></p>
      <h2 class="about-panel__title"><?php echo esc_html((string) ($c['story_title'] ?? '')); ?></h2>
      <?php if ((string) ($c['story_p1'] ?? '') !== '') : ?>
      <p class="about-panel__text"><?php echo esc_html((string) $c['story_p1']); ?></p>
      <?php endif; ?>
      <?php if ((string) ($c['story_p2'] ?? '') !== '') : ?>
      <p class="about-panel__text"><?php echo esc_html((string) $c['story_p2']); ?></p>
      <?php endif; ?>
    </div>
    <div class="about-story__media">
      <img src="<?php echo esc_url($images['industrialWorkshop']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
    </div>
  </div>
</section>

<section class="history-cta" aria-labelledby="history-cta-title">
  <div class="container history-cta__inner">
    <div class="history-cta__copy">
      <p class="history-cta__eyebrow"><?php echo esc_html((string) ($c['history_cta_eyebrow'] ?? '')); ?></p>
      <p class="history-cta__year"><?php echo esc_html((string) ($c['history_cta_year'] ?? '1968')); ?></p>
      <h2 class="history-cta__title" id="history-cta-title"><?php echo esc_html((string) ($c['history_cta_title'] ?? '')); ?></h2>
      <?php if ((string) ($c['history_cta_text'] ?? '') !== '') : ?>
      <p class="history-cta__text"><?php echo esc_html((string) $c['history_cta_text']); ?></p>
      <?php endif; ?>
      <a href="<?php echo esc_url(nw_fuel_page_url('history-of-nw-fuel')); ?>" class="btn btn--white btn--lg history-cta__btn"><?php echo esc_html((string) ($c['history_cta_button'] ?? '')); ?></a>
    </div>
    <div class="history-cta__media">
      <img src="<?php echo esc_url(nw_fuel_asset_url('assets/img/history/2018-celebrating-50-years-in-the-diesel-industry.jpg')); ?>" alt="<?php echo esc_attr((string) ($c['history_cta_title'] ?? '')); ?>">
      <img class="history-cta__badge" src="<?php echo esc_url(nw_fuel_asset_url('assets/img/history/50-years-anniversary.png')); ?>" alt="<?php esc_attr_e('NW Fuel 50 years anniversary', 'nw-fuel'); ?>">
    </div>
  </div>
</section>

<?php if ($values) : ?>
<section class="about-panel about-panel--gray">
  <div class="container">
    <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['values_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section"><?php echo esc_html((string) ($c['values_title'] ?? '')); ?></h2>
    <div class="about-grid about-grid--4">
      <?php foreach ($values as $value) : if (! is_array($value)) { continue; } ?>
      <article class="about-card">
        <h3 class="about-card__title"><?php echo esc_html((string) ($value['title'] ?? '')); ?></h3>
        <p class="about-card__text"><?php echo esc_html((string) ($value['description'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($certs) : ?>
<section class="about-panel">
  <div class="container">
    <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['certs_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section"><?php echo esc_html((string) ($c['certs_title'] ?? '')); ?></h2>
    <div class="about-grid about-grid--2">
      <?php foreach ($certs as $cert) : if (! is_array($cert)) { continue; } ?>
      <article class="about-card about-card--bordered">
        <h3 class="about-card__title"><?php echo esc_html((string) ($cert['title'] ?? '')); ?></h3>
        <p class="about-card__text"><?php echo esc_html((string) ($cert['description'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($equipment) : ?>
<section class="about-panel about-panel--gray">
  <div class="container">
    <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['workshop_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section"><?php echo esc_html((string) ($c['workshop_title'] ?? '')); ?></h2>
    <ul class="about-equipment">
      <?php foreach ($equipment as $item) : ?>
      <li><?php echo esc_html((string) $item); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($team) : ?>
<section class="about-panel">
  <div class="container">
    <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['team_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section"><?php echo esc_html((string) ($c['team_title'] ?? '')); ?></h2>
    <div class="about-grid about-grid--3">
      <?php foreach ($team as $member) : if (! is_array($member)) { continue; } ?>
      <article class="about-card about-card--bordered">
        <h3 class="about-card__title"><?php echo esc_html((string) ($member['name'] ?? '')); ?></h3>
        <p class="about-card__role"><?php echo esc_html((string) ($member['role'] ?? '')); ?></p>
        <p class="about-card__text"><?php echo esc_html((string) ($member['description'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($timeline) : ?>
<section class="about-timeline">
  <div class="container">
    <p class="about-panel__eyebrow about-panel__eyebrow--light"><?php echo esc_html((string) ($c['timeline_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section about-panel__title--light"><?php echo esc_html((string) ($c['timeline_title'] ?? '')); ?></h2>
    <div class="about-timeline__list">
      <?php foreach ($timeline as $item) : if (! is_array($item)) { continue; } ?>
      <article class="about-timeline__item">
        <span class="about-timeline__year"><?php echo esc_html((string) ($item['year'] ?? '')); ?></span>
        <p class="about-timeline__text"><?php echo esc_html((string) ($item['event'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="about-timeline__more">
      <a href="<?php echo esc_url(nw_fuel_page_url('history-of-nw-fuel')); ?>" class="btn btn--outline-white"><?php echo esc_html((string) ($c['history_cta_button'] ?? __('Explore Our History', 'nw-fuel'))); ?></a>
    </p>
  </div>
</section>
<?php endif; ?>

<?php if ($trust) : ?>
<section class="about-panel about-panel--gray">
  <div class="container">
    <p class="about-panel__eyebrow"><?php echo esc_html((string) ($c['trust_eyebrow'] ?? '')); ?></p>
    <h2 class="about-panel__title about-panel__title--section"><?php echo esc_html((string) ($c['trust_title'] ?? '')); ?></h2>
    <div class="about-trust">
      <?php foreach ($trust as $item) : if (! is_array($item)) { continue; } ?>
      <div class="about-trust__item">
        <span class="about-trust__value"><?php echo esc_html((string) ($item['value'] ?? '')); ?></span>
        <span class="about-trust__label"><?php echo esc_html((string) ($item['label'] ?? '')); ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
get_template_part('template-parts/cta', null, [
    'title'       => (string) ($c['cta_title'] ?? ''),
    'description' => (string) ($c['cta_description'] ?? ''),
]);
get_footer();
