<?php
/**
 * Site footer.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

$business           = nw_fuel_business();
$services           = nw_fuel_services();
$product_categories = nw_fuel_product_categories();
?>
</main>
<footer class="site-footer">
  <div class="container site-footer__grid">
    <div>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="logo logo--footer" aria-label="<?php esc_attr_e('NW Fuel home', 'nw-fuel'); ?>">
        <img src="<?php echo esc_url(nw_fuel_asset_url('assets/img/logo1.png')); ?>" alt="<?php esc_attr_e('NW Fuel Injection Service, Diesel Done Right', 'nw-fuel'); ?>" class="logo__img" width="340" height="112" loading="lazy">
      </a>
      <p class="site-footer__desc"><?php
        $footer_desc = (string) ($business['description'] ?? '');
        if ($footer_desc === '') {
            $footer_desc = __('Bosch authorized diesel injection shop in Surrey, BC. Injector testing, pump rebuilds, and OEM parts for repair shops since 1968.', 'nw-fuel');
        }
        echo esc_html($footer_desc);
      ?></p>
      <div class="social-links">
        <?php foreach (($business['social'] ?? []) as $label => $url) : if (! $url) { continue; } ?>
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(ucfirst((string) $label)); ?>"><?php echo nw_fuel_social_icon_svg((string) $label); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h3><?php esc_html_e('Services', 'nw-fuel'); ?></h3>
      <?php if (nw_fuel_has_footer_menu()) : ?>
        <?php
        wp_nav_menu([
            'theme_location' => 'footer',
            'container'      => false,
            'depth'          => 1,
            'fallback_cb'    => false,
            'items_wrap'     => '<ul>%3$s</ul>',
            'walker'         => new NW_Fuel_Footer_Nav_Walker(),
        ]);
        ?>
      <?php else : ?>
      <ul>
        <?php foreach (array_slice($services, 0, 6) as $service_post) : ?>
        <li><a href="<?php echo esc_url(get_permalink($service_post)); ?>"><?php echo esc_html(get_the_title($service_post)); ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="footer-link-accent"><?php esc_html_e('All Services →', 'nw-fuel'); ?></a></li>
      </ul>
      <?php endif; ?>
    </div>
    <div>
      <h3><?php esc_html_e('Products', 'nw-fuel'); ?></h3>
      <ul>
        <?php foreach ($product_categories as $cat) : ?>
        <li><a href="<?php echo esc_url(add_query_arg('category', $cat, nw_fuel_products_url())); ?>"><?php echo esc_html($cat); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3><?php esc_html_e('Contact', 'nw-fuel'); ?></h3>
      <ul class="contact-list">
        <li><?php echo esc_html($business['address']['street'] ?? ''); ?><br><?php echo esc_html(($business['address']['city'] ?? '') . ', ' . ($business['address']['province'] ?? '')); ?></li>
        <li><a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>"><?php echo esc_html($business['phone'] ?? ''); ?></a></li>
        <li><a href="mailto:<?php echo esc_attr($business['email'] ?? ''); ?>"><?php echo esc_html($business['email'] ?? ''); ?></a></li>
      </ul>
      <p class="site-footer__hours"><?php echo esc_html($business['hours'] ?? ''); ?></p>
    </div>
  </div>
  <?php if (is_active_sidebar('footer-widgets')) : ?>
  <div class="container" style="padding-bottom:2rem;">
    <?php dynamic_sidebar('footer-widgets'); ?>
  </div>
  <?php endif; ?>
  <div class="container site-footer__bottom">
    <p class="site-footer__credit">&copy; <?php echo esc_html(gmdate('Y')); ?> NW Fuel Injection Services Ltd. <?php esc_html_e('All rights reserved.', 'nw-fuel'); ?></p>
    <p class="site-footer__credit"><?php esc_html_e('Designed & Developed By', 'nw-fuel'); ?> <a href="https://webpulse.ca" target="_blank" rel="noopener noreferrer">Webpulse AI</a></p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
