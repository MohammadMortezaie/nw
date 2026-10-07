<?php
/**
 * Front page template — pixel-matched to static index.php.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

$business = nw_fuel_business();
$images   = nw_fuel_images();
$settings = nw_fuel_get_settings();
$home     = nw_fuel_home_content();

$all_products = nw_fuel_is_woocommerce_active()
    ? array_values(array_filter(array_map('wc_get_product', wc_get_products(['status' => 'publish', 'limit' => 12, 'return' => 'ids']))))
    : [];

$parts_in_stock = array_slice($all_products, 0, 6);
$more_parts     = array_slice($all_products, 6, 6);
$brands         = nw_fuel_attribute_terms('pa_brand');
$new_drop       = array_slice($brands, 0, 6);
$featured_slugs = $settings['home']['featured_service_slugs'] ?? [];
if (! is_array($featured_slugs) || $featured_slugs === []) {
    $featured_slugs = [
        'mechanical-injector-rebuild',
        'heui-eui-testing',
        'common-rail-repair',
        'fuel-pump-rebuild',
    ];
}
$featured = nw_fuel_related_services($featured_slugs);
$blog_posts     = get_posts(['numberposts' => 4, 'post_type' => 'post']);
$newsletter_feedback = nw_fuel_form_feedback('newsletter');
?>
<section class="hero">
  <img class="hero__bg" src="<?php echo esc_url($images['hero'] ?? ''); ?>" alt="<?php esc_attr_e('Diesel fuel injection workshop at NW Fuel Injection Services', 'nw-fuel'); ?>">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($home['hero_eyebrow']); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($home['hero_title']); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($home['hero_desc']); ?></p>
    <div class="hero-portals hero__fade">
      <a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="hero-portal">
        <img class="hero-portal__bg" src="<?php echo esc_url($images['warehouseParts']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
        <span class="hero-portal__overlay" aria-hidden="true"></span>
        <span class="hero-portal__content">
          <span class="hero-portal__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
          </span>
          <span class="hero-portal__label"><?php echo esc_html($home['portal_parts_label']); ?></span>
          <span class="hero-portal__title"><?php echo esc_html($home['portal_parts_title']); ?></span>
          <span class="hero-portal__cta"><?php echo esc_html($home['portal_parts_cta']); ?></span>
        </span>
      </a>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="hero-portal">
        <img class="hero-portal__bg" src="<?php echo esc_url($images['labEquipment']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
        <span class="hero-portal__overlay" aria-hidden="true"></span>
        <span class="hero-portal__content">
          <span class="hero-portal__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
          </span>
          <span class="hero-portal__label"><?php echo esc_html($home['portal_services_label']); ?></span>
          <span class="hero-portal__title"><?php echo esc_html($home['portal_services_title']); ?></span>
          <span class="hero-portal__cta"><?php echo esc_html($home['portal_services_cta']); ?></span>
        </span>
      </a>
    </div>
    <?php
    $hero_search_value = '';
    get_template_part('template-parts/hero-search-form');
    ?>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html($home['hero_cta_primary']); ?></a>
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline-white btn--lg" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: phone */ __('Call us at %s', 'nw-fuel'), $business['phone'] ?? '')); ?>"><?php echo esc_html($home['hero_cta_secondary']); ?></a>
    </div>
    <div class="hero__meta hero__fade">
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>"><?php echo esc_html($business['phone'] ?? ''); ?></a>
      <span><?php echo esc_html($business['hours'] ?? ''); ?></span>
    </div>
  </div>
  <nav class="hero-cats hero__fade" aria-label="<?php esc_attr_e('Shop parts by category', 'nw-fuel'); ?>">
    <div class="hero-cats__viewport">
      <div class="hero-cats__track" data-hero-cats-track>
        <div class="hero-cats__set"><?php get_template_part('template-parts/hero-cats-track'); ?></div>
        <div class="hero-cats__set" aria-hidden="true"><?php get_template_part('template-parts/hero-cats-track'); ?></div>
      </div>
    </div>
  </nav>
</section>

<?php if ($parts_in_stock) :
    get_template_part('template-parts/home-parts-showcase', null, [
        'showcase_heading_id' => 'parts-in-stock-heading',
        'showcase_title'      => $home['parts_title'],
        'showcase_text'       => $home['parts_text'],
        'showcase_cta_href'   => nw_fuel_products_url(),
        'showcase_cta_label'  => $home['parts_cta'],
        'showcase_products'   => $parts_in_stock,
    ]);
endif; ?>

<section class="home-section home-section--gray">
  <div class="container">
    <div class="home-section__head">
      <h2 class="home-section__title"><?php echo esc_html($home['services_title']); ?></h2>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="home-section__link"><?php echo esc_html($home['services_link']); ?></a>
    </div>
    <div class="collection-grid">
      <?php foreach ($featured as $service_post) :
          $hero = get_post_meta($service_post->ID, '_nw_hero_image', true) ?: get_the_post_thumbnail_url($service_post, 'large');
          ?>
      <a href="<?php echo esc_url(get_permalink($service_post)); ?>" class="collection-tile">
        <?php if ($hero) : ?><img class="collection-tile__bg" src="<?php echo esc_url($hero); ?>" alt="<?php echo esc_attr(get_the_title($service_post)); ?>"><?php endif; ?>
        <span class="collection-tile__overlay" aria-hidden="true"></span>
        <span class="collection-tile__content"><span class="collection-tile__title"><?php echo esc_html(get_the_title($service_post)); ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="home-section">
  <div class="container">
    <div class="home-section__head">
      <h2 class="home-section__title"><?php echo esc_html($home['brands_title']); ?></h2>
      <a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="home-section__link"><?php echo esc_html($home['brands_link']); ?></a>
    </div>
    <div class="drops-scroll">
      <?php foreach ($new_drop as $brand) : ?>
      <a href="<?php echo esc_url(add_query_arg('brand', $brand, nw_fuel_products_url())); ?>" class="drop-tile">
        <span class="drop-tile__brand"><?php esc_html_e('Authorized line', 'nw-fuel'); ?></span>
        <span class="drop-tile__name"><?php echo esc_html($brand); ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="home-section home-section--gray">
  <div class="container">
    <div class="home-duo">
      <div class="home-duo__card home-duo__card--dark">
        <p class="home-duo__label"><?php echo esc_html($home['duo_shop_label']); ?></p>
        <h3 class="home-duo__title"><?php echo esc_html($home['duo_shop_title']); ?></h3>
        <p class="home-duo__text"><?php echo esc_html($home['duo_shop_text']); ?></p>
      </div>
      <div class="home-duo__card">
        <p class="home-duo__label"><?php echo esc_html($home['duo_promise_label']); ?></p>
        <h3 class="home-duo__title"><?php echo esc_html($home['duo_promise_title']); ?></h3>
        <p class="home-duo__text"><?php echo esc_html($home['duo_promise_text']); ?></p>
      </div>
    </div>
  </div>
</section>

<section class="home-banner">
  <img class="home-banner__bg" src="<?php echo esc_url($images['industrialWorkshop']['lg'] ?? $images['labEquipment']['lg'] ?? $images['hero'] ?? ''); ?>" alt="<?php esc_attr_e('Diesel injection repair and fabrication at NW Fuel', 'nw-fuel'); ?>">
  <div class="home-banner__overlay" aria-hidden="true"></div>
  <div class="container home-banner__content">
    <p class="home-banner__eyebrow"><?php echo esc_html($home['banner_eyebrow']); ?></p>
    <h2 class="home-banner__title"><?php echo esc_html($home['banner_title']); ?></h2>
    <p class="home-banner__text"><?php echo esc_html($home['banner_text']); ?></p>
    <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="home-banner__btn"><?php echo esc_html($home['banner_cta']); ?></a>
  </div>
</section>

<?php if ($more_parts) :
    get_template_part('template-parts/home-parts-showcase', null, [
        'showcase_heading_id' => 'more-diesel-parts-heading',
        'showcase_title'      => $home['more_parts_title'],
        'showcase_text'       => $home['more_parts_text'],
        'showcase_cta_href'   => nw_fuel_products_url(),
        'showcase_cta_label'  => $home['parts_cta'],
        'showcase_products'   => $more_parts,
        'showcase_gray'       => true,
    ]);
endif; ?>

<section class="home-section home-section--gray">
  <div class="container">
    <div class="home-section__head">
      <h2 class="home-section__title"><?php echo esc_html($home['blog_title']); ?></h2>
      <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/blog/')); ?>" class="home-section__link"><?php echo esc_html($home['blog_link']); ?></a>
    </div>
    <div class="blog-grid--home">
      <?php foreach ($blog_posts as $post) :
          get_template_part('template-parts/home-blog-card', null, ['post' => $post]);
      endforeach; ?>
    </div>
  </div>
</section>

<section class="home-newsletter" id="email-updates">
  <div class="container">
    <h2 class="home-newsletter__title"><?php echo esc_html($home['newsletter_title']); ?></h2>
    <p class="home-newsletter__sub"><?php echo esc_html($home['newsletter_sub']); ?></p>
    <?php if ($newsletter_feedback === 'success') : ?>
    <p class="home-newsletter__notice" role="status"><?php esc_html_e('Thanks — you’re on the list. We’ll send shop updates to that address.', 'nw-fuel'); ?></p>
    <?php elseif ($newsletter_feedback === 'error') : ?>
    <p class="home-newsletter__notice home-newsletter__notice--error" role="alert"><?php esc_html_e('Sorry, we couldn’t subscribe that email. Please try again or email info@nwfuel.ca.', 'nw-fuel'); ?></p>
    <?php endif; ?>
    <?php if ($newsletter_feedback !== 'success') : ?>
    <form class="home-newsletter__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" aria-label="<?php esc_attr_e('Newsletter signup', 'nw-fuel'); ?>">
      <?php wp_nonce_field('nw_fuel_newsletter', 'nw_fuel_newsletter_nonce'); ?>
      <input type="hidden" name="action" value="nw_fuel_newsletter">
      <div class="home-newsletter__hp" aria-hidden="true">
        <label for="nw-newsletter-website"><?php esc_html_e('Website', 'nw-fuel'); ?></label>
        <input id="nw-newsletter-website" type="text" name="website" tabindex="-1" autocomplete="off">
      </div>
      <input type="email" name="email" placeholder="<?php esc_attr_e('Enter your email', 'nw-fuel'); ?>" required aria-label="<?php esc_attr_e('Email address', 'nw-fuel'); ?>" autocomplete="email">
      <button type="submit"><?php echo esc_html($home['newsletter_cta']); ?></button>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="trust-row" aria-label="<?php esc_attr_e('Why choose NW Fuel', 'nw-fuel'); ?>">
  <div class="trust-item">
    <p class="trust-item__title"><?php echo esc_html($home['trust_1_title']); ?></p>
    <p class="trust-item__text"><?php echo esc_html($home['trust_1_text']); ?></p>
  </div>
  <div class="trust-item">
    <p class="trust-item__title"><?php echo esc_html($home['trust_2_title']); ?></p>
    <p class="trust-item__text"><?php echo esc_html($home['trust_2_text']); ?></p>
  </div>
  <div class="trust-item">
    <p class="trust-item__title"><?php echo esc_html($home['trust_3_title']); ?></p>
    <p class="trust-item__text"><?php echo esc_html($home['trust_3_text']); ?></p>
  </div>
</section>

<?php get_footer(); ?>
