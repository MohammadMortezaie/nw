<?php
/**
 * Admin CRUD UI — WooCommerce products (NW Fuel fields).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_nw_fuel_search_related_products', 'nw_fuel_ajax_search_related_products');

/**
 * Search a small set of products for the Related Products admin picker.
 */
function nw_fuel_ajax_search_related_products(): void
{
    check_ajax_referer('nw_fuel_related_products', 'nonce');

    if (! current_user_can('edit_products')) {
        wp_send_json_error(['message' => __('You are not allowed to edit products.', 'nw-fuel')], 403);
    }

    $query      = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
    $product_id = absint($_GET['product_id'] ?? 0);
    if (mb_strlen($query) < 2) {
        wp_send_json_success([]);
    }

    $products = [];
    foreach (nw_fuel_product_search_suggestions($query, 20) as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1 || $id === $product_id) {
            continue;
        }
        $products[] = [
            'id'    => $id,
            'title' => (string) ($row['name'] ?? ''),
            'slug'  => (string) ($row['slug'] ?? ''),
            'part'  => (string) ($row['part'] ?? ''),
            'image' => (string) ($row['image'] ?? ''),
        ];
    }

    wp_send_json_success($products);
}

/**
 * Render product details CRUD UI.
 */
function nw_fuel_render_product_admin_ui(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_product_meta', 'nw_fuel_product_meta_nonce');

    $part      = nw_fuel_get_part_number($post->ID);
    $code      = nw_fuel_get_product_code($post->ID);
    $retail    = (string) get_post_meta($post->ID, '_nw_retail', true);
    $level1    = (string) get_post_meta($post->ID, '_nw_trade_total', true);
    $level2    = (string) get_post_meta($post->ID, '_nw_special1_total', true);
    $level3    = (string) get_post_meta($post->ID, '_nw_special2_total', true);
    $level4    = (string) get_post_meta($post->ID, '_nw_special3_total', true);
    $core      = (string) get_post_meta($post->ID, '_nw_cost_p_core', true);
    $short     = (string) get_post_meta($post->ID, '_nw_short_description', true);
    $vehicles = array_values(array_map('strval', nw_fuel_json_meta($post->ID, '_nw_compatible_vehicles')));
    $alts     = function_exists('nw_fuel_get_search_alts') ? nw_fuel_get_search_alts($post->ID) : [];
    $features = array_values(array_map('strval', nw_fuel_json_meta($post->ID, '_nw_features')));
    $specs    = nw_fuel_json_meta($post->ID, '_nw_specifications');
    $faqs     = nw_fuel_json_meta($post->ID, '_nw_product_faqs');
    $related  = array_values(array_map('strval', nw_fuel_json_meta($post->ID, '_nw_related_slugs')));
    $photo_ids = function_exists('nw_fuel_product_photo_ids') ? nw_fuel_product_photo_ids($post->ID) : [];
    $photo_max = defined('NW_FUEL_PRODUCT_PHOTOS_MAX') ? NW_FUEL_PRODUCT_PHOTOS_MAX : 5;
    $has_remote = (string) get_post_meta($post->ID, '_nw_remote_image', true) !== ''
        || nw_fuel_json_meta($post->ID, '_nw_remote_gallery') !== [];
    $brand_options = function_exists('nw_fuel_attribute_terms') ? nw_fuel_attribute_terms('pa_brand') : [];
    $current_brand = function_exists('nw_fuel_product_term_names')
        ? (nw_fuel_product_term_names($post->ID, 'pa_brand')[0] ?? '')
        : '';
    if ($current_brand === '') {
        $current_brand = (string) get_post_meta($post->ID, '_nw_price_book', true);
    }
    if ($current_brand !== '' && ! in_array($current_brand, $brand_options, true)) {
        array_unshift($brand_options, $current_brand);
    }
    $api_managed = (string) get_post_meta($post->ID, '_nw_inventory_managed', true) === 'yes'
        || metadata_exists('post', $post->ID, '_nw_website');

    $selected_related_products = [];
    foreach ($related as $slug) {
        $related_post = get_page_by_path($slug, OBJECT, 'product');
        if ($related_post instanceof WP_Post && $related_post->ID !== $post->ID) {
            $selected_related_products[] = $related_post;
        }
    }
    ?>
    <div class="nw-admin-wrap" data-nw-api-managed="<?php echo $api_managed ? '1' : '0'; ?>">
      <?php if ($api_managed) : ?>
      <p class="nw-api-managed-notice">
        <?php nw_fuel_admin_api_badge(); ?>
        <?php esc_html_e('Changes to marked fields may be replaced by the nightly inventory sync.', 'nw-fuel'); ?>
      </p>
      <?php endif; ?>
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Catalog details', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Use WooCommerce title, description, and categories in the main product fields. Brand is the Attributes → Brand list. These NW Fuel fields power the custom product page.', 'nw-fuel'); ?></p>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field([
              'label'       => __('Part Number', 'nw-fuel'),
              'name'        => '_nw_part_number',
              'value'       => $part,
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label' => __('Product Code (admin only)', 'nw-fuel'),
              'name'  => '_nw_product_code',
              'value' => $code,
              'help'  => __('Hidden on the frontend; included in quote emails only.', 'nw-fuel'),
          ]);
          ?>
        </div>
        <div class="nw-admin-grid nw-admin-grid--2" style="margin-top:12px;">
          <div class="nw-admin-field">
            <label for="nw_product_brand"><?php esc_html_e('Brand', 'nw-fuel'); ?> <?php if ($api_managed) { nw_fuel_admin_api_badge(); } ?></label>
            <select class="widefat" id="nw_product_brand" name="nw_product_brand">
              <option value=""><?php esc_html_e('Select a brand', 'nw-fuel'); ?></option>
              <?php foreach ($brand_options as $brand_name) : ?>
              <option value="<?php echo esc_attr($brand_name); ?>"<?php selected($current_brand, $brand_name); ?>><?php echo esc_html($brand_name); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('From Products → Attributes → Brand. Nightly PriceBook also sets this.', 'nw-fuel'); ?></p>
          </div>
          <div class="nw-admin-field">
            <label for="nw_product_brand_new"><?php esc_html_e('Add new brand', 'nw-fuel'); ?> <?php if ($api_managed) { nw_fuel_admin_api_badge(); } ?></label>
            <input class="widefat" type="text" id="nw_product_brand_new" name="nw_product_brand_new" value="" placeholder="<?php esc_attr_e('Type a new brand name', 'nw-fuel'); ?>">
            <p class="description"><?php esc_html_e('Saves as a new Attributes → Brand term and assigns it to this product.', 'nw-fuel'); ?></p>
          </div>
        </div>
        <h3 class="nw-admin-panel__title" style="margin-top:16px;"><?php esc_html_e('Pricing', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Retail (column H) is public. Levels 1–4 are columns I–L and show only to partners with that discount level. Core (column N) is stored for sale later.', 'nw-fuel'); ?></p>
        <div class="nw-admin-grid nw-admin-grid--2">
          <?php
          nw_fuel_admin_field([
              'label'       => __('Retail (column H)', 'nw-fuel'),
              'name'        => '_nw_retail',
              'value'       => $retail,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label'       => __('Level 1 (column I)', 'nw-fuel'),
              'name'        => '_nw_trade_total',
              'value'       => $level1,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label'       => __('Level 2 (column J)', 'nw-fuel'),
              'name'        => '_nw_special1_total',
              'value'       => $level2,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label'       => __('Level 3 (column K)', 'nw-fuel'),
              'name'        => '_nw_special2_total',
              'value'       => $level3,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label'       => __('Level 4 (column L)', 'nw-fuel'),
              'name'        => '_nw_special3_total',
              'value'       => $level4,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          nw_fuel_admin_field([
              'label'       => __('Core (column N)', 'nw-fuel'),
              'name'        => '_nw_cost_p_core',
              'value'       => $core,
              'placeholder' => '0.00',
              'api_managed' => $api_managed,
          ]);
          ?>
        </div>
        <?php
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('Short Description (catalog lead)', 'nw-fuel'),
            'name'  => '_nw_short_description',
            'value' => $short,
            'rows'  => 2,
            'api_managed' => $api_managed,
        ]);
        ?>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Product photos', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php echo esc_html(sprintf(/* translators: %d: max photos */ __('Upload 1 to %d photos. The first photo is the main catalog image.', 'nw-fuel'), $photo_max)); ?></p>
        <?php if ($photo_ids === [] && $has_remote) : ?>
        <p class="description"><?php esc_html_e('This product currently uses remote image URLs. Upload photos here to replace them.', 'nw-fuel'); ?></p>
        <?php endif; ?>
        <input type="hidden" name="nw_product_photos_present" value="1">
        <div class="nw-product-photos" data-nw-product-photos data-max="<?php echo esc_attr((string) $photo_max); ?>">
          <p class="nw-admin-empty"<?php echo $photo_ids ? ' style="display:none"' : ''; ?>><?php esc_html_e('No photos yet. Add at least one.', 'nw-fuel'); ?></p>
          <div class="nw-product-photos__list">
            <?php foreach ($photo_ids as $photo_id) :
                nw_fuel_render_product_photo_item((int) $photo_id);
            endforeach; ?>
          </div>
          <p>
            <button type="button" class="button" data-nw-product-photos-add<?php echo count($photo_ids) >= $photo_max ? ' disabled' : ''; ?>><?php esc_html_e('Add photos', 'nw-fuel'); ?></button>
            <span class="description" data-nw-product-photos-count><?php echo esc_html(sprintf(/* translators: 1: current count, 2: max */ __('%1$d of %2$d photos', 'nw-fuel'), count($photo_ids), $photo_max)); ?></span>
          </p>
        </div>
        <template id="nw-tpl-product-photo">
          <?php nw_fuel_render_product_photo_item(0, '__URL__'); ?>
        </template>
      </div>

      <?php if (! function_exists('nw_fuel_rank_math_active') || ! nw_fuel_rank_math_active()) : ?>
      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('SEO', 'nw-fuel'); ?></h3>
        <?php
        nw_fuel_admin_field([
            'label' => __('SEO Title', 'nw-fuel'),
            'name'  => '_nw_meta_title',
            'value' => (string) get_post_meta($post->ID, '_nw_meta_title', true),
        ]);
        nw_fuel_admin_field([
            'type'  => 'textarea',
            'label' => __('SEO Description', 'nw-fuel'),
            'name'  => '_nw_meta_description',
            'value' => (string) get_post_meta($post->ID, '_nw_meta_description', true),
            'rows'  => 2,
        ]);
        ?>
      </div>
      <?php endif; ?>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Compatible Vehicles', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="product_vehicles" data-name="nw_product_vehicles">
          <p class="nw-admin-empty"<?php echo $vehicles ? ' style="display:none"' : ''; ?>><?php esc_html_e('No vehicles yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($vehicles as $i => $vehicle) : ?>
            <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
              <input class="widefat" type="text" name="nw_product_vehicles[]" value="<?php echo esc_attr($vehicle); ?>">
              <div class="nw-repeater__actions">
                <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="product_vehicles" data-template="nw-tpl-vehicle"><?php esc_html_e('Add vehicle', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-vehicle">
          <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
            <input class="widefat" type="text" name="nw_product_vehicles[]" value="">
            <div class="nw-repeater__actions">
              <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
            </div>
          </div>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Alternates', 'nw-fuel'); ?> <?php if ($api_managed) { nw_fuel_admin_api_badge(); } ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Alternate part numbers used for search (from the Excel “Alternates for search” column).', 'nw-fuel'); ?></p>
        <div class="nw-repeater" data-nw-repeater="product_alts" data-name="nw_product_alts">
          <p class="nw-admin-empty"<?php echo $alts ? ' style="display:none"' : ''; ?>><?php esc_html_e('No alternates yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($alts as $i => $alt) : ?>
            <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
              <input class="widefat" type="text" name="nw_product_alts[]" value="<?php echo esc_attr((string) $alt); ?>">
              <div class="nw-repeater__actions">
                <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="product_alts" data-template="nw-tpl-alt"><?php esc_html_e('Add alternate', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-alt">
          <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
            <input class="widefat" type="text" name="nw_product_alts[]" value="">
            <div class="nw-repeater__actions">
              <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
            </div>
          </div>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Features', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="product_features" data-name="nw_product_features">
          <p class="nw-admin-empty"<?php echo $features ? ' style="display:none"' : ''; ?>><?php esc_html_e('No features yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($features as $i => $feature) : ?>
            <div class="nw-repeater__row nw-repeater__row--simple" data-index="<?php echo esc_attr((string) $i); ?>">
              <input class="widefat" type="text" name="nw_product_features[]" value="<?php echo esc_attr($feature); ?>">
              <div class="nw-repeater__actions">
                <button type="button" class="button-link-delete" data-nw-repeater-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="product_features" data-template="nw-tpl-feature"><?php esc_html_e('Add feature', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-feature">
          <div class="nw-repeater__row nw-repeater__row--simple" data-index="__INDEX__">
            <input class="widefat" type="text" name="nw_product_features[]" value="">
            <div class="nw-repeater__actions">
              <button type="button" class="button-link-delete" data-nw-repeater-remove><?php echo esc_html__('Remove', 'nw-fuel'); ?></button>
            </div>
          </div>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Specifications', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Do not add Product Code here if you want it hidden — use the Product Code field above.', 'nw-fuel'); ?></p>
        <div class="nw-repeater" data-nw-repeater="product_specs" data-name="nw_product_specs">
          <p class="nw-admin-empty"<?php echo $specs ? ' style="display:none"' : ''; ?>><?php esc_html_e('No specifications yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($specs as $i => $spec) :
                if (! is_array($spec)) {
                    continue;
                }
                nw_fuel_render_spec_row((int) $i, $spec);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="product_specs" data-template="nw-tpl-spec"><?php esc_html_e('Add specification', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-spec">
          <?php nw_fuel_render_spec_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Product FAQs', 'nw-fuel'); ?></h3>
        <div class="nw-repeater" data-nw-repeater="product_faqs" data-name="nw_product_faqs">
          <p class="nw-admin-empty"<?php echo $faqs ? ' style="display:none"' : ''; ?>><?php esc_html_e('No FAQs yet.', 'nw-fuel'); ?></p>
          <div class="nw-repeater__rows">
            <?php foreach ($faqs as $i => $faq) :
                if (! is_array($faq)) {
                    continue;
                }
                nw_fuel_render_product_faq_row((int) $i, $faq);
            endforeach; ?>
          </div>
          <button type="button" class="button" data-nw-repeater-add="product_faqs" data-template="nw-tpl-product-faq"><?php esc_html_e('Add FAQ', 'nw-fuel'); ?></button>
        </div>
        <template id="nw-tpl-product-faq">
          <?php nw_fuel_render_product_faq_row('__INDEX__', []); ?>
        </template>
      </div>

      <div class="nw-admin-panel">
        <h3 class="nw-admin-panel__title"><?php esc_html_e('Related Products', 'nw-fuel'); ?></h3>
        <p class="nw-admin-panel__help"><?php esc_html_e('Search by product name, part number, or alternate, then add the products you want to show as related.', 'nw-fuel'); ?></p>
        <div class="nw-related-picker" data-nw-related-picker data-product-id="<?php echo esc_attr((string) $post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('nw_fuel_related_products')); ?>">
          <input type="hidden" name="nw_related_product_slugs_json" value="<?php echo esc_attr((string) wp_json_encode(array_values($related))); ?>" data-nw-related-value>
          <label class="screen-reader-text" for="nw-related-product-search"><?php esc_html_e('Search related products', 'nw-fuel'); ?></label>
          <div class="nw-related-picker__search">
            <input class="widefat" type="search" id="nw-related-product-search" placeholder="<?php esc_attr_e('Search name, part number, or alternate…', 'nw-fuel'); ?>" autocomplete="off" data-nw-related-search>
            <span class="spinner" data-nw-related-spinner></span>
          </div>
          <div class="nw-related-picker__results" data-nw-related-results hidden></div>
          <p class="nw-related-picker__status" data-nw-related-status aria-live="polite"></p>

          <h4 class="nw-related-picker__selected-title"><?php esc_html_e('Selected related products', 'nw-fuel'); ?></h4>
          <p class="nw-admin-empty" data-nw-related-empty<?php echo $selected_related_products ? ' style="display:none"' : ''; ?>><?php esc_html_e('No related products selected.', 'nw-fuel'); ?></p>
          <div class="nw-related-picker__selected" data-nw-related-selected>
            <?php foreach ($selected_related_products as $product_post) :
                $slug          = (string) $product_post->post_name;
                $related_row   = nw_fuel_product_search_suggestion((int) $product_post->ID);
                $related_part  = (string) ($related_row['part'] ?? nw_fuel_get_part_number((int) $product_post->ID));
                $related_image = (string) ($related_row['image'] ?? '');
                ?>
            <div class="nw-related-picker__item" data-related-slug="<?php echo esc_attr($slug); ?>">
              <?php if ($related_image !== '') : ?><img class="nw-related-picker__thumb" src="<?php echo esc_url($related_image); ?>" alt="" loading="lazy"><?php endif; ?>
              <span class="nw-related-picker__item-copy">
                <strong><?php echo esc_html(get_the_title($product_post)); ?></strong>
                <?php if ($related_part !== '') : ?><code><?php echo esc_html($related_part); ?></code><?php endif; ?>
              </span>
              <button type="button" class="button-link-delete" data-nw-related-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
}

/**
 * One product photo slot (attachment ID).
 */
function nw_fuel_render_product_photo_item(int $attachment_id, string $url = ''): void
{
    if ($url === '' && $attachment_id > 0) {
        $url = (string) (wp_get_attachment_image_url($attachment_id, 'medium') ?: wp_get_attachment_image_url($attachment_id, 'full') ?: '');
    }
    ?>
    <div class="nw-product-photos__item">
      <img class="nw-product-photos__thumb" src="<?php echo esc_attr($url); ?>" alt="">
      <input type="hidden" name="nw_product_photo_ids[]" value="<?php echo esc_attr($attachment_id > 0 ? (string) $attachment_id : '__ID__'); ?>">
      <span class="nw-product-photos__badge"><?php esc_html_e('Main', 'nw-fuel'); ?></span>
      <div class="nw-product-photos__item-actions">
        <button type="button" class="button-link" data-nw-product-photos-move="up"><?php esc_html_e('Up', 'nw-fuel'); ?></button>
        <button type="button" class="button-link" data-nw-product-photos-move="down"><?php esc_html_e('Down', 'nw-fuel'); ?></button>
        <button type="button" class="button-link-delete" data-nw-product-photos-remove><?php esc_html_e('Remove', 'nw-fuel'); ?></button>
      </div>
    </div>
    <?php
}

/**
 * Spec row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $spec
 */
function nw_fuel_render_spec_row(int|string $index, array $spec): void
{
    $prefix = 'nw_product_specs[' . $index . ']';
    ?>
    <div class="nw-repeater__row" data-index="<?php echo esc_attr((string) $index); ?>">
      <?php nw_fuel_admin_row_actions(__('Spec', 'nw-fuel')); ?>
      <div class="nw-admin-grid nw-admin-grid--2">
        <?php
        nw_fuel_admin_field([
            'label' => __('Label', 'nw-fuel'),
            'name'  => $prefix . '[label]',
            'value' => (string) ($spec['label'] ?? ''),
        ]);
        nw_fuel_admin_field([
            'label' => __('Value', 'nw-fuel'),
            'name'  => $prefix . '[value]',
            'value' => (string) ($spec['value'] ?? ''),
        ]);
        ?>
      </div>
    </div>
    <?php
}

/**
 * Product FAQ row.
 *
 * @param int|string           $index
 * @param array<string, mixed> $faq
 */
function nw_fuel_render_product_faq_row(int|string $index, array $faq): void
{
    $prefix = 'nw_product_faqs[' . $index . ']';
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
          'rows'  => 3,
      ]);
      ?>
    </div>
    <?php
}

/**
 * Save product CRUD fields.
 */
function nw_fuel_save_product_admin_fields(int $post_id): void
{
    $parse_money = static function (string $value): string {
        if (function_exists('nw_fuel_inventory_parse_money')) {
            return nw_fuel_inventory_parse_money($value);
        }
        $value = str_replace([',', '$'], '', trim($value));
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';
    };

    update_post_meta($post_id, '_nw_part_number', sanitize_text_field(wp_unslash($_POST['_nw_part_number'] ?? '')));
    update_post_meta($post_id, '_nw_product_code', sanitize_text_field(wp_unslash($_POST['_nw_product_code'] ?? '')));

    nw_fuel_save_product_brand($post_id);
    update_post_meta($post_id, '_nw_retail', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_retail'] ?? ''))));
    update_post_meta($post_id, '_nw_trade_total', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_trade_total'] ?? ''))));
    update_post_meta($post_id, '_nw_special1_total', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_special1_total'] ?? ''))));
    update_post_meta($post_id, '_nw_special2_total', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_special2_total'] ?? ''))));
    update_post_meta($post_id, '_nw_special3_total', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_special3_total'] ?? ''))));
    update_post_meta($post_id, '_nw_cost_p_core', $parse_money(sanitize_text_field(wp_unslash($_POST['_nw_cost_p_core'] ?? ''))));
    update_post_meta($post_id, '_nw_short_description', sanitize_textarea_field(wp_unslash($_POST['_nw_short_description'] ?? '')));
    nw_fuel_save_product_photos($post_id);
    if (! function_exists('nw_fuel_rank_math_active') || ! nw_fuel_rank_math_active()) {
        update_post_meta($post_id, '_nw_meta_title', sanitize_text_field(wp_unslash($_POST['_nw_meta_title'] ?? '')));
        update_post_meta($post_id, '_nw_meta_description', sanitize_textarea_field(wp_unslash($_POST['_nw_meta_description'] ?? '')));
    }

    $vehicles = [];
    foreach ((array) wp_unslash($_POST['nw_product_vehicles'] ?? []) as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $vehicles[] = $item;
        }
    }
    update_post_meta($post_id, '_nw_compatible_vehicles', nw_fuel_json_encode_meta($vehicles));

    $alts = [];
    foreach ((array) wp_unslash($_POST['nw_product_alts'] ?? []) as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $alts[] = $item;
        }
    }
    $alts = array_values(array_unique($alts));
    update_post_meta($post_id, '_nw_secondary_ids', nw_fuel_json_encode_meta($alts));
    update_post_meta($post_id, '_nw_search_alts', implode(' ', $alts));

    $features = [];
    foreach ((array) wp_unslash($_POST['nw_product_features'] ?? []) as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $features[] = $item;
        }
    }
    update_post_meta($post_id, '_nw_features', nw_fuel_json_encode_meta($features));

    $specs = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_product_specs'] ?? []),
        static function (array $row): ?array {
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            $value = sanitize_text_field((string) ($row['value'] ?? ''));
            if ($label === '' && $value === '') {
                return null;
            }
            return ['label' => $label, 'value' => $value];
        }
    );
    update_post_meta($post_id, '_nw_specifications', nw_fuel_json_encode_meta($specs));

    $faqs = nw_fuel_sanitize_repeater_rows(
        wp_unslash($_POST['nw_product_faqs'] ?? []),
        static function (array $row): ?array {
            $q = sanitize_text_field((string) ($row['question'] ?? ''));
            $a = sanitize_textarea_field((string) ($row['answer'] ?? ''));
            if ($q === '' && $a === '') {
                return null;
            }
            return ['question' => $q, 'answer' => $a];
        }
    );
    update_post_meta($post_id, '_nw_product_faqs', nw_fuel_json_encode_meta($faqs));

    $related_json = (string) wp_unslash($_POST['nw_related_product_slugs_json'] ?? '');
    $related_input = json_decode($related_json, true);
    if (! is_array($related_input)) {
        $related_input = (array) wp_unslash($_POST['nw_related_product_slugs'] ?? []);
    }

    $related = [];
    foreach ($related_input as $slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug !== '') {
            $related[] = $slug;
        }
    }
    update_post_meta($post_id, '_nw_related_slugs', nw_fuel_json_encode_meta(array_values(array_unique($related))));
}

/**
 * Assign Attributes → Brand from the product admin picker.
 */
function nw_fuel_save_product_brand(int $post_id): void
{
    if (! isset($_POST['nw_product_brand']) && ! isset($_POST['nw_product_brand_new'])) {
        return;
    }

    $brand_new = sanitize_text_field(wp_unslash($_POST['nw_product_brand_new'] ?? ''));
    $brand     = $brand_new !== '' ? $brand_new : sanitize_text_field(wp_unslash($_POST['nw_product_brand'] ?? ''));
    if (function_exists('nw_fuel_assign_inventory_brand')) {
        nw_fuel_assign_inventory_brand($post_id, $brand);
        return;
    }
    if (! taxonomy_exists('pa_brand')) {
        return;
    }
    if ($brand === '') {
        wp_set_object_terms($post_id, [], 'pa_brand', false);
    } else {
        if (! term_exists($brand, 'pa_brand')) {
            wp_insert_term($brand, 'pa_brand');
        }
        wp_set_object_terms($post_id, [$brand], 'pa_brand', false);
    }
    update_post_meta($post_id, '_nw_price_book', $brand);
}

/**
 * Save featured image + Woo gallery from the 1–5 photo picker.
 */
function nw_fuel_save_product_photos(int $post_id): void
{
    if (! isset($_POST['nw_product_photos_present'])) {
        return;
    }

    $max = defined('NW_FUEL_PRODUCT_PHOTOS_MAX') ? NW_FUEL_PRODUCT_PHOTOS_MAX : 5;
    $ids = [];
    foreach ((array) wp_unslash($_POST['nw_product_photo_ids'] ?? []) as $raw) {
        $id = (int) $raw;
        if ($id <= 0 || in_array($id, $ids, true)) {
            continue;
        }
        if (get_post_type($id) !== 'attachment') {
            continue;
        }
        $ids[] = $id;
        if (count($ids) >= $max) {
            break;
        }
    }

    if ($ids === []) {
        delete_post_thumbnail($post_id);
        delete_post_meta($post_id, '_thumbnail_id');
        delete_post_meta($post_id, '_product_image_gallery');
        return;
    }

    set_post_thumbnail($post_id, $ids[0]);
    update_post_meta($post_id, '_product_image_gallery', implode(',', array_slice($ids, 1)));
}
