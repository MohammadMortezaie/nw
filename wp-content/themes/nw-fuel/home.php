<?php
/**
 * Blog posts archive (Posts page).
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$business = nw_fuel_business();
$posts_query = new WP_Query(['post_type' => 'post', 'posts_per_page' => -1, 'post_status' => 'publish']);
$blog_categories = [];
foreach ($posts_query->posts as $post_item) {
    $cats = get_the_category($post_item->ID);
    if ($cats) {
        $blog_categories[$cats[0]->name] = true;
    }
}
$copy = nw_fuel_archive_content('blog');
?>
<section class="hero hero--blog">
  <img class="hero__bg" src="<?php echo esc_url($images['heuiTesting']['lg'] ?? $images['hero'] ?? ''); ?>" alt="">
  <div class="hero__overlay"></div>
  <div class="container hero__content">
    <p class="hero__eyebrow hero__fade"><?php echo esc_html($copy['eyebrow'] ?? ''); ?></p>
    <h1 class="hero__title hero__fade"><?php echo esc_html($copy['title'] ?? ''); ?></h1>
    <p class="hero__desc hero__fade"><?php echo esc_html($copy['desc'] ?? ''); ?></p>
    <div class="hero__actions hero__fade">
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary btn--lg"><?php echo esc_html($copy['cta'] ?? ''); ?></a>
      <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="btn btn--outline-white btn--lg"><?php esc_html_e('Our Services', 'nw-fuel'); ?></a>
    </div>
  </div>
</section>

<section class="blog-stats" aria-label="<?php esc_attr_e('Blog overview', 'nw-fuel'); ?>">
  <div class="container blog-stats__inner">
    <div class="blog-stats__item"><span class="blog-stats__value"><?php echo esc_html((string) $posts_query->post_count); ?></span><span class="blog-stats__label"><?php esc_html_e('Articles', 'nw-fuel'); ?></span></div>
    <div class="blog-stats__item"><span class="blog-stats__value"><?php echo esc_html((string) count($blog_categories)); ?></span><span class="blog-stats__label"><?php esc_html_e('Topics', 'nw-fuel'); ?></span></div>
    <div class="blog-stats__item"><span class="blog-stats__value">Surrey</span><span class="blog-stats__label"><?php esc_html_e('BC shop notes', 'nw-fuel'); ?></span></div>
    <div class="blog-stats__item"><span class="blog-stats__value">1968</span><span class="blog-stats__label"><?php esc_html_e('Since', 'nw-fuel'); ?></span></div>
  </div>
</section>

<section class="blog-catalog">
  <div class="container">
    <div class="blog-catalog__head">
      <h2 class="blog-catalog__title"><?php esc_html_e('Latest Articles', 'nw-fuel'); ?></h2>
      <p class="blog-catalog__desc"><?php esc_html_e('Injector diagnostics, emissions systems, shop equipment, and diesel industry notes from the NW Fuel team.', 'nw-fuel'); ?></p>
    </div>
    <div class="blog-grid">
      <?php while ($posts_query->have_posts()) : $posts_query->the_post();
          get_template_part('template-parts/blog-card', null, ['post' => get_post()]);
      endwhile;
      wp_reset_postdata(); ?>
    </div>
  </div>
</section>

<?php
$title = __('Need Help With a Diesel Injection Issue?', 'nw-fuel');
$description = __('Call our Surrey shop or send a message.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
