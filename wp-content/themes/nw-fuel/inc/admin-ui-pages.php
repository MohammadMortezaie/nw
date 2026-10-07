<?php
/**
 * Admin CRUD UI — About & Contact page content.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render About page content editor.
 */
function nw_fuel_render_about_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_page_content', 'nw_fuel_page_content_nonce');
    $c = nw_fuel_resolve_page_content($post->ID, nw_fuel_about_content_defaults());
    ?>
    <div class="nw-admin-wrap">
      <p class="nw-admin-panel__help"><?php esc_html_e('Every text on the About page is editable below. Title field above is for admin only; front-end hero title is here.', 'nw-fuel'); ?></p>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Hero', 'nw-fuel'); ?></h3>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_about[hero_eyebrow]', 'value' => (string) ($c['hero_eyebrow'] ?? '')]);
          nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_about[hero_title]', 'value' => (string) ($c['hero_title'] ?? '')]);
          ?>
        </div>
        <?php
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => 'nw_about[hero_desc]', 'value' => (string) ($c['hero_desc'] ?? ''), 'rows' => 3]);
        ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Primary CTA', 'nw-fuel'), 'name' => 'nw_about[hero_cta_primary]', 'value' => (string) ($c['hero_cta_primary'] ?? '')]);
          nw_fuel_admin_field(['label' => __('Secondary CTA', 'nw-fuel'), 'name' => 'nw_about[hero_cta_secondary]', 'value' => (string) ($c['hero_cta_secondary'] ?? '')]);
          ?>
        </div>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Stats strip', 'nw-fuel'); ?></h3>
        <?php nw_fuel_render_simple_kv_repeater('nw_about_stats', 'nw_about[stats]', $c['stats'] ?? [], __('Stat', 'nw-fuel'), __('Value', 'nw-fuel'), __('Label', 'nw-fuel')); ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Our Story', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_about[story_eyebrow]', 'value' => (string) ($c['story_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_about[story_title]', 'value' => (string) ($c['story_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Paragraph 1', 'nw-fuel'), 'name' => 'nw_about[story_p1]', 'value' => (string) ($c['story_p1'] ?? ''), 'rows' => 4]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Paragraph 2', 'nw-fuel'), 'name' => 'nw_about[story_p2]', 'value' => (string) ($c['story_p2'] ?? ''), 'rows' => 4]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('History callout', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Large banner on About that links to the History of NW Fuel page.', 'nw-fuel'); ?></p>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_about[history_cta_eyebrow]', 'value' => (string) ($c['history_cta_eyebrow'] ?? '')]);
          nw_fuel_admin_field(['label' => __('Year', 'nw-fuel'), 'name' => 'nw_about[history_cta_year]', 'value' => (string) ($c['history_cta_year'] ?? '')]);
          ?>
        </div>
        <?php
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_about[history_cta_title]', 'value' => (string) ($c['history_cta_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Text', 'nw-fuel'), 'name' => 'nw_about[history_cta_text]', 'value' => (string) ($c['history_cta_text'] ?? ''), 'rows' => 3]);
        nw_fuel_admin_field(['label' => __('Button', 'nw-fuel'), 'name' => 'nw_about[history_cta_button]', 'value' => (string) ($c['history_cta_button'] ?? '')]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Values', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[values_eyebrow]', 'value' => (string) ($c['values_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[values_title]', 'value' => (string) ($c['values_title'] ?? '')]);
        nw_fuel_render_title_desc_repeater('about_values', 'nw_about[values]', $c['values'] ?? [], __('Value', 'nw-fuel'));
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Certifications', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[certs_eyebrow]', 'value' => (string) ($c['certs_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[certs_title]', 'value' => (string) ($c['certs_title'] ?? '')]);
        nw_fuel_render_title_desc_repeater('about_certs', 'nw_about[certifications]', $c['certifications'] ?? [], __('Certification', 'nw-fuel'));
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Workshop equipment', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[workshop_eyebrow]', 'value' => (string) ($c['workshop_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[workshop_title]', 'value' => (string) ($c['workshop_title'] ?? '')]);
        nw_fuel_render_string_list_repeater('about_equipment', 'nw_about[equipment]', $c['equipment'] ?? [], __('Add equipment', 'nw-fuel'));
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Team', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[team_eyebrow]', 'value' => (string) ($c['team_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[team_title]', 'value' => (string) ($c['team_title'] ?? '')]);
        nw_fuel_render_team_repeater($c['team'] ?? []);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Timeline', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[timeline_eyebrow]', 'value' => (string) ($c['timeline_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[timeline_title]', 'value' => (string) ($c['timeline_title'] ?? '')]);
        nw_fuel_render_timeline_repeater($c['timeline'] ?? []);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Trust strip', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_about[trust_eyebrow]', 'value' => (string) ($c['trust_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_about[trust_title]', 'value' => (string) ($c['trust_title'] ?? '')]);
        nw_fuel_render_simple_kv_repeater('nw_about_trust', 'nw_about[trust]', $c['trust'] ?? [], __('Trust item', 'nw-fuel'), __('Value', 'nw-fuel'), __('Label', 'nw-fuel'));
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Bottom CTA', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('CTA title', 'nw-fuel'), 'name' => 'nw_about[cta_title]', 'value' => (string) ($c['cta_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('CTA description', 'nw-fuel'), 'name' => 'nw_about[cta_description]', 'value' => (string) ($c['cta_description'] ?? ''), 'rows' => 2]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * Render History page content editor (hero / CTA only — stories are seeded).
 */
function nw_fuel_render_history_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_page_content', 'nw_fuel_page_content_nonce');
    $c = nw_fuel_resolve_page_content($post->ID, nw_fuel_history_content_defaults());
    ?>
    <div class="nw-admin-wrap">
      <p class="nw-admin-panel__help"><?php esc_html_e('Hero and bottom CTA copy for the History page. Timeline stories and photos come from the original company history and are not edited here.', 'nw-fuel'); ?></p>
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Hero', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_history[hero_eyebrow]', 'value' => (string) ($c['hero_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_history[hero_title]', 'value' => (string) ($c['hero_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => 'nw_history[hero_desc]', 'value' => (string) ($c['hero_desc'] ?? ''), 'rows' => 3]);
        nw_fuel_admin_field(['label' => __('Primary CTA', 'nw-fuel'), 'name' => 'nw_history[hero_cta_primary]', 'value' => (string) ($c['hero_cta_primary'] ?? '')]);
        ?>
      </div>
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Bottom CTA', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('CTA title', 'nw-fuel'), 'name' => 'nw_history[cta_title]', 'value' => (string) ($c['cta_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('CTA description', 'nw-fuel'), 'name' => 'nw_history[cta_description]', 'value' => (string) ($c['cta_description'] ?? ''), 'rows' => 2]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * Render Contact page content editor.
 */
function nw_fuel_render_contact_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_page_content', 'nw_fuel_page_content_nonce');
    $c = nw_fuel_resolve_page_content($post->ID, nw_fuel_contact_content_defaults());
    ?>
    <div class="nw-admin-wrap">
      <p class="nw-admin-panel__help"><?php esc_html_e('Phone, email, address, and hours still come from Appearance → Customize / NW Fuel Settings. Everything else on Contact is below.', 'nw-fuel'); ?></p>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Hero', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_contact[hero_eyebrow]', 'value' => (string) ($c['hero_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_contact[hero_title]', 'value' => (string) ($c['hero_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => 'nw_contact[hero_desc]', 'value' => (string) ($c['hero_desc'] ?? ''), 'rows' => 3]);
        nw_fuel_admin_field(['label' => __('Email button label', 'nw-fuel'), 'name' => 'nw_contact[hero_cta_email]', 'value' => (string) ($c['hero_cta_email'] ?? '')]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Stats strip', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Leave value blank and check “Use shop phone” for the phone stat.', 'nw-fuel'); ?></p>
        <?php nw_fuel_render_contact_stats_repeater($c['stats'] ?? []); ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Business details section', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Eyebrow', 'nw-fuel'), 'name' => 'nw_contact[details_eyebrow]', 'value' => (string) ($c['details_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => 'nw_contact[details_title]', 'value' => (string) ($c['details_title'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Address card label', 'nw-fuel'), 'name' => 'nw_contact[address_label]', 'value' => (string) ($c['address_label'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Address note', 'nw-fuel'), 'name' => 'nw_contact[address_note]', 'value' => (string) ($c['address_note'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Phone/email card label', 'nw-fuel'), 'name' => 'nw_contact[phone_email_label]', 'value' => (string) ($c['phone_email_label'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Hours card label', 'nw-fuel'), 'name' => 'nw_contact[hours_label]', 'value' => (string) ($c['hours_label'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Hours note', 'nw-fuel'), 'name' => 'nw_contact[hours_note]', 'value' => (string) ($c['hours_note'] ?? '')]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Island Diesel card', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Label', 'nw-fuel'), 'name' => 'nw_contact[island_label]', 'value' => (string) ($c['island_label'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Address', 'nw-fuel'), 'name' => 'nw_contact[island_address]', 'value' => (string) ($c['island_address'] ?? ''), 'rows' => 2]);
        nw_fuel_admin_field(['label' => __('Phone', 'nw-fuel'), 'name' => 'nw_contact[island_phone]', 'value' => (string) ($c['island_phone'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Website URL', 'nw-fuel'), 'name' => 'nw_contact[island_website]', 'value' => (string) ($c['island_website'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Website label', 'nw-fuel'), 'name' => 'nw_contact[island_website_label]', 'value' => (string) ($c['island_website_label'] ?? '')]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Form & map', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Form eyebrow', 'nw-fuel'), 'name' => 'nw_contact[form_eyebrow]', 'value' => (string) ($c['form_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Form title', 'nw-fuel'), 'name' => 'nw_contact[form_title]', 'value' => (string) ($c['form_title'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Success title', 'nw-fuel'), 'name' => 'nw_contact[form_success_title]', 'value' => (string) ($c['form_success_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Success text', 'nw-fuel'), 'name' => 'nw_contact[form_success_text]', 'value' => (string) ($c['form_success_text'] ?? ''), 'rows' => 2]);
        nw_fuel_admin_field(['label' => __('Map eyebrow', 'nw-fuel'), 'name' => 'nw_contact[map_eyebrow]', 'value' => (string) ($c['map_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Map title', 'nw-fuel'), 'name' => 'nw_contact[map_title]', 'value' => (string) ($c['map_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Map embed URL', 'nw-fuel'), 'name' => 'nw_contact[map_embed_url]', 'value' => (string) ($c['map_embed_url'] ?? ''), 'rows' => 2]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Contact FAQs', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('Section eyebrow', 'nw-fuel'), 'name' => 'nw_contact[faq_eyebrow]', 'value' => (string) ($c['faq_eyebrow'] ?? '')]);
        nw_fuel_admin_field(['label' => __('Section title', 'nw-fuel'), 'name' => 'nw_contact[faq_title]', 'value' => (string) ($c['faq_title'] ?? '')]);
        nw_fuel_render_faq_repeater('contact_faqs', 'nw_contact[faqs]', $c['faqs'] ?? []);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Bottom CTA', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field(['label' => __('CTA title', 'nw-fuel'), 'name' => 'nw_contact[cta_title]', 'value' => (string) ($c['cta_title'] ?? '')]);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('CTA description', 'nw-fuel'), 'name' => 'nw_contact[cta_description]', 'value' => (string) ($c['cta_description'] ?? ''), 'rows' => 2]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * @param list<mixed> $items
 */
function nw_fuel_render_simple_kv_repeater(string $id, string $name, array $items, string $row_label, string $value_label, string $label_label): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($name); ?>">
      <div class="nw-repeater__rows">
        <?php foreach ($items as $i => $item) :
            if (! is_array($item)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions($row_label); ?>
          <div class="nw-admin-grid nw-admin-grid--2">
            <?php
            nw_fuel_admin_field(['label' => $value_label, 'name' => $name . '[' . $i . '][value]', 'value' => (string) ($item['value'] ?? '')]);
            nw_fuel_admin_field(['label' => $label_label, 'name' => $name . '[' . $i . '][label]', 'value' => (string) ($item['label'] ?? '')]);
            ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="<?php echo esc_attr($id); ?>" data-template="nw-tpl-<?php echo esc_attr($id); ?>"><?php esc_html_e('Add row', 'nw-fuel'); ?></button>
    </div>
    <template id="nw-tpl-<?php echo esc_attr($id); ?>">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions($row_label); ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => $value_label, 'name' => $name . '[__INDEX__][value]', 'value' => '']);
          nw_fuel_admin_field(['label' => $label_label, 'name' => $name . '[__INDEX__][label]', 'value' => '']);
          ?>
        </div>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $items
 */
function nw_fuel_render_title_desc_repeater(string $id, string $name, array $items, string $row_label): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($name); ?>">
      <div class="nw-repeater__rows">
        <?php foreach ($items as $i => $item) :
            if (! is_array($item)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions($row_label); ?>
          <?php
          nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => $name . '[' . $i . '][title]', 'value' => (string) ($item['title'] ?? '')]);
          nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => $name . '[' . $i . '][description]', 'value' => (string) ($item['description'] ?? ''), 'rows' => 3]);
          ?>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="<?php echo esc_attr($id); ?>" data-template="nw-tpl-<?php echo esc_attr($id); ?>"><?php echo esc_html(sprintf(/* translators: %s: item type */ __('Add %s', 'nw-fuel'), strtolower($row_label))); ?></button>
    </div>
    <template id="nw-tpl-<?php echo esc_attr($id); ?>">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions($row_label); ?>
        <?php
        nw_fuel_admin_field(['label' => __('Title', 'nw-fuel'), 'name' => $name . '[__INDEX__][title]', 'value' => '']);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => $name . '[__INDEX__][description]', 'value' => '', 'rows' => 3]);
        ?>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $items
 */
function nw_fuel_render_string_list_repeater(string $id, string $name, array $items, string $add_label): void
{
    $items = array_values(array_map('strval', $items));
    ?>
    <div class="nw-repeater" data-nw-repeater="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($name); ?>">
      <div class="nw-repeater__rows">
        <?php foreach ($items as $i => $item) : ?>
        <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
          <input class="widefat" type="text" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($item); ?>">
          <div class="nw-repeater__actions">
            <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="<?php echo esc_attr($id); ?>" data-template="nw-tpl-<?php echo esc_attr($id); ?>"><?php echo esc_html($add_label); ?></button>
    </div>
    <template id="nw-tpl-<?php echo esc_attr($id); ?>">
      <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
        <input class="widefat" type="text" name="<?php echo esc_attr($name); ?>[]" value="">
        <div class="nw-repeater__actions">
          <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
        </div>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $team
 */
function nw_fuel_render_team_repeater(array $team): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="about_team" data-name="nw_about[team]">
      <div class="nw-repeater__rows">
        <?php foreach ($team as $i => $member) :
            if (! is_array($member)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions(__('Team member', 'nw-fuel')); ?>
          <div class="nw-admin-grid nw-admin-grid--2">
            <?php
            nw_fuel_admin_field(['label' => __('Name', 'nw-fuel'), 'name' => 'nw_about[team][' . $i . '][name]', 'value' => (string) ($member['name'] ?? '')]);
            nw_fuel_admin_field(['label' => __('Role', 'nw-fuel'), 'name' => 'nw_about[team][' . $i . '][role]', 'value' => (string) ($member['role'] ?? '')]);
            ?>
          </div>
          <?php nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => 'nw_about[team][' . $i . '][description]', 'value' => (string) ($member['description'] ?? ''), 'rows' => 2]); ?>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="about_team" data-template="nw-tpl-about-team"><?php esc_html_e('Add team member', 'nw-fuel'); ?></button>
    </div>
    <template id="nw-tpl-about-team">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions(__('Team member', 'nw-fuel')); ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Name', 'nw-fuel'), 'name' => 'nw_about[team][__INDEX__][name]', 'value' => '']);
          nw_fuel_admin_field(['label' => __('Role', 'nw-fuel'), 'name' => 'nw_about[team][__INDEX__][role]', 'value' => '']);
          ?>
        </div>
        <?php nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Description', 'nw-fuel'), 'name' => 'nw_about[team][__INDEX__][description]', 'value' => '', 'rows' => 2]); ?>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $timeline
 */
function nw_fuel_render_timeline_repeater(array $timeline): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="about_timeline" data-name="nw_about[timeline]">
      <div class="nw-repeater__rows">
        <?php foreach ($timeline as $i => $item) :
            if (! is_array($item)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions(__('Timeline', 'nw-fuel')); ?>
          <div class="nw-admin-grid nw-admin-grid--2">
            <?php
            nw_fuel_admin_field(['label' => __('Year', 'nw-fuel'), 'name' => 'nw_about[timeline][' . $i . '][year]', 'value' => (string) ($item['year'] ?? '')]);
            nw_fuel_admin_field(['label' => __('Event', 'nw-fuel'), 'name' => 'nw_about[timeline][' . $i . '][event]', 'value' => (string) ($item['event'] ?? '')]);
            ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="about_timeline" data-template="nw-tpl-about-timeline"><?php esc_html_e('Add timeline item', 'nw-fuel'); ?></button>
    </div>
    <template id="nw-tpl-about-timeline">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions(__('Timeline', 'nw-fuel')); ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Year', 'nw-fuel'), 'name' => 'nw_about[timeline][__INDEX__][year]', 'value' => '']);
          nw_fuel_admin_field(['label' => __('Event', 'nw-fuel'), 'name' => 'nw_about[timeline][__INDEX__][event]', 'value' => '']);
          ?>
        </div>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $stats
 */
function nw_fuel_render_contact_stats_repeater(array $stats): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="contact_stats" data-name="nw_contact[stats]">
      <div class="nw-repeater__rows">
        <?php foreach ($stats as $i => $item) :
            if (! is_array($item)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions(__('Stat', 'nw-fuel')); ?>
          <div class="nw-admin-grid nw-admin-grid--2">
            <?php
            nw_fuel_admin_field(['label' => __('Value', 'nw-fuel'), 'name' => 'nw_contact[stats][' . $i . '][value]', 'value' => (string) ($item['value'] ?? '')]);
            nw_fuel_admin_field(['label' => __('Label', 'nw-fuel'), 'name' => 'nw_contact[stats][' . $i . '][label]', 'value' => (string) ($item['label'] ?? '')]);
            ?>
          </div>
          <p><label><input type="checkbox" name="nw_contact[stats][<?php echo esc_attr((string) $i); ?>][use_phone]" value="1" <?php checked(! empty($item['use_phone'])); ?>> <?php esc_html_e('Use shop phone as value', 'nw-fuel'); ?></label></p>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="contact_stats" data-template="nw-tpl-contact-stats"><?php esc_html_e('Add stat', 'nw-fuel'); ?></button>
    </div>
    <template id="nw-tpl-contact-stats">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions(__('Stat', 'nw-fuel')); ?>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field(['label' => __('Value', 'nw-fuel'), 'name' => 'nw_contact[stats][__INDEX__][value]', 'value' => '']);
          nw_fuel_admin_field(['label' => __('Label', 'nw-fuel'), 'name' => 'nw_contact[stats][__INDEX__][label]', 'value' => '']);
          ?>
        </div>
        <p><label><input type="checkbox" name="nw_contact[stats][__INDEX__][use_phone]" value="1"> <?php echo esc_html__('Use shop phone as value', 'nw-fuel'); ?></label></p>
      </div>
    </template>
    <?php
}

/**
 * @param list<mixed> $faqs
 */
function nw_fuel_render_faq_repeater(string $id, string $name, array $faqs): void
{
    ?>
    <div class="nw-repeater" data-nw-repeater="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($name); ?>">
      <div class="nw-repeater__rows">
        <?php foreach ($faqs as $i => $faq) :
            if (! is_array($faq)) {
                continue;
            }
            ?>
        <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $i); ?>">
          <?php nw_fuel_admin_row_actions(__('FAQ', 'nw-fuel')); ?>
          <?php
          nw_fuel_admin_field(['label' => __('Question', 'nw-fuel'), 'name' => $name . '[' . $i . '][question]', 'value' => (string) ($faq['question'] ?? '')]);
          nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Answer', 'nw-fuel'), 'name' => $name . '[' . $i . '][answer]', 'value' => (string) ($faq['answer'] ?? ''), 'rows' => 3]);
          ?>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="button" data-nw-repeater-add="<?php echo esc_attr($id); ?>" data-template="nw-tpl-<?php echo esc_attr($id); ?>"><?php esc_html_e('Add FAQ', 'nw-fuel'); ?></button>
    </div>
    <template id="nw-tpl-<?php echo esc_attr($id); ?>">
      <div class="nw-repeater__row" data-index="__INDEX__">
        <?php nw_fuel_admin_row_actions(__('FAQ', 'nw-fuel')); ?>
        <?php
        nw_fuel_admin_field(['label' => __('Question', 'nw-fuel'), 'name' => $name . '[__INDEX__][question]', 'value' => '']);
        nw_fuel_admin_field(['type' => 'textarea', 'label' => __('Answer', 'nw-fuel'), 'name' => $name . '[__INDEX__][answer]', 'value' => '', 'rows' => 3]);
        ?>
      </div>
    </template>
    <?php
}

/**
 * Save About page fields.
 */
function nw_fuel_save_about_admin_fields(int $post_id): void
{
    $raw = wp_unslash($_POST['nw_about'] ?? []);
    if (! is_array($raw)) {
        return;
    }

    $content = [
        'hero_eyebrow'       => sanitize_text_field((string) ($raw['hero_eyebrow'] ?? '')),
        'hero_title'         => sanitize_text_field((string) ($raw['hero_title'] ?? '')),
        'hero_desc'          => sanitize_textarea_field((string) ($raw['hero_desc'] ?? '')),
        'hero_cta_primary'   => sanitize_text_field((string) ($raw['hero_cta_primary'] ?? '')),
        'hero_cta_secondary' => sanitize_text_field((string) ($raw['hero_cta_secondary'] ?? '')),
        'story_eyebrow'      => sanitize_text_field((string) ($raw['story_eyebrow'] ?? '')),
        'story_title'        => sanitize_text_field((string) ($raw['story_title'] ?? '')),
        'story_p1'           => sanitize_textarea_field((string) ($raw['story_p1'] ?? '')),
        'story_p2'           => sanitize_textarea_field((string) ($raw['story_p2'] ?? '')),
        'history_cta_eyebrow'=> sanitize_text_field((string) ($raw['history_cta_eyebrow'] ?? '')),
        'history_cta_year'   => sanitize_text_field((string) ($raw['history_cta_year'] ?? '')),
        'history_cta_title'  => sanitize_text_field((string) ($raw['history_cta_title'] ?? '')),
        'history_cta_text'   => sanitize_textarea_field((string) ($raw['history_cta_text'] ?? '')),
        'history_cta_button' => sanitize_text_field((string) ($raw['history_cta_button'] ?? '')),
        'values_eyebrow'     => sanitize_text_field((string) ($raw['values_eyebrow'] ?? '')),
        'values_title'       => sanitize_text_field((string) ($raw['values_title'] ?? '')),
        'certs_eyebrow'      => sanitize_text_field((string) ($raw['certs_eyebrow'] ?? '')),
        'certs_title'        => sanitize_text_field((string) ($raw['certs_title'] ?? '')),
        'workshop_eyebrow'   => sanitize_text_field((string) ($raw['workshop_eyebrow'] ?? '')),
        'workshop_title'     => sanitize_text_field((string) ($raw['workshop_title'] ?? '')),
        'team_eyebrow'       => sanitize_text_field((string) ($raw['team_eyebrow'] ?? '')),
        'team_title'         => sanitize_text_field((string) ($raw['team_title'] ?? '')),
        'timeline_eyebrow'   => sanitize_text_field((string) ($raw['timeline_eyebrow'] ?? '')),
        'timeline_title'     => sanitize_text_field((string) ($raw['timeline_title'] ?? '')),
        'trust_eyebrow'      => sanitize_text_field((string) ($raw['trust_eyebrow'] ?? '')),
        'trust_title'        => sanitize_text_field((string) ($raw['trust_title'] ?? '')),
        'cta_title'          => sanitize_text_field((string) ($raw['cta_title'] ?? '')),
        'cta_description'    => sanitize_textarea_field((string) ($raw['cta_description'] ?? '')),
        'stats'              => nw_fuel_sanitize_kv_rows($raw['stats'] ?? []),
        'trust'              => nw_fuel_sanitize_kv_rows($raw['trust'] ?? []),
        'values'             => nw_fuel_sanitize_title_desc_rows($raw['values'] ?? []),
        'certifications'     => nw_fuel_sanitize_title_desc_rows($raw['certifications'] ?? []),
        'equipment'          => nw_fuel_sanitize_string_list($raw['equipment'] ?? []),
        'team'               => nw_fuel_sanitize_team_rows($raw['team'] ?? []),
        'timeline'           => nw_fuel_sanitize_timeline_rows($raw['timeline'] ?? []),
    ];

    nw_fuel_update_page_content($post_id, $content);
}

/**
 * Save History page fields.
 */
function nw_fuel_save_history_admin_fields(int $post_id): void
{
    $raw = wp_unslash($_POST['nw_history'] ?? []);
    if (! is_array($raw)) {
        return;
    }

    $content = [
        'hero_eyebrow'     => sanitize_text_field((string) ($raw['hero_eyebrow'] ?? '')),
        'hero_title'       => sanitize_text_field((string) ($raw['hero_title'] ?? '')),
        'hero_desc'        => sanitize_textarea_field((string) ($raw['hero_desc'] ?? '')),
        'hero_cta_primary' => sanitize_text_field((string) ($raw['hero_cta_primary'] ?? '')),
        'cta_title'        => sanitize_text_field((string) ($raw['cta_title'] ?? '')),
        'cta_description'  => sanitize_textarea_field((string) ($raw['cta_description'] ?? '')),
    ];

    nw_fuel_update_page_content($post_id, $content);
}

/**
 * Save Contact page fields.
 */
function nw_fuel_save_contact_admin_fields(int $post_id): void
{
    $raw = wp_unslash($_POST['nw_contact'] ?? []);
    if (! is_array($raw)) {
        return;
    }

    $stats = [];
    foreach ((array) ($raw['stats'] ?? []) as $row) {
        if (! is_array($row)) {
            continue;
        }
        $label = sanitize_text_field((string) ($row['label'] ?? ''));
        $value = sanitize_text_field((string) ($row['value'] ?? ''));
        $use   = ! empty($row['use_phone']);
        if ($label === '' && $value === '' && ! $use) {
            continue;
        }
        $stats[] = ['value' => $value, 'label' => $label, 'use_phone' => $use];
    }

    $faqs = nw_fuel_sanitize_repeater_rows(
        $raw['faqs'] ?? [],
        static function (array $row): ?array {
            $q = sanitize_text_field((string) ($row['question'] ?? ''));
            $a = sanitize_textarea_field((string) ($row['answer'] ?? ''));
            if ($q === '' && $a === '') {
                return null;
            }
            return ['question' => $q, 'answer' => $a];
        }
    );

    $content = [
        'hero_eyebrow'         => sanitize_text_field((string) ($raw['hero_eyebrow'] ?? '')),
        'hero_title'           => sanitize_text_field((string) ($raw['hero_title'] ?? '')),
        'hero_desc'            => sanitize_textarea_field((string) ($raw['hero_desc'] ?? '')),
        'hero_cta_email'       => sanitize_text_field((string) ($raw['hero_cta_email'] ?? '')),
        'details_eyebrow'      => sanitize_text_field((string) ($raw['details_eyebrow'] ?? '')),
        'details_title'        => sanitize_text_field((string) ($raw['details_title'] ?? '')),
        'address_label'        => sanitize_text_field((string) ($raw['address_label'] ?? '')),
        'address_note'         => sanitize_text_field((string) ($raw['address_note'] ?? '')),
        'phone_email_label'    => sanitize_text_field((string) ($raw['phone_email_label'] ?? '')),
        'hours_label'          => sanitize_text_field((string) ($raw['hours_label'] ?? '')),
        'hours_note'           => sanitize_text_field((string) ($raw['hours_note'] ?? '')),
        'island_label'         => sanitize_text_field((string) ($raw['island_label'] ?? '')),
        'island_address'       => sanitize_textarea_field((string) ($raw['island_address'] ?? '')),
        'island_phone'         => sanitize_text_field((string) ($raw['island_phone'] ?? '')),
        'island_website'       => esc_url_raw((string) ($raw['island_website'] ?? '')),
        'island_website_label' => sanitize_text_field((string) ($raw['island_website_label'] ?? '')),
        'form_eyebrow'         => sanitize_text_field((string) ($raw['form_eyebrow'] ?? '')),
        'form_title'           => sanitize_text_field((string) ($raw['form_title'] ?? '')),
        'form_success_title'   => sanitize_text_field((string) ($raw['form_success_title'] ?? '')),
        'form_success_text'    => sanitize_textarea_field((string) ($raw['form_success_text'] ?? '')),
        'map_eyebrow'          => sanitize_text_field((string) ($raw['map_eyebrow'] ?? '')),
        'map_title'            => sanitize_text_field((string) ($raw['map_title'] ?? '')),
        'map_embed_url'        => esc_url_raw((string) ($raw['map_embed_url'] ?? '')),
        'faq_eyebrow'          => sanitize_text_field((string) ($raw['faq_eyebrow'] ?? '')),
        'faq_title'            => sanitize_text_field((string) ($raw['faq_title'] ?? '')),
        'cta_title'            => sanitize_text_field((string) ($raw['cta_title'] ?? '')),
        'cta_description'      => sanitize_textarea_field((string) ($raw['cta_description'] ?? '')),
        'stats'                => $stats,
        'faqs'                 => $faqs,
    ];

    nw_fuel_update_page_content($post_id, $content);
}

/**
 * @param mixed $rows
 * @return list<array{value: string, label: string}>
 */
function nw_fuel_sanitize_kv_rows(mixed $rows): array
{
    return nw_fuel_sanitize_repeater_rows(
        $rows,
        static function (array $row): ?array {
            $value = sanitize_text_field((string) ($row['value'] ?? ''));
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            if ($value === '' && $label === '') {
                return null;
            }
            return ['value' => $value, 'label' => $label];
        }
    );
}

/**
 * @param mixed $rows
 * @return list<array{title: string, description: string}>
 */
function nw_fuel_sanitize_title_desc_rows(mixed $rows): array
{
    return nw_fuel_sanitize_repeater_rows(
        $rows,
        static function (array $row): ?array {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $desc  = sanitize_textarea_field((string) ($row['description'] ?? ''));
            if ($title === '' && $desc === '') {
                return null;
            }
            return ['title' => $title, 'description' => $desc];
        }
    );
}

/**
 * @param mixed $items
 * @return list<string>
 */
function nw_fuel_sanitize_string_list(mixed $items): array
{
    $out = [];
    foreach ((array) $items as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * @param mixed $rows
 * @return list<array{name: string, role: string, description: string}>
 */
function nw_fuel_sanitize_team_rows(mixed $rows): array
{
    return nw_fuel_sanitize_repeater_rows(
        $rows,
        static function (array $row): ?array {
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            $role = sanitize_text_field((string) ($row['role'] ?? ''));
            $desc = sanitize_textarea_field((string) ($row['description'] ?? ''));
            if ($name === '' && $role === '' && $desc === '') {
                return null;
            }
            return ['name' => $name, 'role' => $role, 'description' => $desc];
        }
    );
}

/**
 * @param mixed $rows
 * @return list<array{year: string, event: string}>
 */
function nw_fuel_sanitize_timeline_rows(mixed $rows): array
{
    return nw_fuel_sanitize_repeater_rows(
        $rows,
        static function (array $row): ?array {
            $year  = sanitize_text_field((string) ($row['year'] ?? ''));
            $event = sanitize_text_field((string) ($row['event'] ?? ''));
            if ($year === '' && $event === '') {
                return null;
            }
            return ['year' => $year, 'event' => $event];
        }
    );
}
