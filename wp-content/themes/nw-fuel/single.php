<?php
/**
 * Single blog post template.
 *
 * @package NW_Fuel
 */

get_header();

$images = nw_fuel_images();
$business = nw_fuel_business();

while (have_posts()) :
    the_post();
    $image = get_the_post_thumbnail_url(get_the_ID(), 'large');
    if (! $image) {
        $image = (string) get_post_meta(get_the_ID(), '_nw_remote_image', true);
    }
    if (! $image) {
        $image = $images['hero'] ?? '';
    }
    $categories = get_the_category();
    $category = $categories[0]->name ?? '';
    $read_time = (string) get_post_meta(get_the_ID(), '_nw_read_time', true);
    $related = get_posts(['numberposts' => 3, 'post__not_in' => [get_the_ID()], 'post_type' => 'post']);
    ?>
<section class="blog-detail-top"><div class="container"><?php $items = [['label' => __('Blog', 'nw-fuel'), 'href' => get_permalink(get_option('page_for_posts'))], ['label' => get_the_title()]]; get_template_part('template-parts/breadcrumbs', null, compact('items')); ?></div></section>

<article class="blog-article">
  <div class="container blog-article__header">
    <div class="blog-article__meta">
      <?php if ($category) : ?><span class="blog-article__tag"><?php echo esc_html($category); ?></span><?php endif; ?>
      <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
      <?php if ($read_time) : ?><span><?php echo esc_html($read_time); ?></span><?php endif; ?>
    </div>
    <h1 class="blog-article__title"><?php the_title(); ?></h1>
    <p class="blog-article__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
  </div>
  <?php if ($image) : ?><div class="container blog-article__media"><img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>"></div><?php endif; ?>
  <div class="container blog-article__body">
    <div class="blog-article__content"><?php the_content(); ?></div>
    <div class="blog-article__actions">
      <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="btn btn--outline"><?php esc_html_e('Back to Blog', 'nw-fuel'); ?></a>
      <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary"><?php esc_html_e('Contact the Shop', 'nw-fuel'); ?></a>
    </div>
  </div>
</article>

<?php if ($related) : ?>
<section class="blog-related"><div class="container"><div class="blog-related__head"><h2 class="blog-panel__title blog-panel__title--lg"><?php esc_html_e('More Articles', 'nw-fuel'); ?></h2><a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="blog-related__link"><?php esc_html_e('All Articles', 'nw-fuel'); ?></a></div><div class="blog-grid blog-grid--related"><?php foreach ($related as $post) { get_template_part('template-parts/blog-card', null, ['post' => $post]); } ?></div></div></section>
<?php endif;
endwhile;

$title = __('Questions About This Topic?', 'nw-fuel');
$description = __('Our Surrey technicians handle injector testing, pump rebuilds, and parts quotes every day.', 'nw-fuel');
get_template_part('template-parts/cta', null, compact('title', 'description'));
get_footer();
