<?php
/**
 * Breadcrumbs partial.
 *
 * @package NW_Fuel
 */

$items = $args['items'] ?? ($items ?? []);
if (empty($items)) {
    return;
}
?>
<nav class="breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'nw-fuel'); ?>">
  <ol class="breadcrumbs__list">
    <?php foreach ($items as $item) : ?>
    <li class="breadcrumbs__item">
      <?php if (! empty($item['href'])) : ?>
      <a href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['label']); ?></a>
      <?php else : ?>
      <span aria-current="page"><?php echo esc_html($item['label']); ?></span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
