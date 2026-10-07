<?php
/**
 * 404 template — matches site chrome and CTA patterns.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();
?>
<section class="page-hero page-hero--short">
  <div class="container page-hero__content">
    <p class="eyebrow"><?php esc_html_e('Page not found', 'nw-fuel'); ?></p>
    <h1><?php esc_html_e('404', 'nw-fuel'); ?></h1>
    <p><?php esc_html_e('The page you requested does not exist or has moved. Try the parts catalog, services, or contact the shop.', 'nw-fuel'); ?></p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem;">
      <a class="btn btn--primary btn--lg" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to Home', 'nw-fuel'); ?></a>
      <a class="btn btn--outline btn--lg" href="<?php echo esc_url(nw_fuel_products_url()); ?>"><?php esc_html_e('Browse Parts', 'nw-fuel'); ?></a>
      <a class="btn btn--outline btn--lg" href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>"><?php esc_html_e('Contact', 'nw-fuel'); ?></a>
    </div>
  </div>
</section>
<?php
get_template_part('template-parts/cta', null, [
    'title'       => __('Need Help Finding Something?', 'nw-fuel'),
    'description' => __('Call the shop or request a quote — our technicians can point you to the right part or service.', 'nw-fuel'),
]);
get_footer();
