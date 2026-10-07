<?php
/**
 * Contact page template — all copy from Pages → Contact Us content editor.
 *
 * Template Name: Contact
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

$business = nw_fuel_business();
$images   = nw_fuel_images();
$c        = nw_fuel_contact_content();
$feedback = nw_fuel_form_feedback('contact');
$address  = is_array($business['address'] ?? null) ? $business['address'] : [];
$stats    = is_array($c['stats'] ?? null) ? $c['stats'] : [];
$faqs     = is_array($c['faqs'] ?? null) ? $c['faqs'] : [];
?>
<section class="hero hero--contact">
  <img class="hero__bg" src="<?php echo esc_url($images['commercialTruck']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html((string) ($c['hero_eyebrow'] ?? '')); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html((string) ($c['hero_title'] ?? '')); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html((string) ($c['hero_desc'] ?? '')); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html($business['phone'] ?? ''); ?></a>
      <a href="mailto:<?php echo esc_attr($business['email'] ?? ''); ?>" class="btn btn--outline-white btn--lg"><?php echo esc_html((string) ($c['hero_cta_email'] ?? '')); ?></a>
    </div>
  </div>
</section>

<?php if ($stats) : ?>
<section class="contact-stats" aria-label="<?php echo esc_attr((string) ($c['hero_title'] ?? '')); ?>">
  <div class="container contact-stats__inner">
    <?php foreach ($stats as $stat) :
        if (! is_array($stat)) {
            continue;
        }
        $value = ! empty($stat['use_phone']) ? (string) ($business['phone'] ?? '') : (string) ($stat['value'] ?? '');
        ?>
    <div class="contact-stats__item">
      <span class="contact-stats__value"><?php echo esc_html($value); ?></span>
      <span class="contact-stats__label"><?php echo esc_html((string) ($stat['label'] ?? '')); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="contact-main">
  <div class="container contact-main__grid">
    <div class="contact-details">
      <p class="contact-panel__eyebrow"><?php echo esc_html((string) ($c['details_eyebrow'] ?? '')); ?></p>
      <h2 class="contact-panel__title"><?php echo esc_html((string) ($c['details_title'] ?? '')); ?></h2>

      <div class="contact-card">
        <h3 class="contact-card__label"><?php echo esc_html((string) ($c['address_label'] ?? '')); ?></h3>
        <p class="contact-card__text">
          <?php echo esc_html($address['street'] ?? ''); ?><br>
          <?php echo esc_html(trim(($address['city'] ?? '') . ', ' . ($address['province'] ?? '') . ' ' . ($address['postalCode'] ?? ''))); ?>
        </p>
        <?php if ((string) ($c['address_note'] ?? '') !== '') : ?>
        <p class="contact-card__note"><?php echo esc_html((string) $c['address_note']); ?></p>
        <?php endif; ?>
      </div>

      <div class="contact-card">
        <h3 class="contact-card__label"><?php echo esc_html((string) ($c['phone_email_label'] ?? '')); ?></h3>
        <p class="contact-card__text">
          <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>"><?php echo esc_html($business['phone'] ?? ''); ?></a><br>
          <a href="mailto:<?php echo esc_attr($business['email'] ?? ''); ?>"><?php echo esc_html($business['email'] ?? ''); ?></a>
        </p>
      </div>

      <div class="contact-card">
        <h3 class="contact-card__label"><?php echo esc_html((string) ($c['hours_label'] ?? '')); ?></h3>
        <p class="contact-card__text"><?php echo esc_html($business['hours'] ?? ''); ?></p>
        <?php if ((string) ($c['hours_note'] ?? '') !== '') : ?>
        <p class="contact-card__note"><?php echo esc_html((string) $c['hours_note']); ?></p>
        <?php endif; ?>
      </div>

      <div class="contact-card contact-card--dark">
        <h3 class="contact-card__label"><?php echo esc_html((string) ($c['island_label'] ?? '')); ?></h3>
        <p class="contact-card__text"><?php echo nl2br(esc_html((string) ($c['island_address'] ?? ''))); ?></p>
        <p class="contact-card__text">
          <a href="<?php echo esc_url(nw_fuel_phone_href((string) ($c['island_phone'] ?? ''))); ?>"><?php echo esc_html((string) ($c['island_phone'] ?? '')); ?></a><br>
          <a href="<?php echo esc_url((string) ($c['island_website'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) ($c['island_website_label'] ?? '')); ?></a>
        </p>
      </div>
    </div>

    <div class="contact-form-wrap">
      <p class="contact-panel__eyebrow"><?php echo esc_html((string) ($c['form_eyebrow'] ?? '')); ?></p>
      <h2 class="contact-panel__title"><?php echo esc_html((string) ($c['form_title'] ?? '')); ?></h2>

      <?php if ($feedback === 'success') : ?>
      <div id="form-success" class="contact-form__success">
        <h3 class="contact-form__success-title"><?php echo esc_html((string) ($c['form_success_title'] ?? '')); ?></h3>
        <p class="contact-form__success-text"><?php echo esc_html((string) ($c['form_success_text'] ?? '')); ?></p>
      </div>
      <?php elseif ($feedback === 'error') : ?>
      <div class="contact-form__success">
        <p><?php esc_html_e('Sorry, your message could not be sent. Please call the shop directly.', 'nw-fuel'); ?></p>
      </div>
      <?php else : ?>
      <form id="contact-form" class="contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" aria-label="<?php esc_attr_e('Contact form', 'nw-fuel'); ?>">
        <?php wp_nonce_field('nw_fuel_contact', 'nw_fuel_contact_nonce'); ?>
        <input type="hidden" name="action" value="nw_fuel_contact">
        <div class="contact-form__row">
          <div class="contact-form__group">
            <label for="firstName"><?php esc_html_e('First Name *', 'nw-fuel'); ?></label>
            <input id="firstName" name="firstName" type="text" required autocomplete="given-name">
          </div>
          <div class="contact-form__group">
            <label for="lastName"><?php esc_html_e('Last Name *', 'nw-fuel'); ?></label>
            <input id="lastName" name="lastName" type="text" required autocomplete="family-name">
          </div>
        </div>
        <div class="contact-form__row">
          <div class="contact-form__group">
            <label for="email"><?php esc_html_e('Email *', 'nw-fuel'); ?></label>
            <input id="email" name="email" type="email" required autocomplete="email">
          </div>
          <div class="contact-form__group">
            <label for="phone"><?php esc_html_e('Phone', 'nw-fuel'); ?></label>
            <input id="phone" name="phone" type="tel" autocomplete="tel">
          </div>
        </div>
        <div class="contact-form__group">
          <label for="subject"><?php esc_html_e('Subject *', 'nw-fuel'); ?></label>
          <select id="subject" name="subject" required>
            <option value=""><?php esc_html_e('Select a topic', 'nw-fuel'); ?></option>
            <option value="quote"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></option>
            <option value="injector-testing"><?php esc_html_e('Injector Testing', 'nw-fuel'); ?></option>
            <option value="pump-rebuild"><?php esc_html_e('Pump Rebuilding', 'nw-fuel'); ?></option>
            <option value="parts"><?php esc_html_e('Parts Inquiry', 'nw-fuel'); ?></option>
            <option value="technical"><?php esc_html_e('Technical Support', 'nw-fuel'); ?></option>
            <option value="other"><?php esc_html_e('Other', 'nw-fuel'); ?></option>
          </select>
        </div>
        <div class="contact-form__group">
          <label for="message"><?php esc_html_e('Message *', 'nw-fuel'); ?></label>
          <textarea id="message" name="message" rows="5" required placeholder="<?php esc_attr_e('Describe your diesel injection needs...', 'nw-fuel'); ?>"></textarea>
        </div>
        <button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e('Send Message', 'nw-fuel'); ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="contact-map">
  <div class="container">
    <p class="contact-panel__eyebrow"><?php echo esc_html((string) ($c['map_eyebrow'] ?? '')); ?></p>
    <h2 class="contact-panel__title contact-panel__title--section"><?php echo esc_html((string) ($c['map_title'] ?? '')); ?></h2>
    <div class="contact-map__embed">
      <iframe title="<?php echo esc_attr((string) ($c['map_title'] ?? '')); ?>" src="<?php echo esc_url((string) ($c['map_embed_url'] ?? '')); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>

<?php if ($faqs) :
    $id = 'contact-faq';
    ?>
<section class="contact-faq">
  <div class="container contact-faq__inner">
    <p class="contact-panel__eyebrow"><?php echo esc_html((string) ($c['faq_eyebrow'] ?? '')); ?></p>
    <h2 class="contact-panel__title contact-panel__title--section"><?php echo esc_html((string) ($c['faq_title'] ?? '')); ?></h2>
    <?php get_template_part('template-parts/faq', null, compact('faqs', 'id')); ?>
  </div>
</section>
<?php endif; ?>

<?php
get_template_part('template-parts/cta', null, [
    'title'       => (string) ($c['cta_title'] ?? ''),
    'description' => (string) ($c['cta_description'] ?? ''),
]);
get_footer();
