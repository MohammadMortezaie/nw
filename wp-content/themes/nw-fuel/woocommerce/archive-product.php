<?php
/**
 * Product archive / shop template.
 *
 * @package NW_Fuel
 */

defined('ABSPATH') || exit;

get_header();

$images              = nw_fuel_images();
$business            = nw_fuel_business();
$product_categories  = nw_fuel_product_categories();
$brands              = nw_fuel_attribute_terms('pa_brand');
$vehicle_types       = nw_fuel_attribute_terms('pa_vehicle-type');
$engine_types        = nw_fuel_attribute_terms('pa_engine-type');
$initial_category    = nw_fuel_catalog_request_text('category');
$initial_search      = nw_fuel_catalog_request_text('q');
$initial_brand       = nw_fuel_catalog_request_text('brand');
$initial_vehicle     = nw_fuel_catalog_request_text('vehicle');
$initial_engine      = nw_fuel_catalog_request_text('engine');
$total_products      = nw_fuel_published_product_count();
$matched_products    = (int) $GLOBALS['wp_query']->found_posts;
$per_page            = (int) $GLOBALS['wp_query']->get('posts_per_page');
if ($per_page < 1) {
    $per_page = nw_fuel_catalog_per_page();
}
$current_page        = max(1, (int) $GLOBALS['wp_query']->get('paged'), (int) get_query_var('paged'));
$showing_from        = $matched_products === 0 ? 0 : (($current_page - 1) * $per_page) + 1;
$showing_to          = min($matched_products, $current_page * $per_page);
$showing_range       = $matched_products === 0 ? '0' : $showing_from . '–' . $showing_to;
$has_products        = woocommerce_product_loop();
$copy                = nw_fuel_archive_content('products');

$hero_quick_links = [
    [
        'label'  => __('Shop Category', 'nw-fuel'),
        'title'  => __('Pumps & Injectors', 'nw-fuel'),
        'cta'    => __('View Pumps & Injectors', 'nw-fuel'),
        'href'   => add_query_arg('category', 'Pumps & Injectors', nw_fuel_products_url()) . '#product-catalog',
        'image'  => $images['labEquipment']['lg'] ?? $images['warehouseParts']['lg'] ?? '',
        'icon'   => nw_fuel_product_category_icon_svg('Pumps & Injectors'),
        'active' => $initial_category === 'Pumps & Injectors',
        'category' => 'Pumps & Injectors',
    ],
    [
        'label'  => __('Shop Category', 'nw-fuel'),
        'title'  => __('Turbos', 'nw-fuel'),
        'cta'    => __('View Turbos', 'nw-fuel'),
        'href'   => add_query_arg('category', 'Turbos', nw_fuel_products_url()) . '#product-catalog',
        'image'  => $images['turboEngine']['lg'] ?? $images['engineParts']['lg'] ?? $images['warehouseParts']['lg'] ?? '',
        'icon'   => nw_fuel_product_category_icon_svg('Turbos'),
        'active' => $initial_category === 'Turbos',
        'category' => 'Turbos',
    ],
    [
        'label'  => __('Search Parts', 'nw-fuel'),
        'title'  => __('Engine Parts', 'nw-fuel'),
        'cta'    => __('Search Engine Parts', 'nw-fuel'),
        'href'   => add_query_arg('category', 'Engine Parts', nw_fuel_products_url()) . '#product-catalog',
        'image'  => $images['engineParts']['lg'] ?? $images['warehouseParts']['lg'] ?? '',
        'icon'   => nw_fuel_product_category_icon_svg('Engine Parts'),
        'active' => $initial_category === 'Engine Parts',
        'category' => 'Engine Parts',
    ],
];
?>
<section class="hero hero--products">
  <img class="hero__bg" src="<?php echo esc_url($images['warehouseParts']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
    <div class="product-hero-portals hero__fade">
      <?php foreach ($hero_quick_links as $link) : ?>
      <a href="<?php echo esc_url($link['href']); ?>" class="product-hero-portal<?php echo $link['active'] ? ' is-active' : ''; ?>" data-hero-portal-category="<?php echo esc_attr($link['category']); ?>"<?php echo $link['active'] ? ' aria-current="true"' : ''; ?>>
        <img class="product-hero-portal__bg" src="<?php echo esc_url($link['image']); ?>" alt="">
        <span class="product-hero-portal__overlay" aria-hidden="true"></span>
        <span class="product-hero-portal__content">
          <span class="product-hero-portal__icon" aria-hidden="true"><?php echo $link['icon']; ?></span>
          <span class="product-hero-portal__label"><?php echo esc_html($link['label']); ?></span>
          <span class="product-hero-portal__title"><?php echo esc_html($link['title']); ?></span>
          <span class="product-hero-portal__cta"><?php echo esc_html($link['cta']); ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php
    get_template_part('template-parts/hero-search-form', null, ['hero_search_value' => $initial_search]);
    ?>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></a>
      <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="btn btn--outline-white btn--lg"><?php echo esc_html($business['phone'] ?? ''); ?></a>
    </div>
  </div>
</section>

<section class="catalog-stats" aria-label="<?php esc_attr_e('Catalog overview', 'nw-fuel'); ?>">
  <div class="container catalog-stats__inner">
    <div class="catalog-stats__item"><span class="catalog-stats__value"><?php echo esc_html((string) $total_products); ?></span><span class="catalog-stats__label"><?php esc_html_e('Parts in catalog', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value"><?php echo esc_html((string) count($product_categories)); ?></span><span class="catalog-stats__label"><?php esc_html_e('Part categories', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value"><?php echo esc_html((string) count($brands)); ?></span><span class="catalog-stats__label"><?php esc_html_e('Brand lines', 'nw-fuel'); ?></span></div>
    <div class="catalog-stats__item"><span class="catalog-stats__value">1968</span><span class="catalog-stats__label"><?php esc_html_e('In Surrey since', 'nw-fuel'); ?></span></div>
  </div>
</section>

<section class="catalog-cats" aria-label="<?php esc_attr_e('Shop by category', 'nw-fuel'); ?>">
  <div class="container">
    <div class="catalog-cats__scroll">
      <a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="catalog-cats__pill<?php echo $initial_category === '' ? ' is-active' : ''; ?>" data-category-pill=""><?php esc_html_e('All Parts', 'nw-fuel'); ?></a>
      <?php foreach ($product_categories as $cat) : ?>
      <a href="<?php echo esc_url(add_query_arg('category', $cat, nw_fuel_products_url())); ?>" class="catalog-cats__pill<?php echo $initial_category === $cat ? ' is-active' : ''; ?>" data-category-pill="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($engine_types) : ?>
<section class="catalog-cats" aria-label="<?php esc_attr_e('Shop by engine type', 'nw-fuel'); ?>">
  <div class="container">
    <div class="catalog-cats__scroll">
      <a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="catalog-cats__pill<?php echo $initial_engine === '' ? ' is-active' : ''; ?>" data-engine-pill=""><?php esc_html_e('All Engine Types', 'nw-fuel'); ?></a>
      <?php foreach ($engine_types as $et) : ?>
      <a href="<?php echo esc_url(add_query_arg('engine', $et, nw_fuel_products_url())); ?>" class="catalog-cats__pill<?php echo $initial_engine === $et ? ' is-active' : ''; ?>" data-engine-pill="<?php echo esc_attr($et); ?>"><?php echo esc_html($et); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="catalog-section">
  <div class="catalog__backdrop" id="catalog-backdrop" aria-hidden="true"></div>
  <div class="container catalog" id="product-catalog" data-catalog-url="<?php echo esc_url(nw_fuel_products_url()); ?>">
    <aside class="catalog__sidebar" id="catalog-sidebar">
      <div class="catalog__sidebar-header">
        <h2><?php esc_html_e('Filter Results', 'nw-fuel'); ?></h2>
        <button type="button" class="catalog__close" id="catalog-close" aria-label="<?php esc_attr_e('Close filters', 'nw-fuel'); ?>">×</button>
      </div>
      <form class="catalog__filters" id="catalog-filters" method="get" action="<?php echo esc_url(nw_fuel_products_url()); ?>">
      <div class="filter-group"><label for="filter-search"><?php esc_html_e('Search', 'nw-fuel'); ?></label><input type="search" id="filter-search" name="q" placeholder="<?php esc_attr_e('Part number or name', 'nw-fuel'); ?>" value="<?php echo esc_attr($initial_search); ?>"></div>
      <div class="filter-group">
        <label for="filter-category"><?php esc_html_e('Category', 'nw-fuel'); ?></label>
        <select id="filter-category" name="category">
          <option value=""><?php esc_html_e('All Categories', 'nw-fuel'); ?></option>
          <?php foreach ($product_categories as $cat) : ?>
          <option value="<?php echo esc_attr($cat); ?>"<?php selected($initial_category, $cat); ?>><?php echo esc_html($cat); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label for="filter-brand"><?php esc_html_e('Brand', 'nw-fuel'); ?></label>
        <select id="filter-brand" name="brand">
          <option value=""><?php esc_html_e('All Brands', 'nw-fuel'); ?></option>
          <?php foreach ($brands as $brand) : ?><option value="<?php echo esc_attr($brand); ?>"<?php selected($initial_brand, $brand); ?>><?php echo esc_html($brand); ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label for="filter-vehicle"><?php esc_html_e('Vehicle Type', 'nw-fuel'); ?></label>
        <select id="filter-vehicle" name="vehicle"><option value=""><?php esc_html_e('All Vehicle Types', 'nw-fuel'); ?></option><?php foreach ($vehicle_types as $vt) : ?><option value="<?php echo esc_attr($vt); ?>"<?php selected($initial_vehicle, $vt); ?>><?php echo esc_html($vt); ?></option><?php endforeach; ?></select>
      </div>
      <div class="filter-group">
        <label for="filter-engine"><?php esc_html_e('Engine Type', 'nw-fuel'); ?></label>
        <select id="filter-engine" name="engine"><option value=""><?php esc_html_e('All Engine Types', 'nw-fuel'); ?></option><?php foreach ($engine_types as $et) : ?><option value="<?php echo esc_attr($et); ?>"<?php selected($initial_engine, $et); ?>><?php echo esc_html($et); ?></option><?php endforeach; ?></select>
      </div>
      <a class="filter-clear" id="filter-clear" href="<?php echo esc_url(nw_fuel_products_url()); ?>"><?php esc_html_e('Clear filters', 'nw-fuel'); ?></a>
      </form>
    </aside>

    <div>
      <div class="catalog__toolbar">
        <p><?php esc_html_e('Showing', 'nw-fuel'); ?> <strong id="product-count"><?php echo esc_html($showing_range); ?></strong> <?php esc_html_e('of', 'nw-fuel'); ?> <strong><?php echo esc_html((string) $matched_products); ?></strong> <?php esc_html_e('parts', 'nw-fuel'); ?></p>
        <button type="button" class="filter-toggle" id="filter-toggle" aria-expanded="false" aria-controls="catalog-sidebar"><?php esc_html_e('Filters', 'nw-fuel'); ?></button>
      </div>
      <div class="catalog-grid" id="catalog-grid"<?php echo $has_products ? '' : ' hidden'; ?>>
        <?php
        $nw_shown_product_ids = [];
        if ($has_products) {
            while (have_posts()) {
                the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $nw_shown_product_ids[] = $product->get_id();
                    get_template_part('template-parts/catalog-product-card', null, ['product' => $product]);
                }
            }
        }
        if ($initial_search !== '' && function_exists('nw_fuel_track_product_search')) {
            nw_fuel_track_product_search($initial_search, $nw_shown_product_ids);
            echo '<span hidden data-nw-search="' . esc_attr($initial_search) . '" data-nw-search-ids="' . esc_attr(implode(',', array_map('intval', $nw_shown_product_ids))) . '"></span>';
        }
        ?>
      </div>
      <?php
      if ($has_products && function_exists('nw_fuel_render_catalog_pagination')) {
          nw_fuel_render_catalog_pagination();
      }
      ?>
      <div class="catalog__empty" id="catalog-empty"<?php echo $has_products ? ' hidden' : ''; ?>>
        <h3><?php esc_html_e('No parts matched your search', 'nw-fuel'); ?></h3>
        <p><?php esc_html_e('Clear the filters or call us with your part number.', 'nw-fuel'); ?></p>
        <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary catalog__empty-btn"><?php esc_html_e('Request a Quote', 'nw-fuel'); ?></a>
      </div>
    </div>
  </div>
</section>

<?php
$title = __('Part not listed?', 'nw-fuel');
$description = __('Call with your part number. Our staff will search the full catalog and surplus inventory for you.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
