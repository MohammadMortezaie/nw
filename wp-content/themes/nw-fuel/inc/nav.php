<?php
/**
 * Navigation menus — walkers preserve static header/footer markup.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'nw_fuel_filter_primary_nav_items', 15, 2);

/**
 * Navbar only: About, Contact, no Blog.
 *
 * @param list<object> $items
 * @param stdClass     $args
 * @return list<object>
 */
function nw_fuel_filter_primary_nav_items(array $items, $args): array
{
    $location = is_object($args) ? (string) ($args->theme_location ?? '') : '';
    if ($location !== 'primary') {
        return $items;
    }

    $blog_id = (int) get_option('page_for_posts');
    $out     = [];

    foreach ($items as $item) {
        $title     = trim((string) ($item->title ?? ''));
        $url       = (string) ($item->url ?? '');
        $object_id = (int) ($item->object_id ?? 0);

        if (strcasecmp($title, 'Blog') === 0) {
            continue;
        }
        if ($blog_id > 0 && $object_id === $blog_id) {
            continue;
        }
        if (preg_match('~/blog/?$~i', $url)) {
            continue;
        }

        if (strcasecmp($title, 'About Us') === 0) {
            $item->title = __('About', 'nw-fuel');
        }
        if (strcasecmp($title, 'Contact Us') === 0) {
            $item->title = __('Contact', 'nw-fuel');
        }

        $out[] = $item;
    }

    return $out;
}

/**
 * Whether the primary menu has items assigned.
 */
function nw_fuel_has_primary_menu(): bool
{
    return has_nav_menu('primary');
}

/**
 * Whether the footer menu has items assigned.
 */
function nw_fuel_has_footer_menu(): bool
{
    return has_nav_menu('footer');
}

/**
 * Render desktop primary navigation (inside .site-nav__desktop).
 */
function nw_fuel_render_primary_nav_desktop(): void
{
    if (nw_fuel_has_primary_menu()) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'depth'          => 2,
            'fallback_cb'    => false,
            'walker'         => new NW_Fuel_Primary_Nav_Walker('desktop'),
        ]);
        nw_fuel_render_gallery_nav_link('desktop');
        nw_fuel_render_catalog_nav_link('desktop');
        return;
    }

    nw_fuel_render_primary_nav_fallback('desktop');
}

/**
 * Render mobile primary navigation (inside #mobile-menu, after search).
 */
function nw_fuel_render_primary_nav_mobile(): void
{
    if (nw_fuel_has_primary_menu()) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'depth'          => 2,
            'fallback_cb'    => false,
            'walker'         => new NW_Fuel_Primary_Nav_Walker('mobile'),
        ]);
        nw_fuel_render_gallery_nav_link('mobile');
        nw_fuel_render_catalog_nav_link('mobile');
        return;
    }

    nw_fuel_render_primary_nav_fallback('mobile');
}

/**
 * Fallback primary nav matching the static site when no WP menu is assigned.
 *
 * @param 'desktop'|'mobile' $context
 */
function nw_fuel_render_primary_nav_fallback(string $context): void
{
    $nav                 = nw_fuel_nav_state();
    $business            = nw_fuel_business();
    $services            = nw_fuel_services();
    $technical_resources = nw_fuel_technical_resources();

    $products_menu = [
        ['label' => __('All Products', 'nw-fuel'), 'href' => nw_fuel_products_url()],
        ['label' => __('Search by Part Number', 'nw-fuel'), 'href' => nw_fuel_products_url() . '#filter-search'],
    ];

    $nav_seed = nw_fuel_seed_json_safe('nav.json');
    $services_menu = [];
    if (! empty($nav_seed['servicesMenu']) && is_array($nav_seed['servicesMenu'])) {
        foreach ($nav_seed['servicesMenu'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $href = (string) ($item['href'] ?? '');
            if (str_starts_with($href, '/')) {
                $href = home_url($href);
            }
            $services_menu[] = [
                'label' => (string) ($item['label'] ?? ''),
                'href'  => $href,
                'all'   => ! empty($item['all']),
            ];
        }
    }
    if ($services_menu === []) {
        foreach ($services as $service_post) {
            $services_menu[] = [
                'label' => get_the_title($service_post),
                'href'  => get_permalink($service_post),
            ];
        }
        $services_menu[] = [
            'label' => __('See All Services', 'nw-fuel'),
            'href'  => get_post_type_archive_link('nw_service'),
            'all'   => true,
        ];
    }

    $technical_menu = array_map(static function (WP_Post $resource): array {
        return [
            'label' => get_the_title($resource),
            'href'  => get_permalink($resource),
        ];
    }, $technical_resources);

    if ($context === 'desktop') {
        ?>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-link<?php echo is_front_page() ? ' is-active' : ''; ?>"><?php esc_html_e('Home', 'nw-fuel'); ?></a>

        <div class="nav-dropdown">
          <a href="<?php echo esc_url(nw_fuel_products_url()); ?>" class="nav-link<?php echo $nav['products_active'] ? ' is-active' : ''; ?>"><?php esc_html_e('Products', 'nw-fuel'); ?> ▾</a>
          <div class="nav-dropdown__panel">
            <?php foreach ($products_menu as $item) : ?>
            <a href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['label']); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="nav-dropdown">
          <a href="<?php echo esc_url(get_post_type_archive_link('nw_service')); ?>" class="nav-link<?php echo $nav['services_active'] ? ' is-active' : ''; ?>"><?php esc_html_e('Services', 'nw-fuel'); ?> ▾</a>
          <div class="nav-dropdown__panel nav-dropdown__panel--wide">
            <?php foreach ($services_menu as $item) : ?>
            <a href="<?php echo esc_url($item['href']); ?>"<?php echo ! empty($item['all']) ? ' class="nav-dropdown__all"' : ''; ?>><?php echo esc_html($item['label']); ?><?php echo ! empty($item['all']) ? ' →' : ''; ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="nav-dropdown">
          <a href="<?php echo esc_url(get_post_type_archive_link('nw_tech_resource')); ?>" class="nav-link<?php echo $nav['technical_active'] ? ' is-active' : ''; ?>"><?php esc_html_e('Technical Resources', 'nw-fuel'); ?> ▾</a>
          <div class="nav-dropdown__panel nav-dropdown__panel--wide">
            <?php foreach ($technical_menu as $item) : ?>
            <a href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['label']); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <a href="<?php echo esc_url(nw_fuel_page_url('about')); ?>" class="nav-link<?php echo nw_fuel_is_about_context() ? ' is-active' : ''; ?>"><?php esc_html_e('About', 'nw-fuel'); ?></a>
        <?php if (nw_fuel_user_can_see_gallery()) : ?>
        <a href="<?php echo esc_url(get_post_type_archive_link('nw_gallery_item') ?: home_url('/gallery/')); ?>" class="nav-link<?php echo $nav['gallery_active'] ? ' is-active' : ''; ?>"><?php esc_html_e('Gallery', 'nw-fuel'); ?></a>
        <?php endif; ?>
        <?php if (nw_fuel_user_can_see_catalog()) : ?>
        <a href="<?php echo esc_url(nw_fuel_catalog_url()); ?>" class="nav-link<?php echo $nav['catalog_active'] ? ' is-active' : ''; ?>"><?php esc_html_e('Catalog', 'nw-fuel'); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>" class="nav-link<?php echo is_page('contact') ? ' is-active' : ''; ?>"><?php esc_html_e('Contact', 'nw-fuel'); ?></a>
        <?php
        return;
    }

    // Mobile fallback.
    ?>
    <a href="<?php echo esc_url(home_url('/')); ?>"<?php echo is_front_page() ? ' class="is-active"' : ''; ?>><?php esc_html_e('Home', 'nw-fuel'); ?></a>

    <div class="mobile-menu__group" data-mobile-submenu>
      <button type="button" class="mobile-menu__toggle<?php echo $nav['products_active'] ? ' is-active' : ''; ?>" aria-expanded="false">
        <?php esc_html_e('Products', 'nw-fuel'); ?> <span class="mobile-menu__chevron" aria-hidden="true"></span>
      </button>
      <div class="mobile-menu__sub" hidden>
        <?php foreach ($products_menu as $item) : ?>
        <a href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['label']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mobile-menu__group" data-mobile-submenu>
      <button type="button" class="mobile-menu__toggle<?php echo $nav['services_active'] ? ' is-active' : ''; ?>" aria-expanded="false">
        <?php esc_html_e('Services', 'nw-fuel'); ?> <span class="mobile-menu__chevron" aria-hidden="true"></span>
      </button>
      <div class="mobile-menu__sub" hidden>
        <?php foreach ($services_menu as $item) : ?>
        <a href="<?php echo esc_url($item['href']); ?>"<?php echo ! empty($item['all']) ? ' class="mobile-menu__all"' : ''; ?>><?php echo esc_html($item['label']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mobile-menu__group" data-mobile-submenu>
      <button type="button" class="mobile-menu__toggle<?php echo $nav['technical_active'] ? ' is-active' : ''; ?>" aria-expanded="false">
        <?php esc_html_e('Technical Resources', 'nw-fuel'); ?> <span class="mobile-menu__chevron" aria-hidden="true"></span>
      </button>
      <div class="mobile-menu__sub" hidden>
        <?php foreach ($technical_menu as $item) : ?>
        <a href="<?php echo esc_url($item['href']); ?>"><?php echo esc_html($item['label']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <a href="<?php echo esc_url(nw_fuel_page_url('about')); ?>"<?php echo nw_fuel_is_about_context() ? ' class="is-active"' : ''; ?>><?php esc_html_e('About', 'nw-fuel'); ?></a>
    <?php if (nw_fuel_user_can_see_gallery()) : ?>
    <a href="<?php echo esc_url(get_post_type_archive_link('nw_gallery_item') ?: home_url('/gallery/')); ?>"<?php echo $nav['gallery_active'] ? ' class="is-active"' : ''; ?>><?php esc_html_e('Gallery', 'nw-fuel'); ?></a>
    <?php endif; ?>
    <?php if (nw_fuel_user_can_see_catalog()) : ?>
    <a href="<?php echo esc_url(nw_fuel_catalog_url()); ?>"<?php echo $nav['catalog_active'] ? ' class="is-active"' : ''; ?>><?php esc_html_e('Catalog', 'nw-fuel'); ?></a>
    <?php endif; ?>
    <a href="<?php echo esc_url(nw_fuel_page_url('contact')); ?>"<?php echo is_page('contact') ? ' class="is-active"' : ''; ?>><?php esc_html_e('Contact', 'nw-fuel'); ?></a>
    <a href="<?php echo esc_url(nw_fuel_phone_href($business['phone'] ?? '')); ?>" class="mobile-menu__phone"><?php echo esc_html($business['phone'] ?? ''); ?></a>
    <?php
}

/**
 * Safe seed JSON loader (nav may load before importer helpers in edge cases).
 *
 * @return array<string, mixed>
 */
function nw_fuel_seed_json_safe(string $filename): array
{
    if (function_exists('nw_fuel_seed_json')) {
        return nw_fuel_seed_json($filename);
    }
    $path = NW_FUEL_DIR . '/seed-data/' . $filename;
    if (! file_exists($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/**
 * Primary nav walker — outputs static-site markup (no <ul>/<li>).
 */
class NW_Fuel_Primary_Nav_Walker extends Walker_Nav_Menu
{
    private string $context;

    /** @var array<int, bool> */
    private array $parent_has_children = [];

    /** @var array<string, mixed>|null */
    private $current_parent = null;

    public function __construct(string $context = 'desktop')
    {
        $this->context = $context === 'mobile' ? 'mobile' : 'desktop';
    }

    /**
     * @param mixed               $element
     * @param array<int, mixed>   $children_elements
     * @param mixed               $max_depth
     * @param mixed               $depth
     * @param mixed               $args
     * @param string              $output
     */
    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output): void
    {
        if ($element) {
            $this->parent_has_children[(int) $element->ID] = ! empty($children_elements[$element->ID]);
        }
        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
    }

    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        if ($this->context === 'mobile') {
            $output .= '<div class="mobile-menu__sub" hidden>';
            return;
        }
        $classes = is_array($this->current_parent->classes ?? null) ? $this->current_parent->classes : [];
        $wide    = in_array('dropdown-wide', $classes, true) ? ' nav-dropdown__panel--wide' : '';
        $output .= '<div class="nav-dropdown__panel' . $wide . '">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void
    {
        $output .= '</div>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        $title = apply_filters('the_title', $item->title, $item->ID);
        $url   = $item->url ?: '#';
        $classes = is_array($item->classes) ? $item->classes : [];
        $is_all  = in_array('nav-all', $classes, true) || in_array('all', $classes, true)
            || stripos((string) $title, 'see all') !== false;
        $is_active = in_array('current-menu-item', $classes, true)
            || in_array('current-menu-ancestor', $classes, true)
            || in_array('current-menu-parent', $classes, true);
        $has_children = ! empty($this->parent_has_children[(int) $item->ID]);

        if ($depth === 0) {
            $this->current_parent = $item;
            if ($this->context === 'mobile') {
                if ($has_children) {
                    $output .= '<div class="mobile-menu__group" data-mobile-submenu>';
                    $output .= '<button type="button" class="mobile-menu__toggle' . ($is_active ? ' is-active' : '') . '" aria-expanded="false">';
                    $output .= esc_html($title) . ' <span class="mobile-menu__chevron" aria-hidden="true"></span>';
                    $output .= '</button>';
                } else {
                    $output .= '<a href="' . esc_url($url) . '"' . ($is_active ? ' class="is-active"' : '') . '>' . esc_html($title) . '</a>';
                }
                return;
            }

            if ($has_children) {
                $output .= '<div class="nav-dropdown">';
                $output .= '<a href="' . esc_url($url) . '" class="nav-link' . ($is_active ? ' is-active' : '') . '">' . esc_html($title) . ' ▾</a>';
            } else {
                $output .= '<a href="' . esc_url($url) . '" class="nav-link' . ($is_active ? ' is-active' : '') . '">' . esc_html($title) . '</a>';
            }
            return;
        }

        // Child items.
        if ($this->context === 'mobile') {
            $output .= '<a href="' . esc_url($url) . '"' . ($is_all ? ' class="mobile-menu__all"' : '') . '>' . esc_html($title) . '</a>';
            return;
        }

        $class = $is_all ? ' class="nav-dropdown__all"' : '';
        $arrow = $is_all ? ' →' : '';
        $output .= '<a href="' . esc_url($url) . '"' . $class . '>' . esc_html($title) . $arrow . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void
    {
        if ($depth === 0 && ! empty($this->parent_has_children[(int) $item->ID])) {
            if ($this->context === 'mobile') {
                $output .= '</div>'; // .mobile-menu__group
            } else {
                $output .= '</div>'; // .nav-dropdown
            }
        }
    }
}

/**
 * Footer walker — standard <li><a> list matching site-footer markup.
 */
class NW_Fuel_Footer_Nav_Walker extends Walker_Nav_Menu
{
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        $title = apply_filters('the_title', $item->title, $item->ID);
        $url   = $item->url ?: '#';
        $classes = is_array($item->classes) ? $item->classes : [];
        $is_all  = in_array('nav-all', $classes, true) || in_array('all', $classes, true)
            || stripos((string) $title, 'all services') !== false;

        $output .= '<li><a href="' . esc_url($url) . '"' . ($is_all ? ' class="footer-link-accent"' : '') . '>'
            . esc_html($title) . ($is_all ? ' →' : '') . '</a></li>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void
    {
        // li closed in start_el.
    }
}
