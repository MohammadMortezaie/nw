<?php
/**
 * Single technical resource (Technical Page) — matched to static detail.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();

while (have_posts()) :
    the_post();
    $post_id  = get_the_ID();
    $images   = nw_fuel_images();
    $business = nw_fuel_business();
    $image    = get_the_post_thumbnail_url($post_id, 'large');
    if (! $image) {
        $image = (string) get_post_meta($post_id, '_nw_remote_image', true);
    }
    if (! $image) {
        $image = $images['hero'] ?? '';
    }
    $image_contain = (bool) get_post_meta($post_id, '_nw_image_contain', true);
    $sections      = nw_fuel_json_meta($post_id, '_nw_resource_sections');
    $app_table     = nw_fuel_json_meta($post_id, '_nw_application_table');
    $downloads     = nw_fuel_json_meta($post_id, '_nw_downloads');
    if ($app_table !== [] && array_is_list($app_table)) {
        $app_table = [];
    }
    $related = get_posts([
        'numberposts' => 3,
        'post_type'   => 'nw_tech_resource',
        'post__not_in'=> [$post_id],
        'orderby'     => 'menu_order',
        'order'       => 'ASC',
    ]);
    ?>
<section class="tech-detail-top">
  <div class="container">
    <?php
    $items = [
        ['label' => __('Technical Resources', 'nw-fuel'), 'href' => get_post_type_archive_link('nw_tech_resource')],
        ['label' => get_the_title()],
    ];
    get_template_part('template-parts/breadcrumbs', null, compact('items'));
    ?>
  </div>
</section>

<article class="tech-article">
  <div class="container tech-article__header">
    <span class="tech-article__tag"><?php esc_html_e('Technical Guide', 'nw-fuel'); ?></span>
    <h1 class="tech-article__title"><?php the_title(); ?></h1>
    <p class="tech-article__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
  </div>

  <?php if ($image) : ?>
  <div class="container tech-article__media<?php echo $image_contain ? ' tech-article__media--contain' : ''; ?>">
    <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>">
  </div>
  <?php endif; ?>

  <div class="container tech-article__body">
    <div class="tech-article__content">
      <?php the_content(); ?>
      <p>
        <?php esc_html_e('Need help with this application? Call', 'nw-fuel'); ?>
        <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>"><?php echo esc_html($business['phone'] ?? ''); ?></a>
        <?php esc_html_e('or', 'nw-fuel'); ?>
        <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>"><?php esc_html_e('request a quote', 'nw-fuel'); ?></a>
        <?php esc_html_e('and our shop will walk through fitment, testing, and parts with you.', 'nw-fuel'); ?>
      </p>
    </div>
    <div class="tech-article__actions">
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_tech_resource')); ?>" class="btn btn--outline"><?php esc_html_e('All Guides', 'nw-fuel'); ?></a>
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary"><?php esc_html_e('Contact the Shop', 'nw-fuel'); ?></a>
    </div>
  </div>

  <?php foreach ($sections as $i => $section) :
      if (! is_array($section)) {
          continue;
      }
      ?>
  <section class="tech-resource-panel<?php echo $i % 2 === 0 ? ' tech-resource-panel--gray' : ''; ?>">
    <div class="container">
      <div class="tech-resource-grid">
        <div class="tech-resource-grid__head">
          <?php if (! empty($section['eyebrow'])) : ?>
          <p class="tech-resource-panel__eyebrow"><?php echo esc_html((string) $section['eyebrow']); ?></p>
          <?php endif; ?>
          <h2 class="tech-resource-panel__title"><?php echo esc_html((string) ($section['title'] ?? '')); ?></h2>
          <?php if (! empty($section['image']['src'])) : ?>
          <figure class="tech-resource-media">
            <img src="<?php echo esc_url(nw_fuel_resolve_image_url((string) $section['image']['src'])); ?>" alt="<?php echo esc_attr((string) ($section['image']['alt'] ?? '')); ?>" loading="lazy">
          </figure>
          <?php endif; ?>
        </div>
        <div class="tech-resource-grid__body">
          <?php if (! empty($section['text'])) : ?>
          <p class="tech-resource-panel__text"><?php echo esc_html((string) $section['text']); ?></p>
          <?php endif; ?>
          <?php if (! empty($section['items']) && is_array($section['items'])) : ?>
          <ol class="tech-resource-list">
            <?php foreach ($section['items'] as $item) : ?>
            <li><?php echo esc_html((string) $item); ?></li>
            <?php endforeach; ?>
          </ol>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($app_table && ! empty($app_table['columns']) && ! empty($app_table['rows'])) : ?>
  <section class="tech-resource-panel">
    <div class="container">
      <p class="tech-resource-panel__eyebrow"><?php esc_html_e('Application Guide', 'nw-fuel'); ?></p>
      <h2 class="tech-resource-panel__title"><?php echo esc_html((string) ($app_table['title'] ?? '')); ?></h2>
      <?php if (! empty($app_table['text'])) : ?>
      <p class="tech-resource-panel__text tech-resource-panel__text--wide"><?php echo esc_html((string) $app_table['text']); ?></p>
      <?php endif; ?>
      <div class="tech-table-wrap">
        <table class="tech-table">
          <thead>
            <tr>
              <?php foreach ((array) $app_table['columns'] as $column) : ?>
              <th scope="col"><?php echo esc_html((string) $column); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ((array) $app_table['rows'] as $row) :
                if (! is_array($row)) {
                    continue;
                }
                ?>
            <tr>
              <?php foreach ((array) $app_table['columns'] as $column) : ?>
              <td><?php echo esc_html((string) ($row[(string) $column] ?? '')); ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($downloads) : ?>
  <section class="tech-resource-panel tech-resource-panel--gray">
    <div class="container">
      <p class="tech-resource-panel__eyebrow"><?php esc_html_e('Downloads', 'nw-fuel'); ?></p>
      <h2 class="tech-resource-panel__title"><?php esc_html_e('Downloads', 'nw-fuel'); ?></h2>
      <div class="tech-download-grid">
        <?php foreach ($downloads as $download) :
            if (! is_array($download)) {
                continue;
            }
            ?>
        <article class="tech-download">
          <?php if (! empty($download['image']['src'])) : ?>
          <img src="<?php echo esc_url(nw_fuel_resolve_image_url((string) $download['image']['src'])); ?>" alt="<?php echo esc_attr((string) ($download['image']['alt'] ?? '')); ?>" loading="lazy">
          <?php endif; ?>
          <div class="tech-download__body">
            <h3 class="tech-download__title"><?php echo esc_html((string) ($download['title'] ?? '')); ?></h3>
            <?php if (! empty($download['text'])) : ?>
            <p class="tech-download__text"><?php echo esc_html((string) $download['text']); ?></p>
            <?php endif; ?>
            <?php if (! empty($download['href'])) : ?>
            <a class="btn btn--primary" href="<?php echo esc_url((string) $download['href']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) ($download['label'] ?? __('Download', 'nw-fuel'))); ?></a>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</article>

<?php if ($related) : ?>
<section class="tech-related">
  <div class="container">
    <div class="tech-related__head">
      <h2 class="tech-panel__title tech-panel__title--lg"><?php esc_html_e('More Guides', 'nw-fuel'); ?></h2>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_tech_resource')); ?>" class="tech-related__link"><?php esc_html_e('All Resources', 'nw-fuel'); ?></a>
    </div>
    <div class="tech-grid tech-grid--related">
      <?php foreach ($related as $resource) {
          get_template_part('template-parts/technical-resource-card', null, ['resource' => $resource]);
      } ?>
    </div>
  </div>
</section>
<?php endif;

endwhile;

get_template_part('template-parts/cta', null, [
    'title'       => __('Questions About This Guide?', 'nw-fuel'),
    'description' => __('Our Surrey technicians handle injector testing, pump rebuilds, and parts quotes every day.', 'nw-fuel'),
]);
get_footer();
