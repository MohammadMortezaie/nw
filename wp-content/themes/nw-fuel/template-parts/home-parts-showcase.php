<?php
/**
 * Homepage parts showcase — markup matched to static includes/home-parts-showcase.php
 *
 * @package NW_Fuel
 *
 * @var array $args {
 *   @type string       $showcase_heading_id
 *   @type string       $showcase_title
 *   @type string       $showcase_text
 *   @type string       $showcase_cta_href
 *   @type string       $showcase_cta_label
 *   @type WC_Product[] $showcase_products
 *   @type bool         $showcase_gray
 * }
 */

declare(strict_types=1);

$showcase_heading_id = $args['showcase_heading_id'] ?? '';
$showcase_title      = $args['showcase_title'] ?? '';
$showcase_text       = $args['showcase_text'] ?? '';
$showcase_cta_href   = $args['showcase_cta_href'] ?? '';
$showcase_cta_label  = $args['showcase_cta_label'] ?? '';
$showcase_products   = $args['showcase_products'] ?? [];
$showcase_gray       = ! empty($args['showcase_gray']);
?>
<section class="home-section<?php echo $showcase_gray ? ' home-section--gray' : ''; ?>" aria-labelledby="<?php echo esc_attr($showcase_heading_id); ?>">
  <div class="container">
    <div class="home-section__head">
      <h2 id="<?php echo esc_attr($showcase_heading_id); ?>" class="home-section__title"><?php echo esc_html($showcase_title); ?></h2>
      <a href="<?php echo esc_url($showcase_cta_href); ?>" class="home-section__link"><?php echo esc_html($showcase_cta_label); ?></a>
    </div>
    <?php if ($showcase_text !== '') : ?>
    <p class="home-section__intro"><?php echo esc_html($showcase_text); ?></p>
    <?php endif; ?>
    <div class="product-grid--home product-grid--six">
      <?php foreach ($showcase_products as $product) :
          if (! $product instanceof WC_Product) {
              continue;
          }
          get_template_part('template-parts/home-product-card', null, ['product' => $product]);
      endforeach; ?>
    </div>
  </div>
</section>
