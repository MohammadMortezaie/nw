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
    if ($read_time === '') {
        $word_count = str_word_count(wp_strip_all_tags((string) get_the_content()));
        $read_time  = sprintf(_n('%d min read', '%d min read', max(1, (int) ceil($word_count / 220)), 'nw-fuel'), max(1, (int) ceil($word_count / 220)));
    }
    $author_name  = get_the_author_meta('display_name') ?: __('NW Fuel Team', 'nw-fuel');
    if (in_array(strtolower(trim((string) $author_name)), ['admin', 'administrator'], true)) {
        $author_name = __('NW Fuel Team', 'nw-fuel');
    }
    $hero_label = trim((string) $category);
    if ($hero_label === '' || strtolower($hero_label) === 'blog') {
        $hero_label = __('Diesel Insights', 'nw-fuel');
    }
    $author_words = preg_split('/\s+/', trim((string) $author_name)) ?: [];
    $author_initials = '';
    foreach (array_slice($author_words, 0, 2) as $author_word) {
        $author_initials .= function_exists('mb_substr') ? mb_substr($author_word, 0, 1) : substr($author_word, 0, 1);
    }
    $author_initials = strtoupper($author_initials ?: 'NW');
    $related = get_posts(['numberposts' => 3, 'post__not_in' => [get_the_ID()], 'post_type' => 'post']);
    ?>
<section class="blog-detail-top"><div class="container"><?php $items = [['label' => __('Blog', 'nw-fuel'), 'href' => get_permalink(get_option('page_for_posts'))], ['label' => get_the_title()]]; get_template_part('template-parts/breadcrumbs', null, compact('items')); ?></div></section>

<article class="blog-article">
  <header class="blog-article__hero">
    <div class="container blog-article__hero-grid">
      <div class="blog-article__header">
        <p class="blog-article__eyebrow"><?php echo esc_html($hero_label); ?></p>
        <h1 class="blog-article__title"><?php the_title(); ?></h1>
        <?php if (get_the_excerpt() !== '') : ?><p class="blog-article__lead"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>

        <div class="blog-article__byline">
          <span class="blog-article__avatar" aria-hidden="true"><?php echo esc_html($author_initials); ?></span>
          <span class="blog-article__author">
            <strong><?php echo esc_html($author_name); ?></strong>
            <small><?php esc_html_e('NW Fuel technical team', 'nw-fuel'); ?></small>
          </span>
          <span class="blog-article__meta-item">
            <small><?php esc_html_e('Published', 'nw-fuel'); ?></small>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
          </span>
          <span class="blog-article__meta-item">
            <small><?php esc_html_e('Reading time', 'nw-fuel'); ?></small>
            <span><?php echo esc_html($read_time); ?></span>
          </span>
        </div>
      </div>

      <?php if ($image) : ?>
      <div class="blog-article__media">
        <img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>">
        <span class="blog-article__media-label"><?php esc_html_e('Diesel knowledge, built in the shop.', 'nw-fuel'); ?></span>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <div class="container blog-article__layout">
    <div class="blog-article__body">
      <div class="blog-article__content"><?php the_content(); ?></div>
      <div class="blog-article__actions">
        <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>" class="btn btn--outline"><?php esc_html_e('Back to Blog', 'nw-fuel'); ?></a>
        <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="btn btn--primary"><?php esc_html_e('Contact the Shop', 'nw-fuel'); ?></a>
      </div>
    </div>

    <aside class="blog-article__sidebar" aria-label="<?php esc_attr_e('Contact NW Fuel', 'nw-fuel'); ?>">
      <div class="blog-article__sidebar-sticky">
        <div class="blog-support-card">
          <p class="blog-support-card__eyebrow"><?php esc_html_e('Talk to a diesel specialist', 'nw-fuel'); ?></p>
          <h2><?php esc_html_e('Need a second opinion?', 'nw-fuel'); ?></h2>
          <p><?php esc_html_e('Our Surrey team can help with injector testing, pump rebuilding, fitment questions, and parts availability.', 'nw-fuel'); ?></p>
          <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="blog-support-card__call">
            <span aria-hidden="true">↗</span>
            <?php echo esc_html(sprintf(__('Call %s', 'nw-fuel'), $business['phone'] ?? '')); ?>
          </a>
          <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="blog-support-card__link"><?php esc_html_e('Send us a message', 'nw-fuel'); ?> →</a>
        </div>
        <div class="blog-shop-note">
          <span><?php esc_html_e('NW Fuel Injection', 'nw-fuel'); ?></span>
          <strong><?php esc_html_e('Diesel done right since 1968.', 'nw-fuel'); ?></strong>
        </div>
      </div>
    </aside>
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
