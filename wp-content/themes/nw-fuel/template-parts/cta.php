<?php
/**
 * CTA banner partial (matches static includes/cta.php).
 *
 * @package NW_Fuel
 */

$title = $args['title'] ?? ($title ?? __('Need a part or a quote?', 'nw-fuel'));
$description = $args['description'] ?? ($description ?? __('Call our Surrey shop for injector testing, pump rebuilds, and OEM diesel parts. Bosch authorized since 1968.', 'nw-fuel'));
$business = nw_fuel_business();
?>
<section class="cta-banner">
  <div class="container cta-banner__inner">
    <h2><?php echo esc_html($title); ?></h2>
    <p><?php echo esc_html($description); ?></p>
    <div class="cta-banner__actions">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--white"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></a>
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline-white"><?php echo esc_html($business['phone'] ?? ''); ?></a>
    </div>
  </div>
</section>
