<?php
/**
 * Catalog PDF detail — preview, details, email gate, download.
 *
 * @package NW_Fuel
 */

get_header();

$item = get_queried_object();
if (! $item instanceof WP_Post) {
    get_footer();
    return;
}

$id         = (int) $item->ID;
$title      = get_the_title($item);
$summary    = nw_fuel_catalog_display_summary($id);
$cover      = nw_fuel_catalog_cover_url($id);
$is_doc     = nw_fuel_catalog_cover_is_document($id);
$size       = nw_fuel_catalog_file_size_label($id);
$updated    = nw_fuel_catalog_updated_label($id);
$filename   = nw_fuel_catalog_file_name($id);
$highlights = nw_fuel_catalog_highlights();
$related    = nw_fuel_catalog_related_items($id);
$unlocked   = nw_fuel_catalog_is_unlocked($id);
$status     = sanitize_key((string) ($_GET['catalog'] ?? ''));
?>
<section class="catalog-detail-top">
  <div class="container">
    <?php
    $items = [
        ['label' => __('Catalog', 'nw-fuel'), 'href' => nw_fuel_catalog_url()],
        ['label' => $title],
    ];
    get_template_part('template-parts/breadcrumbs', null, compact('items'));
    ?>
  </div>
</section>

<section class="catalog-detail">
  <div class="container catalog-detail__layout">
    <div class="catalog-detail__preview">
      <div class="catalog-preview<?php echo $is_doc ? ' catalog-preview--document' : ''; ?>">
        <?php if ($cover !== '') : ?>
        <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>">
        <?php endif; ?>
        <span class="catalog-preview__tag"><?php esc_html_e('PDF', 'nw-fuel'); ?></span>
      </div>
      <dl class="catalog-specs">
        <div>
          <dt><?php esc_html_e('Format', 'nw-fuel'); ?></dt>
          <dd><?php esc_html_e('PDF download', 'nw-fuel'); ?></dd>
        </div>
        <?php if ($size !== '') : ?>
        <div>
          <dt><?php esc_html_e('File size', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($size); ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($updated !== '') : ?>
        <div>
          <dt><?php esc_html_e('Updated', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($updated); ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($filename !== '') : ?>
        <div>
          <dt><?php esc_html_e('File', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($filename); ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </div>

    <div class="catalog-detail__panel">
      <p class="catalog-detail__tag"><?php esc_html_e('Catalog PDF', 'nw-fuel'); ?></p>
      <h1 class="catalog-detail__title"><?php echo esc_html($title); ?></h1>
      <p class="catalog-detail__desc"><?php echo esc_html($summary); ?></p>

      <h2 class="catalog-detail__sub"><?php esc_html_e('What’s inside', 'nw-fuel'); ?></h2>
      <ul class="catalog-highlights">
        <?php foreach ($highlights as $line) : ?>
        <li><?php echo esc_html($line); ?></li>
        <?php endforeach; ?>
      </ul>

      <?php if ($status === 'error') : ?>
      <p class="catalog-detail__notice catalog-detail__notice--error"><?php esc_html_e('Enter a valid email to download this PDF.', 'nw-fuel'); ?></p>
      <?php elseif ($status === 'locked') : ?>
      <p class="catalog-detail__notice catalog-detail__notice--error"><?php esc_html_e('Enter your email to unlock the download.', 'nw-fuel'); ?></p>
      <?php elseif ($status === 'missing') : ?>
      <p class="catalog-detail__notice catalog-detail__notice--error"><?php esc_html_e('This file is not available yet.', 'nw-fuel'); ?></p>
      <?php elseif ($status === 'ok' || $unlocked) : ?>
      <p class="catalog-detail__notice"><?php esc_html_e('Thanks. Your download is ready.', 'nw-fuel'); ?></p>
      <?php endif; ?>

      <?php if ($unlocked) : ?>
      <a class="btn btn--primary btn--lg" href="<?php echo esc_url(nw_fuel_catalog_download_url($id)); ?>"><?php esc_html_e('Download PDF', 'nw-fuel'); ?></a>
      <?php else : ?>
      <form class="catalog-unlock" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('nw_fuel_catalog_email', 'nw_fuel_catalog_email_nonce'); ?>
        <input type="hidden" name="action" value="nw_fuel_catalog_email">
        <input type="hidden" name="catalog_id" value="<?php echo esc_attr((string) $id); ?>">
        <label class="catalog-unlock__hp" for="catalog-website"><?php esc_html_e('Website', 'nw-fuel'); ?></label>
        <input class="catalog-unlock__hp" type="text" id="catalog-website" name="website" tabindex="-1" autocomplete="off">
        <label for="catalog-email"><?php esc_html_e('Work email', 'nw-fuel'); ?></label>
        <input type="email" id="catalog-email" name="email" required placeholder="<?php esc_attr_e('you@shop.com', 'nw-fuel'); ?>">
        <button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e('Unlock download', 'nw-fuel'); ?></button>
        <p class="catalog-unlock__note"><?php esc_html_e('Used only to send this file. No mailing list signup.', 'nw-fuel'); ?></p>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related !== []) : ?>
<section class="catalog-related">
  <div class="container">
    <div class="catalog-list__head">
      <h2><?php esc_html_e('More catalogs', 'nw-fuel'); ?></h2>
      <p><?php esc_html_e('Other shop PDFs you can unlock the same way.', 'nw-fuel'); ?></p>
    </div>
    <div class="catalog-grid">
      <?php foreach ($related as $related_item) {
          get_template_part('template-parts/catalog-pdf-card', null, ['item' => $related_item]);
      } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
$cta_title       = __('Need help finding a part?', 'nw-fuel');
$cta_description = __('Call our Surrey shop and we will match the catalog line to stock, testing, or a quote.', 'nw-fuel');
get_template_part('template-parts/cta', null, [
    'title'       => $cta_title,
    'description' => $cta_description,
]);
get_footer();
