<?php
/**
 * Fallback archive template.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

get_header();
?>
<section class="page-hero page-hero--short">
  <div class="container page-hero__content">
    <h1>
      <?php
      if (is_category() || is_tag() || is_tax()) {
          single_term_title();
      } elseif (is_post_type_archive()) {
          post_type_archive_title();
      } else {
          the_archive_title();
      }
      ?>
    </h1>
    <?php the_archive_description('<p>', '</p>'); ?>
  </div>
</section>

<section class="page-section">
  <div class="container">
    <?php if (have_posts()) : ?>
      <div class="archive-list">
        <?php
        while (have_posts()) :
            the_post();
            ?>
        <article <?php post_class('archive-item'); ?>>
          <h2 class="archive-item__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <?php if (has_excerpt()) : ?>
          <p class="archive-item__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
          <?php endif; ?>
        </article>
            <?php
        endwhile;
        ?>
      </div>
      <?php the_posts_pagination(); ?>
    <?php else : ?>
      <p><?php esc_html_e('No items found.', 'nw-fuel'); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php
get_footer();
