<?php
/**
 * Admin “Content Guide” — where to edit every word.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'nw_fuel_register_content_guide_page');

/**
 * Register Content Guide under Appearance.
 */
function nw_fuel_register_content_guide_page(): void
{
    add_theme_page(
        __('Content Guide', 'nw-fuel'),
        __('Content Guide', 'nw-fuel'),
        'edit_theme_options',
        'nw-fuel-content-guide',
        'nw_fuel_render_content_guide_page'
    );
}

/**
 * Render the guide.
 */
function nw_fuel_render_content_guide_page(): void
{
    if (! current_user_can('edit_theme_options')) {
        return;
    }

    $rows = [
        [
            __('Homepage', 'nw-fuel'),
            __('Appearance → Customize → NW Fuel Homepage', 'nw-fuel'),
            admin_url('customize.php?autofocus[section]=nw_fuel_homepage'),
        ],
        [
            __('Phone, email, address, hours, footer text, social', 'nw-fuel'),
            __('Appearance → Customize → NW Fuel Business Info', 'nw-fuel'),
            admin_url('customize.php?autofocus[section]=nw_fuel_business'),
        ],
        [
            __('About page (every section)', 'nw-fuel'),
            __('Pages → About Us → “About Page Content”', 'nw-fuel'),
            admin_url('edit.php?post_type=page'),
        ],
        [
            __('History of NW Fuel page', 'nw-fuel'),
            __('Pages → History of NW Fuel → hero/CTA. Timeline stories are in the theme.', 'nw-fuel'),
            admin_url('edit.php?post_type=page'),
        ],
        [
            __('Contact page (every section + FAQs)', 'nw-fuel'),
            __('Pages → Contact Us → “Contact Page Content”', 'nw-fuel'),
            admin_url('edit.php?post_type=page'),
        ],
        [
            __('Services listing / service detail', 'nw-fuel'),
            __('Services CPT (listing heroes also in Customize → Archives)', 'nw-fuel'),
            admin_url('edit.php?post_type=nw_service'),
        ],
        [
            __('Products catalog / product detail', 'nw-fuel'),
            __('Products + “NW Fuel Product Details”', 'nw-fuel'),
            admin_url('edit.php?post_type=product'),
        ],
        [
            __('Technical pages', 'nw-fuel'),
            __('Technical Pages CPT', 'nw-fuel'),
            admin_url('edit.php?post_type=nw_tech_resource'),
        ],
        [
            __('Blog posts', 'nw-fuel'),
            __('Posts', 'nw-fuel'),
            admin_url('edit.php'),
        ],
        [
            __('Gallery photos and videos', 'nw-fuel'),
            __('Gallery → Add New. Title, type, image or video link.', 'nw-fuel'),
            admin_url('edit.php?post_type=nw_gallery_item'),
        ],
        [
            __('Catalog PDFs', 'nw-fuel'),
            __('Catalog → Add New. Title, PDF, short description. Download emails are under Catalog → Download Emails.', 'nw-fuel'),
            admin_url('edit.php?post_type=nw_catalog_item'),
        ],
        [
            __('Header & footer menus', 'nw-fuel'),
            __('Appearance → Menus', 'nw-fuel'),
            admin_url('nav-menus.php'),
        ],
        [
            __('Archive heroes (Services / Products / Tech / Blog)', 'nw-fuel'),
            __('Appearance → Customize → NW Fuel Archives', 'nw-fuel'),
            admin_url('customize.php?autofocus[section]=nw_fuel_archives'),
        ],
        [
            __('SEO titles & descriptions', 'nw-fuel'),
            __('Each page/post/product → NW Fuel SEO fields', 'nw-fuel'),
            '',
        ],
    ];
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('NW Fuel — Where to Edit Content', 'nw-fuel'); ?></h1>
      <p><?php esc_html_e('Use this map so you never need to edit theme PHP for normal copy changes.', 'nw-fuel'); ?></p>
      <table class="widefat striped" style="max-width:960px;">
        <thead>
          <tr>
            <th><?php esc_html_e('What you want to change', 'nw-fuel'); ?></th>
            <th><?php esc_html_e('Where in admin', 'nw-fuel'); ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as [$what, $where, $link]) : ?>
          <tr>
            <td><strong><?php echo esc_html($what); ?></strong></td>
            <td><?php echo esc_html($where); ?></td>
            <td><?php if ($link) : ?><a class="button button-small" href="<?php echo esc_url($link); ?>"><?php esc_html_e('Open', 'nw-fuel'); ?></a><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
