<?php
/**
 * FAQ accordion partial (matches static includes/faq.php).
 *
 * @package NW_Fuel
 */

$faqs = $args['faqs'] ?? ($faqs ?? []);
$id = $args['id'] ?? ($id ?? 'faq');
if (empty($faqs) || ! is_array($faqs)) {
    return;
}
?>
<div class="faq" id="<?php echo esc_attr($id); ?>">
  <?php foreach ($faqs as $index => $faq) :
      if (! is_array($faq)) {
          continue;
      }
      $is_open = $index === 0;
      ?>
  <div class="faq__item<?php echo $is_open ? ' is-open' : ''; ?>">
    <button type="button" class="faq__question" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($id . '-answer-' . $index); ?>">
      <?php echo esc_html((string) ($faq['question'] ?? '')); ?>
      <span class="faq__chevron" aria-hidden="true"></span>
    </button>
    <div class="faq__answer" id="<?php echo esc_attr($id . '-answer-' . $index); ?>"<?php echo $is_open ? '' : ' hidden'; ?>>
      <p><?php echo esc_html((string) ($faq['answer'] ?? '')); ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>
