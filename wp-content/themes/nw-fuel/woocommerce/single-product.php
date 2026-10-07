<?php
/**
 * Single product template.
 *
 * @package NW_Fuel
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    $product    = wc_get_product(get_the_ID());
    if (! $product) {
        continue;
    }

    $product_id = $product->get_id();
    $business   = nw_fuel_business();
    $part       = nw_fuel_get_part_number($product_id);
    $code       = nw_fuel_get_product_code($product_id);
    $gallery    = nw_fuel_product_gallery_urls($product_id);
    $inventory  = nw_fuel_product_inventory($product_id);
    $features   = nw_fuel_json_meta($product_id, '_nw_features');
    $specs      = nw_fuel_json_meta($product_id, '_nw_specifications');
    $vehicles   = nw_fuel_json_meta($product_id, '_nw_compatible_vehicles');
    $alts       = nw_fuel_get_search_alts($product_id);
    $faqs       = nw_fuel_json_meta($product_id, '_nw_product_faqs');
    $related    = nw_fuel_related_products(nw_fuel_json_meta($product_id, '_nw_related_slugs'));
    $category   = nw_fuel_product_category_name($product_id);
    $vehicle_terms = nw_fuel_product_term_names($product_id, 'pa_vehicle-type');
    $engine_terms  = nw_fuel_product_term_names($product_id, 'pa_engine-type');
    $brand      = nw_fuel_product_term_names($product_id, 'pa_brand')[0] ?? '';
    $quote_feedback = nw_fuel_form_feedback('quote');
    ?>
<section class="product-detail-top"><div class="container"><?php $items = [['label' => __('Products', 'nw-fuel'), 'href' => nw_fuel_products_url()], ['label' => $product->get_name()]]; get_template_part('template-parts/breadcrumbs', null, compact('items')); ?></div></section>

<section class="product-detail-main" data-nw-product-view="<?php echo esc_attr((string) $product_id); ?>">
  <div class="container product-detail__grid">
    <div class="product-gallery" data-gallery>
      <div class="product-gallery__main">
        <?php if ($gallery) : ?>
        <a href="<?php echo esc_url($gallery[0]); ?>" class="product-gallery__zoom" data-gallery-lightbox aria-label="<?php esc_attr_e('View larger image', 'nw-fuel'); ?>">
          <img id="gallery-main" src="<?php echo esc_url($gallery[0]); ?>" alt="<?php echo esc_attr($product->get_name() . ', ' . $brand); ?>">
        </a>
        <button type="button" class="product-gallery__zoom-toggle" data-gallery-zoom-toggle aria-label="<?php esc_attr_e('Zoom in', 'nw-fuel'); ?>" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            <line class="product-gallery__zoom-toggle-h" x1="8" y1="11" x2="14" y2="11"></line>
            <line class="product-gallery__zoom-toggle-v" x1="11" y1="8" x2="11" y2="14"></line>
          </svg>
        </button>
        <?php endif; ?>
        <?php if (count($gallery) > 1) : ?>
        <button type="button" class="product-gallery__nav product-gallery__nav--prev" data-gallery-prev aria-label="<?php esc_attr_e('Previous image', 'nw-fuel'); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <button type="button" class="product-gallery__nav product-gallery__nav--next" data-gallery-next aria-label="<?php esc_attr_e('Next image', 'nw-fuel'); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
        <?php endif; ?>
        <span class="product-gallery__tag"><?php echo esc_html($category); ?></span>
      </div>
      <?php if (count($gallery) > 1) : ?>
      <div class="product-gallery__thumbs">
        <?php foreach ($gallery as $i => $img) : ?>
        <button type="button" class="product-gallery__thumb<?php echo $i === 0 ? ' is-active' : ''; ?>" data-src="<?php echo esc_url($img); ?>" aria-label="<?php echo esc_attr(sprintf(__('View product image %d', 'nw-fuel'), $i + 1)); ?>"><img src="<?php echo esc_url($img); ?>" alt=""></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="product-buy">
      <p class="product-buy__brand"><?php echo esc_html($brand); ?></p>
      <?php if ($part) : ?><p class="product-buy__part"><span class="product-buy__part-label"><?php esc_html_e('Part #', 'nw-fuel'); ?></span> <strong><?php echo esc_html($part); ?></strong></p><?php endif; ?>
      <?php if ($alts) : ?>
      <div class="product-buy__alts">
        <p class="product-buy__alts-label"><?php esc_html_e('Alternates', 'nw-fuel'); ?></p>
        <ul class="product-buy__alts-list">
          <?php foreach ($alts as $alt) : ?><li><?php echo esc_html((string) $alt); ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <h1 class="product-buy__title"><?php echo esc_html($product->get_name()); ?></h1>

      <?php
      $short_desc = trim((string) get_post_meta($product_id, '_nw_short_description', true));
      if ($short_desc === '') {
          $short_desc = trim((string) $product->get_short_description());
      }
      $long_desc = trim((string) $product->get_description());
      ?>

      <?php if ($short_desc !== '') : ?>
      <div class="product-buy__copy product-buy__copy--lead">
        <p class="product-buy__copy-label"><?php esc_html_e('Overview', 'nw-fuel'); ?></p>
        <div class="product-buy__desc product-buy__desc--lead"><?php echo nw_fuel_rich_text($short_desc); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
      </div>
      <?php endif; ?>

      <?php if ($inventory) :
          nw_fuel_render_inventory($inventory, 'detail');
      else : ?>
      <div class="product-buy__pricing"><p class="product-buy__price-label"><?php esc_html_e('Price', 'nw-fuel'); ?></p><p class="product-buy__quote"><?php esc_html_e('Call for price and availability', 'nw-fuel'); ?></p></div>
      <?php endif; ?>

      <?php if ($long_desc !== '') : ?>
      <div class="product-buy__copy">
        <p class="product-buy__copy-label"><?php esc_html_e('Description', 'nw-fuel'); ?></p>
        <div class="product-buy__desc"><?php echo nw_fuel_rich_text($long_desc); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
      </div>
      <?php endif; ?>

      <div class="product-buy__actions">
        <button type="button" class="btn btn--primary btn--lg" data-quote-toggle aria-expanded="false" aria-controls="product-quote-form-wrap"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></button>
        <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline btn--lg"><?php echo esc_html(sprintf(__('Call %s', 'nw-fuel'), $business['phone'] ?? '')); ?></a>
      </div>

      <div class="product-quote" id="product-quote-form-wrap" <?php echo $quote_feedback ? '' : 'hidden'; ?>>
        <?php if ($quote_feedback === 'success') : ?>
        <div class="product-quote__success" data-quote-success>
          <h2 class="product-quote__success-title"><?php esc_html_e('Quote Request Sent', 'nw-fuel'); ?></h2>
          <p class="product-quote__success-text"><?php esc_html_e('Thank you. Our team will review the part, quantity, and notes, then respond by email or call you.', 'nw-fuel'); ?></p>
        </div>
        <?php elseif ($quote_feedback === 'error') : ?>
        <p><?php esc_html_e('Sorry, your quote request could not be sent. Please call the shop.', 'nw-fuel'); ?></p>
        <?php else : ?>
        <form class="product-quote__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" aria-label="<?php esc_attr_e('Product quote request', 'nw-fuel'); ?>">
          <?php wp_nonce_field('nw_fuel_quote', 'nw_fuel_quote_nonce'); ?>
          <input type="hidden" name="action" value="nw_fuel_quote">
          <input type="hidden" name="product" value="<?php echo esc_attr($product->get_name()); ?>">
          <input type="hidden" name="partNumber" value="<?php echo esc_attr($part); ?>">
          <input type="hidden" name="productId" value="<?php echo esc_attr((string) $product->get_id()); ?>">
          <?php if ($code) : ?><input type="hidden" name="productCode" value="<?php echo esc_attr($code); ?>"><?php endif; ?>
          <div class="product-quote__row">
            <div class="product-quote__group"><label for="quote-name"><?php esc_html_e('Full Name *', 'nw-fuel'); ?></label><input id="quote-name" name="name" type="text" required autocomplete="name"></div>
            <div class="product-quote__group"><label for="quote-email"><?php esc_html_e('Email Address *', 'nw-fuel'); ?></label><input id="quote-email" name="email" type="email" required autocomplete="email"></div>
          </div>
          <div class="product-quote__row">
            <div class="product-quote__group"><label for="quote-quantity"><?php esc_html_e('Quantity *', 'nw-fuel'); ?></label><input id="quote-quantity" name="quantity" type="number" min="1" step="1" value="1" required></div>
            <div class="product-quote__group"><label for="quote-phone"><?php esc_html_e('Phone', 'nw-fuel'); ?></label><input id="quote-phone" name="phone" type="tel" autocomplete="tel"></div>
          </div>
          <div class="product-quote__group"><label for="quote-note"><?php esc_html_e('Extra Note', 'nw-fuel'); ?></label><textarea id="quote-note" name="note" rows="4"></textarea></div>
          <button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e('Submit Quote Request', 'nw-fuel'); ?></button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($features) : ?>
      <div class="product-buy__features">
        <h2 class="product-buy__features-title"><?php esc_html_e('Key Features', 'nw-fuel'); ?></h2>
        <ul class="product-buy__list"><?php foreach ($features as $feature) : ?><li><?php echo esc_html((string) $feature); ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="product-detail-panel product-detail-panel--gray">
  <div class="container product-detail-panel__grid">
    <div class="product-detail-panel__block product-detail-panel__block--6">
      <h2 class="product-detail-panel__title"><?php esc_html_e('Specifications', 'nw-fuel'); ?></h2>
      <table class="product-spec">
        <?php foreach ($specs as $spec) :
            if (($spec['label'] ?? '') === 'Product Code') {
                continue;
            }
        ?><tr><th scope="row"><?php echo esc_html($spec['label'] ?? ''); ?></th><td><?php echo esc_html($spec['value'] ?? ''); ?></td></tr><?php endforeach; ?>
      </table>
    </div>
    <div class="product-detail-panel__block product-detail-panel__block--6">
      <h2 class="product-detail-panel__title"><?php esc_html_e('Compatible Vehicles', 'nw-fuel'); ?></h2>
      <ul class="product-buy__list product-buy__list--vehicles"><?php foreach ($vehicles as $vehicle) : ?><li><?php echo esc_html((string) $vehicle); ?></li><?php endforeach; ?></ul>
      <div class="product-tags">
        <?php foreach ($vehicle_terms as $vt) : ?><span class="product-tags__item"><?php echo esc_html($vt); ?></span><?php endforeach; ?>
        <?php foreach ($engine_terms as $et) : ?><span class="product-tags__item product-tags__item--dark"><?php echo esc_html($et); ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($faqs) : ?><section class="product-detail-panel"><div class="container product-detail-panel--narrow"><p class="product-detail-panel__eyebrow"><?php esc_html_e('FAQ', 'nw-fuel'); ?></p><h2 class="product-detail-panel__title product-detail-panel__title--lg"><?php esc_html_e('Product Questions', 'nw-fuel'); ?></h2><?php $id = 'product-faq'; get_template_part('template-parts/faq', null, compact('faqs', 'id')); ?></div></section><?php endif; ?>

<?php if ($related) : ?>
<section class="product-detail-panel product-detail-panel--gray">
  <div class="container">
    <div class="product-detail-related__head">
      <h2 class="product-detail-panel__title product-detail-panel__title--lg"><?php esc_html_e('Related Parts', 'nw-fuel'); ?></h2>
      <a href="<?php echo esc_url(add_query_arg('category', $category, nw_fuel_products_url())); ?>" class="product-detail-related__link"><?php echo esc_html(sprintf(__('More %s', 'nw-fuel'), $category)); ?></a>
    </div>
    <div class="catalog-grid catalog-grid--related">
      <?php foreach ($related as $related_product) :
          get_template_part('template-parts/catalog-product-card', null, ['product' => $related_product]);
      endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
    $title = __('Need help with this part?', 'nw-fuel');
    $description = __('Call our Surrey shop with your part number.', 'nw-fuel');
    get_template_part('template-parts/cta', null, compact('title', 'description'));
endwhile;

get_footer();
