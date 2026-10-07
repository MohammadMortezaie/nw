<?php
/**
 * Generic page template.
 *
 * @package NW_Fuel
 */

get_header();

while (have_posts()) :
    the_post();
    ?>
    <section class="home-section">
      <div class="container">
        <h1><?php the_title(); ?></h1>
        <div><?php the_content(); ?></div>
      </div>
    </section>
    <?php
endwhile;

get_footer();
