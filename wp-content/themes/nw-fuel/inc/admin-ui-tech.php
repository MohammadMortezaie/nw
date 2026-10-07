<?php
/**
 * Admin CRUD UI — Technical Pages (nw_tech_resource).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render technical page admin UI.
 */
function nw_fuel_render_tech_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_technical_resource_meta', 'nw_fuel_technical_resource_meta_nonce');

    $rank_math   = function_exists('nw_fuel_rank_math_active') && nw_fuel_rank_math_active();
    $meta_title  = (string) get_post_meta($post->ID, '_nw_meta_title', true);
    $meta_desc   = (string) get_post_meta($post->ID, '_nw_meta_description', true);
    $remote      = (string) get_post_meta($post->ID, '_nw_remote_image', true);
    $contain     = (bool) get_post_meta($post->ID, '_nw_image_contain', true);
    $sections    = nw_fuel_json_meta($post->ID, '_nw_resource_sections');
    $app_table   = nw_fuel_json_meta($post->ID, '_nw_application_table');
    $downloads   = nw_fuel_json_meta($post->ID, '_nw_downloads');

    if ($app_table !== [] && array_is_list($app_table)) {
        $app_table = [];
    }

    $columns = [];
    if (! empty($app_table['columns']) && is_array($app_table['columns'])) {
        $columns = array_map('strval', $app_table['columns']);
    }
    $rows = [];
    if (! empty($app_table['rows']) && is_array($app_table['rows'])) {
        $rows = $app_table['rows'];
    }
    ?>
    <div class="nw-admin-wrap">
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Page copy', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Title uses the WordPress title field above. These map to the public technical guide page.', 'nw-fuel'); ?></p>
        <?php
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('Excerpt / Lead', 'nw-fuel'),
            'name'  => 'nw_tech_excerpt',
            'value' => (string) $post->post_excerpt,
            'rows'  => 3,
        ]);
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('Intro description', 'nw-fuel'),
            'name'  => 'nw_tech_content',
            'value' => wp_strip_all_tags((string) $post->post_content),
            'rows'  => 5,
        ]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php echo $rank_math ? esc_html__('Hero', 'nw-fuel') : esc_html__('Hero & SEO', 'nw-fuel'); ?></h3>
        <?php if ($rank_math) : ?>
        <p class="nw-admin-panel__help"><?php esc_html_e('SEO title, description, and social tags are in the Rank Math box on this page.', 'nw-fuel'); ?></p>
        <?php endif; ?>
        <?php
        nw_fuel_admin_media_field([
            'label' => __('Hero / Featured Image URL', 'nw-fuel'),
            'name'  => '_nw_remote_image',
            'value' => $remote,
            'help'  => __('Used when no Featured Image is set. Prefer Featured Image in the sidebar when possible.', 'nw-fuel'),
        ]);
        ?>
        <p>
          <label>
            <input type="checkbox" name="_nw_image_contain" value="1" <?php checked($contain); ?>>
            <?php esc_html_e('Contain hero image (object-fit: contain)', 'nw-fuel'); ?>
          </label>
        </p>
        <?php if (! $rank_math) : ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field([
              'label' => __('SEO Title', 'nw-fuel'),
              'name'  => '_nw_meta_title',
              'value' => $meta_title,
          ]);
          ?>
        </div>
        <?php
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('SEO Description', 'nw-fuel'),
            'name'  => '_nw_meta_description',
            'value' => $meta_desc,
            'rows'  => 2,
        ]);
        ?>
        <?php endif; ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Resource Sections', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="tech_sections" data-name="nw_tech_sections">
          <p class="nw-admin-empty"<?php echo $sections ? ' style="display:none"' : ''; ?>><?php esc_html_e('No sections yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($sections as $i => $section) :
                if (! is_array($section)) {
                    continue;
                }
                nw_fuel_render_tech_section_row((int) $i, $section);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="tech_sections" data-template="nw-tpl-tech-section"><?php esc_html_e('Add section', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-tech-section">
          <?php nw_fuel_render_tech_section_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Application Table', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Optional guide table. Define columns first, then add rows (one cell per column, in order).', 'nw-fuel'); ?></p>
        <?php
        nw_fuel_admin_field([
            'label' => __('Table Title', 'nw-fuel'),
            'name'  => 'nw_app_table[title]',
            'value' => (string) ($app_table['title'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('Table Intro Text', 'nw-fuel'),
            'name'  => 'nw_app_table[text]',
            'value' => (string) ($app_table['text'] ?? ''),
            'rows'  => 2,
        ]);
        ?>
        <div class="nw-repeater" data-nw-repeater="app_columns" data-name="nw_app_columns">
          <p class="nw-admin-empty"<?php echo $columns ? ' style="display:none"' : ''; ?>><?php esc_html_e('No columns yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($columns as $i => $col) : ?>
            <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
              <input class="widefat" type="text" name="nw_app_columns[]" value="<?php echo esc_attr($col); ?>" placeholder="<?php esc_attr_e('Column name', 'nw-fuel'); ?>">
              <div class="nw-repeater__actions">
                <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="app_columns" data-template="nw-tpl-app-column"><?php esc_html_e('Add column', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-app-column">
          <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
            <input class="widefat" type="text" name="nw_app_columns[]" value="" placeholder="<?php echo esc_attr__('Column name', 'nw-fuel'); ?>">
            <div class="nw-repeater__actions">
              <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
            </div>
          </div>
        </template>

        <h4 style="margin-top:16px;"><?php esc_html_e('Rows', 'nw-fuel'); ?></h4>
        <div class="nw-repeater" data-nw-repeater="app_rows" data-name="nw_app_rows">
          <p class="nw-admin-empty"<?php echo $rows ? ' style="display:none"' : ''; ?>><?php esc_html_e('No rows yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($rows as $i => $row) :
                if (! is_array($row)) {
                    continue;
                }
                $cells = [];
                foreach ($columns as $col) {
                    $cells[] = (string) ($row[$col] ?? '');
                }
                nw_fuel_render_app_row((int) $i, $cells, $columns);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="app_rows" data-template="nw-tpl-app-row"><?php esc_html_e('Add row', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-app-row">
          <?php nw_fuel_render_app_row('__INDEX__', [], $columns ?: ['']); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Downloads', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="tech_downloads" data-name="nw_tech_downloads">
          <p class="nw-admin-empty"<?php echo $downloads ? ' style="display:none"' : ''; ?>><?php esc_html_e('No downloads yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($downloads as $i => $download) :
                if (! is_array($download)) {
                    continue;
                }
                nw_fuel_render_download_row((int) $i, $download);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="tech_downloads" data-template="nw-tpl-download"><?php esc_html_e('Add download', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-download">
          <?php nw_fuel_render_download_row('__INDEX__', []); ?>
        </template>
      </div>
    </div>
    <?php
}

/**
 * Tech section row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $section
 */
function nw_fuel_render_tech_section_row(int|string $index, array $section): void
{
    $prefix = 'nw_tech_sections[' . $index . ']';
    $items  = [];
    if (! empty($section['items']) && is_array($section['items'])) {
        $items = array_map('strval', $section['items']);
    }
    $image = is_array($section['image'] ?? null) ? $section['image'] : [];
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
          'label' => __('Image Alt', 'nw-fuel'),
          'name'  => $prefix . '[image_alt]',
          'value' => (string) ($image['alt'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('List items (one per line)', 'nw-fuel'),
          'name'  => $prefix . '[items]',
          'value' => implode("\n", $items),
          'rows'  => 5,
      ]);
      ?>
    </div>
    <?php
}

/**
 * Application table row — cells one per line matching columns.
 *
 * @param int|string   $index
 * @param list<string> $cells
 * @param list<string> $columns
 */
function nw_fuel_render_app_row(int|string $index, array $cells, array $columns): void
{
    $hint = $columns !== [] ? implode(' / ', $columns) : __('one value per column, in order', 'nw-fuel');
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Row', 'nw-fuel')); ?>
      <?php
      nw_fuel_admin_field([
          'type'        => 'textarea',
          'label'       => sprintf(/* translators: %s: column names */ __('Cells (one per line: %s)', 'nw-fuel'), $hint),
          'name'        => 'nw_app_rows[' . $index . '][cells]',
          'value'       => implode("\n", $cells),
          'rows'        => max(2, count($columns) ?: 3),
          'placeholder' => "Value for col 1\nValue for col 2",
      ]);
      ?>
    </div>
    <?php
}

/**
 * Download row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $download
 */
function nw_fuel_render_download_row(int|string $index, array $download): void
{
    $prefix = 'nw_tech_downloads[' . $index . ']';
    $image  = is_array($download['image'] ?? null) ? $download['image'] : [];
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Download', 'nw-fuel')); ?>
      <?php
      nw_fuel_admin_field([
          'label' => __('Title', 'nw-fuel'),
          'name'  => $prefix . '[title]',
          'value' => (string) ($download['title'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'type'  => 'textarea',
          'label' => __('Text', 'nw-fuel'),
          'name'  => $prefix . '[text]',
          'value' => (string) ($download['text'] ?? ''),
          'rows'  => 2,
      ]);
      ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Button Label', 'nw-fuel'),
            'name'  => $prefix . '[label]',
            'value' => (string) ($download['label'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('File URL', 'nw-fuel'),
            'name'  => $prefix . '[href]',
            'type'  => 'url',
            'value' => (string) ($download['href'] ?? ''),
            'help'  => __('PDF or file URL (Media Library or /wp-content/themes/nw-fuel/assets/files/...).', 'nw-fuel'),
        ]);
        ?>
      </div>
      <?php
      nw_fuel_admin_media_field([
          'label' => __('Card Image', 'nw-fuel'),
          'name'  => $prefix . '[image_src]',
          'value' => (string) ($image['src'] ?? ''),
      ]);
      nw_fuel_admin_field([
          'label' => __('Image Alt', 'nw-fuel'),
          'name'  => $prefix . '[image_alt]',
          'value' => (string) ($image['alt'] ?? ''),
      ]);
      ?>
    </div>
    <?php
}

/**
 * Save technical page CRUD fields.
 */
function nw_fuel_save_tech_admin_fields(int $post_id): void
{
    if (! function_exists('nw_fuel_rank_math_active') || ! nw_fuel_rank_math_active()) {
        update_post_meta($post_id, '_nw_meta_title', sanitize_text_field(wp_unslash($_POST['_nw_meta_title'] ?? '')));
        update_post_meta($post_id, '_nw_meta_description', sanitize_textarea_field(wp_unslash($_POST['_nw_meta_description'] ?? '')));
    }
    update_post_meta($post_id, '_nw_remote_image', esc_url_raw(wp_unslash($_POST['_nw_remote_image'] ?? '')));
    update_post_meta($post_id, '_nw_image_contain', isset($_POST['_nw_image_contain']) ? '1' : '');

    $excerpt = sanitize_textarea_field(wp_unslash($_POST['nw_tech_excerpt'] ?? ''));
    $content = sanitize_textarea_field(wp_unslash($_POST['nw_tech_content'] ?? ''));
    remove_action('save_post_nw_tech_resource', 'nw_fuel_save_technical_resource_meta', 10);
    wp_update_post([
        'ID'           => $post_id,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
    ]);
    add_action('save_post_nw_tech_resource', 'nw_fuel_save_technical_resource_meta', 10, 2);

    $sections = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_tech_sections'] ?? []),
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $text  = sanitize_textarea_field((string) ($row['text'] ?? ''));
            $items = nw_fuel_lines_to_list($row['items'] ?? '');
            $src   = esc_url_raw((string) ($row['image_src'] ?? ''));
            if ($title === '' && $text === '' && $items === [] && $src === '') {
                return null;
            }
            $out = [
                'eyebrow' => sanitize_text_field((string) ($row['eyebrow'] ?? '')),
                'title'   => $title,
                'text'    => $text,
                'items'   => $items,
            ];
            if ($src !== '') {
                $out['image'] = [
                    'src' => $src,
                    'alt' => sanitize_text_field((string) ($row['image_alt'] ?? '')),
                ];
            }
            return $out;
        }
    );
    update_post_meta($post_id, '_nw_resource_sections', nw_fuel_json_encode_meta($sections));

    $columns_raw = wp_unslash($_POST['nw_app_columns'] ?? []);
    $columns     = [];
    if (is_array($columns_raw)) {
        foreach ($columns_raw as $col) {
            $col = sanitize_text_field((string) $col);
            if ($col !== '') {
                $columns[] = $col;
            }
        }
    }

    $table = [
        'title'   => sanitize_text_field(wp_unslash($_POST['nw_app_table']['title'] ?? '')),
        'text'    => sanitize_textarea_field(wp_unslash($_POST['nw_app_table']['text'] ?? '')),
        'columns' => $columns,
        'rows'    => [],
    ];

    $rows_raw = wp_unslash($_POST['nw_app_rows'] ?? []);
    if (is_array($rows_raw) && $columns !== []) {
        foreach ($rows_raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = nw_fuel_lines_to_list($row['cells'] ?? '');
            $mapped = [];
            $has = false;
            foreach ($columns as $ci => $col) {
                $val = $cells[$ci] ?? '';
                $mapped[$col] = $val;
                if ($val !== '') {
                    $has = true;
                }
            }
            if ($has) {
                $table['rows'][] = $mapped;
            }
        }
    }

    if ($table['title'] === '' && $table['text'] === '' && $table['columns'] === [] && $table['rows'] === []) {
        update_post_meta($post_id, '_nw_application_table', nw_fuel_json_encode_meta(new stdClass()));
    } else {
        update_post_meta($post_id, '_nw_application_table', nw_fuel_json_encode_meta($table));
    }

    $downloads = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_tech_downloads'] ?? []),
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $href  = esc_url_raw((string) ($row['href'] ?? ''));
            // Allow theme-relative paths that esc_url_raw may strip.
            if ($href === '' && ! empty($row['href'])) {
                $href = sanitize_text_field((string) $row['href']);
            }
            if ($title === '' && $href === '') {
                return null;
            }
            $out = [
                'title' => $title,
                'text'  => sanitize_textarea_field((string) ($row['text'] ?? '')),
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'href'  => $href,
            ];
            $src = (string) ($row['image_src'] ?? '');
            $src = esc_url_raw($src) ?: sanitize_text_field($src);
            if ($src !== '') {
                $out['image'] = [
                    'src' => $src,
                    'alt' => sanitize_text_field((string) ($row['image_alt'] ?? '')),
                ];
            }
            return $out;
        }
    );
    update_post_meta($post_id, '_nw_downloads', nw_fuel_json_encode_meta($downloads));
}
