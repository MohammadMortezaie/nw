<?php
/**
 * Single service template — matched to static services/detail.php.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

while (have_posts()) :
    the_post();
    $service_id = get_the_ID();
    $business   = nw_fuel_business();
    $benefits   = nw_fuel_json_meta($service_id, '_nw_benefits');
    $process    = nw_fuel_json_meta($service_id, '_nw_process');
    $pricing    = nw_fuel_json_meta($service_id, '_nw_pricing');
    $faqs       = nw_fuel_json_meta($service_id, '_nw_service_faqs');
    $related    = nw_fuel_related_services(nw_fuel_json_meta($service_id, '_nw_related_slugs'));
    $sections   = nw_fuel_json_meta($service_id, '_nw_detail_sections');
    $feature_cards = nw_fuel_json_meta($service_id, '_nw_feature_cards');
    $video      = nw_fuel_json_meta($service_id, '_nw_video_section');
    // video stored as object — json_meta returns array; empty list means missing
    if ($video !== [] && array_is_list($video)) {
        $video = [];
    }
    $hero       = get_post_meta($service_id, '_nw_hero_image', true) ?: get_the_post_thumbnail_url($service_id, 'large');
    $highlights = array_slice($benefits, 0, 4);
    $quote_feedback = nw_fuel_form_feedback('quote');
    $slug       = get_post_field('post_name', $service_id);
    $has_pricing_notes = (bool) array_filter($pricing, static fn($row): bool => is_array($row) && ! empty($row['note']));
    ?>
<section class="service-detail-top">
  <div class="container">
    <?php
    $items = [
        ['label' => __('Services', 'nw-fuel'), 'href' => get_post_type_archive_link('nw_service')],
        ['label' => get_the_title()],
    ];
    get_template_part('template-parts/breadcrumbs', null, compact('items'));
    ?>
  </div>
</section>

<section class="service-detail-main">
  <div class="container service-detail__grid">
    <div class="service-media">
      <?php if ($hero) : ?><img src="<?php echo esc_url($hero); ?>" alt="<?php the_title_attribute(); ?>"><?php endif; ?>
    </div>
    <div class="service-info">
      <p class="service-info__eyebrow"><?php esc_html_e('NW Fuel Services', 'nw-fuel'); ?></p>
      <h1 class="service-info__title"><?php the_title(); ?></h1>
      <p class="service-info__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
      <div class="service-info__actions">
        <button type="button" class="btn btn--primary btn--lg" data-quote-toggle aria-expanded="false" aria-controls="service-quote-form-wrap"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></button>
        <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline btn--lg"><?php echo esc_html(sprintf(/* translators: %s: phone */ __('Call %s', 'nw-fuel'), $business['phone'] ?? '')); ?></a>
      </div>
      <div class="service-quote" id="service-quote-form-wrap" <?php echo $quote_feedback ? '' : 'hidden'; ?>>
        <?php if ($quote_feedback === 'success') : ?>
        <div class="service-quote__success" data-quote-success>
          <span class="service-quote__success-icon" aria-hidden="true"></span>
          <h2 class="service-quote__success-title"><?php esc_html_e('Quote Request Sent', 'nw-fuel'); ?></h2>
          <p class="service-quote__success-text"><?php esc_html_e('Thank you. Our team will review your service request, then respond by email or call you.', 'nw-fuel'); ?></p>
        </div>
        <?php elseif ($quote_feedback === 'error') : ?>
        <p><?php esc_html_e('Sorry, your quote request could not be sent.', 'nw-fuel'); ?></p>
        <?php else : ?>
        <form class="service-quote__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" aria-label="<?php esc_attr_e('Service quote request', 'nw-fuel'); ?>">
          <?php wp_nonce_field('nw_fuel_quote', 'nw_fuel_quote_nonce'); ?>
          <input type="hidden" name="action" value="nw_fuel_quote">
          <input type="hidden" name="service" value="<?php the_title_attribute(); ?>">
          <input type="hidden" name="serviceSlug" value="<?php echo esc_attr((string) $slug); ?>">
          <div class="service-quote__row">
            <div class="service-quote__group">
              <label for="service-quote-name"><?php esc_html_e('Full Name *', 'nw-fuel'); ?></label>
              <input id="service-quote-name" name="name" type="text" required autocomplete="name">
            </div>
            <div class="service-quote__group">
              <label for="service-quote-email"><?php esc_html_e('Email Address *', 'nw-fuel'); ?></label>
              <input id="service-quote-email" name="email" type="email" required autocomplete="email">
            </div>
          </div>
          <div class="service-quote__group">
            <label for="service-quote-phone"><?php esc_html_e('Phone', 'nw-fuel'); ?></label>
            <input id="service-quote-phone" name="phone" type="tel" autocomplete="tel">
          </div>
          <div class="service-quote__group">
            <label for="service-quote-note"><?php esc_html_e('Extra Note', 'nw-fuel'); ?></label>
            <textarea id="service-quote-note" name="note" rows="4" placeholder="<?php esc_attr_e('Tell us what vehicle, engine, symptoms, deadline, or parts you need help with.', 'nw-fuel'); ?>"></textarea>
          </div>
          <button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e('Submit Quote Request', 'nw-fuel'); ?></button>
        </form>
        <?php endif; ?>
      </div>
      <?php if ($highlights) : ?>
      <div class="service-info__highlights">
        <h2 class="service-info__highlights-title"><?php esc_html_e('Why This Service', 'nw-fuel'); ?></h2>
        <ul class="service-list">
          <?php foreach ($highlights as $benefit) : ?>
          <li><?php echo esc_html((string) $benefit); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="service-detail-panel">
  <div class="container service-detail-panel--narrow">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('Overview', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title service-detail-panel__title--lg"><?php esc_html_e('Service Overview', 'nw-fuel'); ?></h2>
    <div class="service-detail-panel__text"><?php the_content(); ?></div>
  </div>
</section>

<?php foreach ($sections as $i => $section) :
    if (! is_array($section)) {
        continue;
    }
    ?>
<section class="service-detail-panel<?php echo $i % 2 === 0 ? ' service-detail-panel--gray' : ''; ?>">
  <div class="container">
    <div class="service-copy-grid<?php echo ! empty($section['image']) ? ' service-copy-grid--with-media' : ''; ?>">
      <div class="service-copy-grid__head">
        <?php if (! empty($section['eyebrow'])) : ?>
        <p class="service-detail-panel__eyebrow"><?php echo esc_html((string) $section['eyebrow']); ?></p>
        <?php endif; ?>
        <h2 class="service-detail-panel__title"><?php echo esc_html((string) ($section['title'] ?? '')); ?></h2>
        <?php if (! empty($section['image']['src'])) : ?>
        <figure class="service-copy-media">
          <img src="<?php echo esc_url(nw_fuel_resolve_image_url((string) $section['image']['src'])); ?>" alt="<?php echo esc_attr((string) ($section['image']['alt'] ?? '')); ?>" loading="lazy">
        </figure>
        <?php endif; ?>
      </div>
      <div class="service-copy-grid__body">
        <?php if (! empty($section['text'])) : ?>
        <p class="service-detail-panel__text"><?php echo esc_html((string) $section['text']); ?></p>
        <?php endif; ?>
        <?php if (! empty($section['items']) && is_array($section['items'])) : ?>
        <ul class="service-list service-list--compact">
          <?php foreach ($section['items'] as $item) : ?>
          <li><?php echo esc_html((string) $item); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if (! empty($section['link']['href']) && ! empty($section['link']['label'])) : ?>
        <a class="service-detail-link" href="<?php echo esc_url((string) $section['link']['href']); ?>"><?php echo esc_html((string) $section['link']['label']); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php if ($feature_cards) : ?>
<section class="service-detail-panel">
  <div class="container">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('Service Fit', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title"><?php esc_html_e('Injectors We Service', 'nw-fuel'); ?></h2>
    <div class="service-feature-grid">
      <?php foreach ($feature_cards as $card) :
          if (! is_array($card)) {
              continue;
          }
          ?>
      <article class="service-feature">
        <h3 class="service-feature__title"><?php echo esc_html((string) ($card['title'] ?? '')); ?></h3>
        <p class="service-feature__text"><?php echo esc_html((string) ($card['text'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($video && ! empty($video['embedUrl'])) : ?>
<section class="service-detail-panel">
  <div class="container">
    <div class="service-video-grid">
      <div class="service-video-copy">
        <?php if (! empty($video['eyebrow'])) : ?>
        <p class="service-detail-panel__eyebrow"><?php echo esc_html((string) $video['eyebrow']); ?></p>
        <?php endif; ?>
        <h2 class="service-detail-panel__title"><?php echo esc_html((string) ($video['title'] ?? '')); ?></h2>
        <?php if (! empty($video['text'])) : ?>
        <p class="service-detail-panel__text"><?php echo esc_html((string) $video['text']); ?></p>
        <?php endif; ?>
        <?php if (! empty($video['items']) && is_array($video['items'])) : ?>
        <ul class="service-list service-list--compact">
          <?php foreach ($video['items'] as $item) : ?>
          <li><?php echo esc_html((string) $item); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="service-video-frame">
        <iframe
          src="<?php echo esc_url((string) $video['embedUrl']); ?>"
          title="<?php echo esc_attr((string) ($video['videoTitle'] ?? get_the_title())); ?>"
          loading="lazy"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen></iframe>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($benefits) : ?>
<section class="service-detail-panel service-detail-panel--gray">
  <div class="container">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('Benefits', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title"><?php esc_html_e('Why Choose This Service', 'nw-fuel'); ?></h2>
    <ul class="service-list service-list--wide">
      <?php foreach ($benefits as $benefit) : ?>
      <li><?php echo esc_html((string) $benefit); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($pricing) : ?>
<section class="service-detail-panel">
  <div class="container">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('Pricing', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title"><?php esc_html_e('Service Pricing', 'nw-fuel'); ?></h2>
    <div class="service-table-tools">
      <label class="service-table-search" for="service-pricing-search-<?php echo esc_attr((string) $slug); ?>">
        <span><?php esc_html_e('Search This Table', 'nw-fuel'); ?></span>
        <input id="service-pricing-search-<?php echo esc_attr((string) $slug); ?>" type="search" placeholder="<?php esc_attr_e('Try Duramax, Cummins, GDI, 6.0, pump...', 'nw-fuel'); ?>" data-service-table-search>
      </label>
      <p class="service-table-search__status" data-service-table-status aria-live="polite"></p>
    </div>
    <div class="service-table-wrap" data-service-table-wrap>
      <table class="service-table">
        <thead>
          <tr>
            <th scope="col"><?php esc_html_e('Style', 'nw-fuel'); ?></th>
            <th scope="col"><?php esc_html_e('Price', 'nw-fuel'); ?></th>
            <th scope="col"><?php esc_html_e('Turnaround', 'nw-fuel'); ?></th>
            <?php if ($has_pricing_notes) : ?>
            <th scope="col"><?php esc_html_e('Notes', 'nw-fuel'); ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pricing as $row) :
              if (! is_array($row)) {
                  continue;
              }
              ?>
          <tr>
            <td><?php echo esc_html((string) ($row['item'] ?? '')); ?></td>
            <td><?php echo esc_html((string) ($row['price'] ?? '')); ?></td>
            <td><?php echo esc_html((string) ($row['timeframe'] ?? '')); ?></td>
            <?php if ($has_pricing_notes) : ?>
            <td><?php echo esc_html((string) ($row['note'] ?? '')); ?></td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($process) : ?>
<section class="service-detail-panel service-detail-panel--gray">
  <div class="container">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('Process', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title"><?php esc_html_e('Our Service Process', 'nw-fuel'); ?></h2>
    <div class="service-steps">
      <?php foreach ($process as $step) :
          if (! is_array($step)) {
              continue;
          }
          ?>
      <article class="service-step">
        <span class="service-step__num"><?php echo esc_html((string) ($step['step'] ?? '')); ?></span>
        <h3 class="service-step__title"><?php echo esc_html((string) ($step['title'] ?? '')); ?></h3>
        <p class="service-step__text"><?php echo esc_html((string) ($step['description'] ?? '')); ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($faqs) : ?>
<section class="service-detail-panel">
  <div class="container service-detail-panel--narrow">
    <p class="service-detail-panel__eyebrow"><?php esc_html_e('FAQ', 'nw-fuel'); ?></p>
    <h2 class="service-detail-panel__title service-detail-panel__title--lg"><?php esc_html_e('Service Questions', 'nw-fuel'); ?></h2>
    <?php
    $id = 'service-faq';
    get_template_part('template-parts/faq', null, compact('faqs', 'id'));
    ?>
  </div>
</section>
<?php endif; ?>

<?php if ($related) : ?>
<section class="service-detail-panel service-detail-panel--gray">
  <div class="container">
    <div class="service-detail-related__head">
      <h2 class="service-detail-panel__title service-detail-panel__title--lg"><?php esc_html_e('Related Services', 'nw-fuel'); ?></h2>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="service-detail-related__link"><?php esc_html_e('All Services', 'nw-fuel'); ?></a>
    </div>
    <div class="services-grid services-grid--related">
      <?php foreach ($related as $service) {
          get_template_part('template-parts/service-catalog-card', null, ['service' => $service]);
      } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
    get_template_part('template-parts/cta');
endwhile;
get_footer();
