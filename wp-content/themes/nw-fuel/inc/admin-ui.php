<?php
/**
 * Admin UI helpers — repeater CRUD fields for services.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'nw_fuel_enqueue_admin_assets');

/**
 * Enqueue admin meta UI assets on service / tech / product edit screens.
 */
function nw_fuel_enqueue_admin_assets(string $hook): void
{
    if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (! $screen || ! in_array($screen->post_type, ['nw_service', 'nw_tech_resource', 'nw_gallery_item', 'nw_catalog_item', 'product', 'page'], true)) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style(
        'nw-fuel-admin-meta',
        NW_FUEL_URI . '/assets/css/admin-meta.css',
        [],
        NW_FUEL_VERSION
    );
    wp_enqueue_script(
        'nw-fuel-admin-meta',
        NW_FUEL_URI . '/assets/js/admin-meta.js',
        ['jquery'],
        NW_FUEL_VERSION,
        true
    );
}

/**
 * Escape attribute for use inside HTML templates.
 */
function nw_fuel_admin_attr(?string $value): string
{
    return esc_attr((string) $value);
}

/**
 * Render a labeled text/textarea field.
 *
 * @param array<string, mixed> $args
 */
function nw_fuel_admin_field(array $args): void
{
    $type        = (string) ($args['type'] ?? 'text');
    $name        = (string) ($args['name'] ?? '');
    $label       = (string) ($args['label'] ?? '');
    $value       = (string) ($args['value'] ?? '');
    $placeholder = (string) ($args['placeholder'] ?? '');
    $rows        = (int) ($args['rows'] ?? 3);
    $help        = (string) ($args['help'] ?? '');
    $class       = (string) ($args['class'] ?? 'widefat');
    $api_managed = ! empty($args['api_managed']);
    ?>
    <div class="nw-admin-field">
      <?php if ($label !== '') : ?>
      <label>
        <?php echo esc_html($label); ?>
        <?php if ($api_managed) : ?>
          <?php nw_fuel_admin_api_badge(); ?>
        <?php endif; ?>
      </label>
      <?php endif; ?>
      <?php if ($type === 'textarea') : ?>
      <textarea class="<?php echo esc_attr($class); ?>" name="<?php echo esc_attr($name); ?>" rows="<?php echo esc_attr((string) $rows); ?>" placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($value); ?></textarea>
      <?php else : ?>
      <input class="<?php echo esc_attr($class); ?>" type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
      <?php endif; ?>
      <?php if ($help !== '') : ?>
      <p class="description"><?php echo esc_html($help); ?></p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * Mark a product field whose value is replaced by the nightly inventory API.
 */
function nw_fuel_admin_api_badge(): void
{
    ?>
    <span class="nw-api-managed-badge"><?php esc_html_e('Updated every day by API', 'nw-fuel'); ?></span>
    <?php
}

/**
 * Render image URL field with media library picker.
 *
 * @param array<string, mixed> $args
 */
function nw_fuel_admin_media_field(array $args): void
{
    $name  = (string) ($args['name'] ?? '');
    $label = (string) ($args['label'] ?? __('Image URL', 'nw-fuel'));
    $value = (string) ($args['value'] ?? '');
    $help  = (string) ($args['help'] ?? '');
    ?>
    <div class="nw-admin-field nw-media-field">
      <label><?php echo esc_html($label); ?></label>
      <?php if ($value !== '') : ?>
      <img class="nw-media-field__preview is-visible" src="<?php echo esc_url($value); ?>" alt="">
      <?php else : ?>
      <img class="nw-media-field__preview" src="" alt="">
      <?php endif; ?>
      <div class="nw-media-field__controls">
        <input class="widefat" type="url" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_url($value); ?>" placeholder="https://">
        <button type="button" class="button" data-nw-media data-title="<?php esc_attr_e('Select image', 'nw-fuel'); ?>"><?php esc_html_e('Select', 'nw-fuel'); ?></button>
        <button type="button" class="button" data-nw-media-clear><?php esc_html_e('Clear', 'nw-fuel'); ?></button>
      </div>
      <?php if ($help !== '') : ?>
      <p class="description"><?php echo esc_html($help); ?></p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * Row action buttons for repeaters.
 */
function nw_fuel_admin_row_actions(string $label): void
{
    ?>
    <div class="nw-repeater__row-head">
      <p class="nw-repeater__row-title" data-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></p>
      <div class="nw-repeater__actions">
        <button type="button" class="button-link" data-nw-repeater-move="up"><?php esc_html_e('Up', 'nw-fuel'); ?></button>
        <button type="button" class="button-link" data-nw-repeater-move="down"><?php esc_html_e('Down', 'nw-fuel'); ?></button>
        <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
      </div>
    </div>
    <?php
}

/**
 * Render service CRUD meta box (replaces JSON textareas).
 */
function nw_fuel_render_service_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_service_meta', 'nw_fuel_service_meta_nonce');

    $icon        = (string) get_post_meta($post->ID, '_nw_icon', true);
    $hero        = (string) get_post_meta($post->ID, '_nw_hero_image', true);
    $meta_title  = (string) get_post_meta($post->ID, '_nw_meta_title', true);
    $meta_desc   = (string) get_post_meta($post->ID, '_nw_meta_description', true);
    $benefits    = array_values(array_map('strval', nw_fuel_json_meta($post->ID, '_nw_benefits')));
    $process     = nw_fuel_json_meta($post->ID, '_nw_process');
    $pricing     = nw_fuel_json_meta($post->ID, '_nw_pricing');
    $faqs        = nw_fuel_json_meta($post->ID, '_nw_service_faqs');
    $sections    = nw_fuel_json_meta($post->ID, '_nw_detail_sections');
    $cards       = nw_fuel_json_meta($post->ID, '_nw_feature_cards');
    $video       = nw_fuel_json_meta($post->ID, '_nw_video_section');
    $related     = array_values(array_map('strval', nw_fuel_json_meta($post->ID, '_nw_related_slugs')));

    if ($video !== [] && array_is_list($video)) {
        $video = [];
    }

    $other_services = get_posts([
        'post_type'      => 'nw_service',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'post__not_in'   => [$post->ID],
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ]);
    ?>
    <div class="nw-admin-wrap">
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Page copy (required)', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('These replace the old WordPress content editor. They appear on the public service detail page.', 'nw-fuel'); ?></p>
        <?php
        nw_fuel_admin_field([
            'type'        => 'textarea',
            'label'       => __('Short Description (lead / excerpt)', 'nw-fuel'),
            'name'        => 'nw_service_short_description',
            'value'       => (string) $post->post_excerpt,
            'rows'        => 3,
            'help'        => __('Shown under the H1 on the service detail page.', 'nw-fuel'),
            'placeholder' => __('One or two sentences summarizing the service…', 'nw-fuel'),
        ]);
        nw_fuel_admin_field([
            'type'        => 'textarea',
            'label'       => __('Service Overview (main description)', 'nw-fuel'),
            'name'        => 'nw_service_overview',
            'value'       => wp_strip_all_tags((string) $post->post_content),
            'rows'        => 6,
            'help'        => __('Shown in the “Service Overview” section.', 'nw-fuel'),
            'placeholder' => __('Full overview paragraph(s)…', 'nw-fuel'),
        ]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Basics & SEO', 'nw-fuel'); ?></h3>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field([
              'label' => __('Icon Key', 'nw-fuel'),
              'name'  => '_nw_icon',
              'value' => $icon,
              'help'  => __('Optional icon identifier from the design system (e.g. Gauge).', 'nw-fuel'),
          ]);
          nw_fuel_admin_field([
              'label' => __('SEO Title', 'nw-fuel'),
              'name'  => '_nw_meta_title',
              'value' => $meta_title,
          ]);
          ?>
        </div>
        <?php
        nw_fuel_admin_media_field([
            'label' => __('Hero Image', 'nw-fuel'),
            'name'  => '_nw_hero_image',
            'value' => $hero,
            'help'  => __('Main image on the service detail page. Also set Featured Image in the sidebar if you want a thumbnail in lists.', 'nw-fuel'),
        ]);
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('SEO Description', 'nw-fuel'),
            'name'  => '_nw_meta_description',
            'value' => $meta_desc,
            'rows'  => 2,
        ]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Benefits', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Shown in the sidebar highlights and the benefits list.', 'nw-fuel'); ?></p>
        <div class="nw-repeater" data-nw-repeater="benefits" data-name="nw_benefits">
          <p class="nw-admin-empty"<?php echo $benefits ? ' style="display:none"' : ''; ?>><?php esc_html_e('No benefits yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($benefits as $i => $benefit) : ?>
            <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
              <input class="widefat" type="text" name="nw_benefits[]" value="<?php echo esc_attr($benefit); ?>">
              <div class="nw-repeater__actions">
                <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="benefits" data-template="nw-tpl-benefit"><?php esc_html_e('Add benefit', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-benefit">
          <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
            <input class="widefat" type="text" name="nw_benefits[]" value="">
            <div class="nw-repeater__actions">
              <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
            </div>
          </div>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Detail Sections', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Content blocks under the overview (eyebrow, title, text, image, bullet list, optional link).', 'nw-fuel'); ?></p>
        <div class="nw-repeater" data-nw-repeater="detail_sections" data-name="nw_detail_sections">
          <p class="nw-admin-empty"<?php echo $sections ? ' style="display:none"' : ''; ?>><?php esc_html_e('No detail sections yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($sections as $i => $section) :
                if (! is_array($section)) {
                    continue;
                }
                nw_fuel_render_detail_section_row((int) $i, $section);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="detail_sections" data-template="nw-tpl-detail-section"><?php esc_html_e('Add section', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-detail-section">
          <?php nw_fuel_render_detail_section_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Feature Cards', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="feature_cards" data-name="nw_feature_cards">
          <p class="nw-admin-empty"<?php echo $cards ? ' style="display:none"' : ''; ?>><?php esc_html_e('No feature cards yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($cards as $i => $card) :
                if (! is_array($card)) {
                    continue;
                }
                nw_fuel_render_feature_card_row((int) $i, $card);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="feature_cards" data-template="nw-tpl-feature-card"><?php esc_html_e('Add card', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-feature-card">
          <?php nw_fuel_render_feature_card_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Video Section', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Leave Embed URL empty to hide the video block on the frontend.', 'nw-fuel'); ?></p>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field([
              'label' => __('Eyebrow', 'nw-fuel'),
              'name'  => 'nw_video[eyebrow]',
              'value' => (string) ($video['eyebrow'] ?? ''),
          ]);
          nw_fuel_admin_field([
              'label' => __('Title', 'nw-fuel'),
              'name'  => 'nw_video[title]',
              'value' => (string) ($video['title'] ?? ''),
          ]);
          nw_fuel_admin_field([
              'label' => __('Embed URL', 'nw-fuel'),
              'name'  => 'nw_video[embedUrl]',
              'type'  => 'url',
              'value' => (string) ($video['embedUrl'] ?? ''),
              'help'  => __('YouTube embed URL, e.g. https://www.youtube.com/embed/...', 'nw-fuel'),
          ]);
          nw_fuel_admin_field([
              'label' => __('Video Title (iframe title)', 'nw-fuel'),
              'name'  => 'nw_video[videoTitle]',
              'value' => (string) ($video['videoTitle'] ?? ''),
          ]);
          ?>
        </div>
        <?php
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('Text', 'nw-fuel'),
            'name'  => 'nw_video[text]',
            'value' => (string) ($video['text'] ?? ''),
            'rows'  => 3,
        ]);
        $video_items = [];
        if (! empty($video['items']) && is_array($video['items'])) {
            $video_items = array_map('strval', $video['items']);
        }
        nw_fuel_admin_field([
            'type'        => 'textarea',
            'label'       => __('Bullet items (one per line)', 'nw-fuel'),
            'name'        => 'nw_video[items]',
            'value'       => implode("\n", $video_items),
            'rows'        => 4,
            'placeholder' => "First point\nSecond point",
        ]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Pricing Table', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="pricing" data-name="nw_pricing">
          <p class="nw-admin-empty"<?php echo $pricing ? ' style="display:none"' : ''; ?>><?php esc_html_e('No pricing rows yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($pricing as $i => $row) :
                if (! is_array($row)) {
                    continue;
                }
                nw_fuel_render_pricing_row((int) $i, $row);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="pricing" data-template="nw-tpl-pricing"><?php esc_html_e('Add pricing row', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-pricing">
          <?php nw_fuel_render_pricing_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Process Steps', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="process" data-name="nw_process">
          <p class="nw-admin-empty"<?php echo $process ? ' style="display:none"' : ''; ?>><?php esc_html_e('No process steps yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($process as $i => $step) :
                if (! is_array($step)) {
                    continue;
                }
                nw_fuel_render_process_row((int) $i, $step);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="process" data-template="nw-tpl-process"><?php esc_html_e('Add step', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-process">
          <?php nw_fuel_render_process_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('FAQs', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="faqs" data-name="nw_faqs">
          <p class="nw-admin-empty"<?php echo $faqs ? ' style="display:none"' : ''; ?>><?php esc_html_e('No FAQs yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($faqs as $i => $faq) :
                if (! is_array($faq)) {
                    continue;
                }
                nw_fuel_render_faq_row((int) $i, $faq);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="faqs" data-template="nw-tpl-faq"><?php esc_html_e('Add FAQ', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-faq">
          <?php nw_fuel_render_faq_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Related Services', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Select other services to show at the bottom of this page.', 'nw-fuel'); ?></p>
        <?php if (! $other_services) : ?>
        <p class="nw-admin-empty"><?php esc_html_e('No other services available yet.', 'nw-fuel'); ?></p>
        <?php else : ?>
        <div class="nw-related-list">
          <?php foreach ($other_services as $service_post) :
              $slug = (string) $service_post->post_name;
              ?>
          <label>
            <input type="checkbox" name="nw_related_slugs[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $related, true)); ?>>
            <?php echo esc_html(get_the_title($service_post)); ?>
            <code><?php echo esc_html($slug); ?></code>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

/**
 * Detail section row markup.
 *
 * @param int|string           $index
 * @param array<string, mixed> $section
 */
function nw_fuel_render_detail_section_row(int|string $index, array $section): void
{
    $prefix = 'nw_detail_sections[' . $index . ']';
    $items  = [];
    if (! empty($section['items']) && is_array($section['items'])) {
        $items = array_map('strval', $section['items']);
    }
    $image = is_array($section['image'] ?? null) ? $section['image'] : [];
    $link  = is_array($section['link'] ?? null) ? $section['link'] : [];
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Section', 'nw-fuel')); ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Eyebrow', 'nw-fuel'),
            'name'  => $prefix . '[eyebrow]',
            'value' => (string) ($section['eyebrow'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Title', 'nw-fuel'),
            'name'  => $prefix . '[title]',
            'value' => (string) ($section['title'] ?? ''),
        ]);
        ?>
      </div>
      <?php
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('Text', 'nw-fuel'),
          'name'  => $prefix . '[text]',
          'value' => (string) ($section['text'] ?? ''),
          'rows'  => 4,
      ]);
      nw_fuel_admin_media_field([
          'label' => __('Section Image', 'nw-fuel'),
          'name'  => $prefix . '[image_src]',
          'value' => (string) ($image['src'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'label' => __('Image Alt Text', 'nw-fuel'),
          'name'  => $prefix . '[image_alt]',
          'value' => (string) ($image['alt'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'type'        => 'textarea',
          'label'       => __('Bullet items (one per line)', 'nw-fuel'),
          'name'        => $prefix . '[items]',
          'value'       => implode("\n", $items),
          'rows'        => 5,
          'placeholder' => "Item one\nItem two",
      ]);
      ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Link Label', 'nw-fuel'),
            'name'  => $prefix . '[link_label]',
            'value' => (string) ($link['label'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Link URL / Anchor', 'nw-fuel'),
            'name'  => $prefix . '[link_href]',
            'value' => (string) ($link['href'] ?? ''),
            'help'  => __('Example: #service-quote-form-wrap or /contact/', 'nw-fuel'),
        ]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * Feature card row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $card
 */
function nw_fuel_render_feature_card_row(int|string $index, array $card): void
{
    $prefix = 'nw_feature_cards[' . $index . ']';
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Card', 'nw-fuel')); ?>
      <?php
      nw_fuel_admin_field([
          'label' => __('Title', 'nw-fuel'),
          'name'  => $prefix . '[title]',
          'value' => (string) ($card['title'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('Text', 'nw-fuel'),
          'name'  => $prefix . '[text]',
          'value' => (string) ($card['text'] ?? ''),
          'rows'  => 3,
      ]);
      ?>
    </div>
    <?php
}

/**
 * Pricing row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $row
 */
function nw_fuel_render_pricing_row(int|string $index, array $row): void
{
    $prefix = 'nw_pricing[' . $index . ']';
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Row', 'nw-fuel')); ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Style / Item', 'nw-fuel'),
            'name'  => $prefix . '[item]',
            'value' => (string) ($row['item'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Price', 'nw-fuel'),
            'name'  => $prefix . '[price]',
            'value' => (string) ($row['price'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Turnaround', 'nw-fuel'),
            'name'  => $prefix . '[timeframe]',
            'value' => (string) ($row['timeframe'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Notes', 'nw-fuel'),
            'name'  => $prefix . '[note]',
            'value' => (string) ($row['note'] ?? ''),
        ]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * Process step row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $step
 */
function nw_fuel_render_process_row(int|string $index, array $step): void
{
    $prefix = 'nw_process[' . $index . ']';
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Step', 'nw-fuel')); ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Step Number', 'nw-fuel'),
            'name'  => $prefix . '[step]',
            'value' => (string) ($step['step'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Title', 'nw-fuel'),
            'name'  => $prefix . '[title]',
            'value' => (string) ($step['title'] ?? ''),
        ]);
        ?>
      </div>
      <?php
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('Description', 'nw-fuel'),
          'name'  => $prefix . '[description]',
          'value' => (string) ($step['description'] ?? ''),
          'rows'  => 3,
      ]);
      ?>
    </div>
    <?php
}

/**
 * FAQ row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $faq
 */
function nw_fuel_render_faq_row(int|string $index, array $faq): void
{
    $prefix = 'nw_faqs[' . $index . ']';
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('FAQ', 'nw-fuel')); ?>
      <?php
      nw_fuel_admin_field([
          'label' => __('Question', 'nw-fuel'),
          'name'  => $prefix . '[question]',
          'value' => (string) ($faq['question'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('Answer', 'nw-fuel'),
          'name'  => $prefix . '[answer]',
          'value' => (string) ($faq['answer'] ?? ''),
          'rows'  => 4,
      ]);
      ?>
    </div>
    <?php
}

/**
 * Parse multiline textarea into clean string list.
 *
 * @return list<string>
 */
function nw_fuel_lines_to_list(mixed $raw): array
{
    if (! is_string($raw)) {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $out   = [];
    foreach ($lines as $line) {
        $line = trim(sanitize_text_field($line));
        if ($line !== '') {
            $out[] = $line;
        }
    }

    return $out;
}

/**
 * Sanitize posted repeater rows (associative list).
 *
 * @param mixed                $raw
 * @param callable(array):(?array) $mapper
 * @return list<array<string, mixed>>
 */
function nw_fuel_sanitize_repeater_rows(mixed $raw, callable $mapper): array
{
    if (! is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $row) {
        if (! is_array($row)) {
            continue;
        }
        $mapped = $mapper($row);
        if (is_array($mapped) && $mapped !== []) {
            $out[] = $mapped;
        }
    }

    return $out;
}
