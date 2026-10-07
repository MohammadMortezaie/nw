<?php
/**
 * Partners — separate from WordPress users. Frontend login + discount levels.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_PARTNER_COOKIE = 'nw_fuel_partner';

add_action('init', 'nw_fuel_register_partner_post_type');
add_action('admin_menu', 'nw_fuel_register_partners_page');
add_action('admin_post_nw_fuel_add_partner', 'nw_fuel_handle_add_partner');
add_action('admin_post_nw_fuel_save_partner', 'nw_fuel_handle_save_partner');
add_action('admin_post_nw_fuel_remove_partner', 'nw_fuel_handle_remove_partner');
add_action('admin_post_nopriv_nw_fuel_partner_login', 'nw_fuel_handle_partner_login');
add_action('admin_post_nw_fuel_partner_login', 'nw_fuel_handle_partner_login');
add_action('admin_post_nopriv_nw_fuel_partner_logout', 'nw_fuel_handle_partner_logout');
add_action('admin_post_nw_fuel_partner_logout', 'nw_fuel_handle_partner_logout');
add_action('send_headers', 'nw_fuel_partner_nocache_headers');
add_action('template_redirect', 'nw_fuel_partner_disable_page_cache');

function nw_fuel_register_partner_post_type(): void
{
    register_post_type('nw_partner', [
        'labels'             => [
            'name'          => __('Partners', 'nw-fuel'),
            'singular_name' => __('Partner', 'nw-fuel'),
        ],
        'public'             => false,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'show_in_rest'       => false,
        'supports'           => ['title'],
        'capability_type'    => 'post',
        'exclude_from_search'=> true,
        'rewrite'            => false,
    ]);
}

/**
 * @return array<int, string>
 */
function nw_fuel_discount_level_choices(): array
{
    return [
        1 => __('Level 1 — column I', 'nw-fuel'),
        2 => __('Level 2 — column J', 'nw-fuel'),
        3 => __('Level 3 — column K', 'nw-fuel'),
        4 => __('Level 4 — column L', 'nw-fuel'),
    ];
}

function nw_fuel_register_partners_page(): void
{
    add_menu_page(
        __('Partners', 'nw-fuel'),
        __('Partners', 'nw-fuel'),
        'manage_options',
        'nw-fuel-partners',
        'nw_fuel_render_partners_page',
        'dashicons-groups',
        31
    );
}

/**
 * @return array<int, array{id:int,name:string,email:string,level:int}>
 */
function nw_fuel_partner_records(): array
{
    $posts = get_posts([
        'post_type'      => 'nw_partner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $out = [];
    foreach ($posts as $post) {
        $out[] = [
            'id'    => $post->ID,
            'name'  => $post->post_title,
            'email' => (string) get_post_meta($post->ID, '_nw_partner_email', true),
            'level' => max(1, min(4, (int) get_post_meta($post->ID, '_nw_partner_level', true))),
        ];
    }

    return $out;
}

function nw_fuel_find_partner_id_by_email(string $email): int
{
    $email = strtolower(trim($email));
    if ($email === '' || ! is_email($email)) {
        return 0;
    }

    $q = new WP_Query([
        'post_type'      => 'nw_partner',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_nw_partner_email',
                'value' => $email,
            ],
        ],
        'no_found_rows'  => true,
    ]);

    return $q->posts !== [] ? (int) $q->posts[0] : 0;
}

/**
 * @return array{id:int,name:string,email:string,level:int}|null
 */
function nw_fuel_current_partner(): ?array
{
    static $cached = false;
    static $partner = null;

    if ($cached !== false) {
        return $partner;
    }
    $cached = true;

    $raw = isset($_COOKIE[NW_FUEL_PARTNER_COOKIE]) ? (string) $_COOKIE[NW_FUEL_PARTNER_COOKIE] : '';
    if ($raw === '' || ! str_contains($raw, ':')) {
        return null;
    }

    [$id, $token] = explode(':', $raw, 2);
    $id = absint($id);
    if ($id < 1 || ! hash_equals(nw_fuel_partner_token($id), $token)) {
        return null;
    }

    $post = get_post($id);
    if (! $post instanceof WP_Post || $post->post_type !== 'nw_partner' || $post->post_status !== 'publish') {
        return null;
    }

    $partner = [
        'id'    => $id,
        'name'  => $post->post_title,
        'email' => (string) get_post_meta($id, '_nw_partner_email', true),
        'level' => max(1, min(4, (int) get_post_meta($id, '_nw_partner_level', true))),
    ];

    return $partner;
}

function nw_fuel_current_partner_level(): int
{
    $partner = nw_fuel_current_partner();
    return $partner ? (int) $partner['level'] : 0;
}

function nw_fuel_partner_nocache_headers(): void
{
    if (nw_fuel_current_partner()) {
        nocache_headers();
    }
}

function nw_fuel_partner_disable_page_cache(): void
{
    if (nw_fuel_current_partner() && ! defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
}

function nw_fuel_partner_token(int $id): string
{
    return hash_hmac('sha256', (string) $id, wp_salt('logged_in'));
}

function nw_fuel_set_partner_session(int $id): void
{
    $value = $id . ':' . nw_fuel_partner_token($id);
    $expire = time() + 30 * DAY_IN_SECONDS;
    $path   = defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/';

    setcookie(NW_FUEL_PARTNER_COOKIE, $value, [
        'expires'  => $expire,
        'path'     => $path,
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[NW_FUEL_PARTNER_COOKIE] = $value;
}

function nw_fuel_clear_partner_session(): void
{
    $path = defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/';
    setcookie(NW_FUEL_PARTNER_COOKIE, '', [
        'expires'  => time() - HOUR_IN_SECONDS,
        'path'     => $path,
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[NW_FUEL_PARTNER_COOKIE]);
}

function nw_fuel_partners_redirect(array $args = []): void
{
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=nw-fuel-partners')));
    exit;
}

function nw_fuel_partner_return_url(): string
{
    $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash((string) $_SERVER['HTTP_HOST']) : '';
    $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
    if ($host === '') {
        return home_url('/');
    }

    return (is_ssl() ? 'https://' : 'http://') . $host . $uri;
}

function nw_fuel_partner_safe_redirect(string $url = ''): void
{
    wp_safe_redirect(wp_validate_redirect($url, home_url('/')));
    exit;
}

function nw_fuel_handle_add_partner(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_partner_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_partner_nonce'])), 'nw_fuel_add_partner')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $email = strtolower(sanitize_email(wp_unslash((string) ($_POST['partner_email'] ?? ''))));
    $name  = sanitize_text_field(wp_unslash((string) ($_POST['partner_name'] ?? '')));
    $pass  = (string) wp_unslash($_POST['partner_password'] ?? '');
    $level = absint($_POST['partner_level'] ?? 1);
    if ($level < 1 || $level > 4) {
        $level = 1;
    }

    if ($email === '' || ! is_email($email)) {
        nw_fuel_partners_redirect(['partner' => 'invalid']);
    }
    if (nw_fuel_find_partner_id_by_email($email) > 0) {
        nw_fuel_partners_redirect(['partner' => 'exists']);
    }

    if ($pass === '') {
        $pass = wp_generate_password(12, false);
    }

    $id = wp_insert_post([
        'post_type'   => 'nw_partner',
        'post_status' => 'publish',
        'post_title'  => $name !== '' ? $name : $email,
    ], true);

    if (is_wp_error($id)) {
        nw_fuel_partners_redirect(['partner' => 'error']);
    }

    update_post_meta((int) $id, '_nw_partner_email', $email);
    update_post_meta((int) $id, '_nw_partner_level', $level);
    update_post_meta((int) $id, '_nw_partner_pass', wp_hash_password($pass));

    set_transient('nw_fuel_partner_new_pass_' . get_current_user_id(), [
        'email'    => $email,
        'password' => $pass,
    ], 5 * MINUTE_IN_SECONDS);

    nw_fuel_partners_redirect(['partner' => 'created']);
}

function nw_fuel_handle_save_partner(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_partner_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_partner_nonce'])), 'nw_fuel_save_partner')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $id = absint($_POST['partner_id'] ?? 0);
    $post = get_post($id);
    if (! $post instanceof WP_Post || $post->post_type !== 'nw_partner') {
        nw_fuel_partners_redirect(['partner' => 'error']);
    }

    $level = absint($_POST['partner_level'] ?? 1);
    if ($level < 1 || $level > 4) {
        $level = 1;
    }
    update_post_meta($id, '_nw_partner_level', $level);

    $pass = (string) wp_unslash($_POST['partner_password'] ?? '');
    if (trim($pass) !== '') {
        update_post_meta($id, '_nw_partner_pass', wp_hash_password($pass));
    }

    nw_fuel_partners_redirect(['partner' => 'saved']);
}

function nw_fuel_handle_remove_partner(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }
    if (! isset($_POST['nw_fuel_partner_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_partner_nonce'])), 'nw_fuel_remove_partner')) {
        wp_die(esc_html__('Invalid nonce', 'nw-fuel'));
    }

    $id = absint($_POST['partner_id'] ?? 0);
    $post = get_post($id);
    if ($post instanceof WP_Post && $post->post_type === 'nw_partner') {
        wp_delete_post($id, true);
    }

    nw_fuel_partners_redirect(['partner' => 'removed']);
}

function nw_fuel_handle_partner_login(): void
{
    $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_POST['redirect_to'])) : home_url('/');
    if (! isset($_POST['nw_fuel_partner_login_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_partner_login_nonce'])), 'nw_fuel_partner_login')) {
        nw_fuel_partner_safe_redirect(add_query_arg('partner_login', 'error', $redirect));
    }

    $email = strtolower(sanitize_email(wp_unslash((string) ($_POST['partner_email'] ?? ''))));
    $pass  = (string) wp_unslash($_POST['partner_password'] ?? '');
    $id    = nw_fuel_find_partner_id_by_email($email);
    $hash  = $id > 0 ? (string) get_post_meta($id, '_nw_partner_pass', true) : '';

    if ($id < 1 || $hash === '' || ! wp_check_password($pass, $hash)) {
        nw_fuel_partner_safe_redirect(add_query_arg('partner_login', 'error', $redirect));
    }

    nw_fuel_set_partner_session($id);
    nw_fuel_partner_safe_redirect(remove_query_arg('partner_login', $redirect));
}

function nw_fuel_handle_partner_logout(): void
{
    $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_POST['redirect_to'])) : home_url('/');
    if (! isset($_POST['nw_fuel_partner_logout_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_partner_logout_nonce'])), 'nw_fuel_partner_logout')) {
        nw_fuel_partner_safe_redirect($redirect);
    }

    nw_fuel_clear_partner_session();
    nw_fuel_partner_safe_redirect($redirect);
}

function nw_fuel_render_partners_page(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized', 'nw-fuel'));
    }

    $status   = isset($_GET['partner']) ? sanitize_key((string) wp_unslash($_GET['partner'])) : '';
    $partners = nw_fuel_partner_records();
    $choices  = nw_fuel_discount_level_choices();
    $created  = get_transient('nw_fuel_partner_new_pass_' . get_current_user_id());
    if (is_array($created)) {
        delete_transient('nw_fuel_partner_new_pass_' . get_current_user_id());
    } else {
        $created = [];
    }

    $notices = [
        'created' => __('Partner added. They log in on the website, not in WordPress admin.', 'nw-fuel'),
        'saved'   => __('Partner saved.', 'nw-fuel'),
        'removed' => __('Partner deleted.', 'nw-fuel'),
        'invalid' => __('Enter a valid email address.', 'nw-fuel'),
        'exists'  => __('A partner with that email already exists.', 'nw-fuel'),
        'error'   => __('Could not save that partner.', 'nw-fuel'),
    ];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Partners', 'nw-fuel'); ?></h1>
        <p><?php esc_html_e('Partners are customers, not WordPress users. They log in with the icon in the website header. Their discount level controls which price they see (columns I–L).', 'nw-fuel'); ?></p>

        <?php if ($status !== '' && isset($notices[$status])) : ?>
        <div class="notice notice-<?php echo in_array($status, ['invalid', 'exists', 'error'], true) ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($notices[$status]); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($created !== []) : ?>
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e('Copy this password now — it will not be shown again.', 'nw-fuel'); ?></strong></p>
            <p><?php echo esc_html((string) ($created['email'] ?? '')); ?> — <code><?php echo esc_html((string) ($created['password'] ?? '')); ?></code></p>
        </div>
        <?php endif; ?>

        <h2><?php esc_html_e('Add partner', 'nw-fuel'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:40rem;margin-bottom:2rem;">
            <?php wp_nonce_field('nw_fuel_add_partner', 'nw_fuel_partner_nonce'); ?>
            <input type="hidden" name="action" value="nw_fuel_add_partner">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="partner_name"><?php esc_html_e('Name', 'nw-fuel'); ?></label></th>
                    <td><input class="regular-text" type="text" id="partner_name" name="partner_name"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="partner_email"><?php esc_html_e('Email', 'nw-fuel'); ?></label></th>
                    <td><input class="regular-text" type="email" id="partner_email" name="partner_email" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="partner_password"><?php esc_html_e('Password', 'nw-fuel'); ?></label></th>
                    <td>
                        <input class="regular-text" type="text" id="partner_password" name="partner_password" autocomplete="new-password">
                        <p class="description"><?php esc_html_e('Leave blank to generate one.', 'nw-fuel'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="partner_level"><?php esc_html_e('Discount level', 'nw-fuel'); ?></label></th>
                    <td>
                        <select id="partner_level" name="partner_level">
                            <?php foreach ($choices as $value => $label) : ?>
                            <option value="<?php echo esc_attr((string) $value); ?>"<?php selected($value, 1); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Add partner', 'nw-fuel'), 'primary', 'submit', false); ?>
        </form>

        <h2><?php esc_html_e('Partner list', 'nw-fuel'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'nw-fuel'); ?></th>
                    <th><?php esc_html_e('Email', 'nw-fuel'); ?></th>
                    <th><?php esc_html_e('Discount level', 'nw-fuel'); ?></th>
                    <th><?php esc_html_e('New password', 'nw-fuel'); ?></th>
                    <th><?php esc_html_e('Actions', 'nw-fuel'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($partners === []) : ?>
                <tr><td colspan="5"><?php esc_html_e('No partners yet.', 'nw-fuel'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($partners as $row) : ?>
                <?php $save_id = 'nw-partner-save-' . (int) $row['id']; ?>
                <tr>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td><?php echo esc_html($row['email']); ?></td>
                    <td>
                        <form id="<?php echo esc_attr($save_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('nw_fuel_save_partner', 'nw_fuel_partner_nonce'); ?>
                            <input type="hidden" name="action" value="nw_fuel_save_partner">
                            <input type="hidden" name="partner_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                            <select name="partner_level">
                                <?php foreach ($choices as $value => $label) : ?>
                                <option value="<?php echo esc_attr((string) $value); ?>"<?php selected($row['level'], $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <input form="<?php echo esc_attr($save_id); ?>" type="text" name="partner_password" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to keep', 'nw-fuel'); ?>" autocomplete="new-password">
                    </td>
                    <td>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;">
                            <?php submit_button(__('Save', 'nw-fuel'), 'secondary', 'submit', false, ['form' => $save_id]); ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this partner?');" style="margin:0;">
                                <?php wp_nonce_field('nw_fuel_remove_partner', 'nw_fuel_partner_nonce'); ?>
                                <input type="hidden" name="action" value="nw_fuel_remove_partner">
                                <input type="hidden" name="partner_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                                <?php submit_button(__('Delete', 'nw-fuel'), 'delete', 'submit', false); ?>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function nw_fuel_render_partner_nav(): void
{
    $partner  = nw_fuel_current_partner();
    $redirect = nw_fuel_partner_return_url();
    ?>
    <?php if ($partner) : ?>
    <div class="partner-account" data-partner-account>
      <button type="button" class="partner-account__toggle" aria-expanded="false" aria-controls="partner-account-menu" aria-label="<?php esc_attr_e('Partner account', 'nw-fuel'); ?>">
        <span class="partner-account__avatar" aria-hidden="true"></span>
      </button>
      <div class="partner-account__menu" id="partner-account-menu" hidden>
        <p class="partner-account__name"><?php echo esc_html($partner['name'] !== '' ? $partner['name'] : $partner['email']); ?></p>
        <p class="partner-account__email"><?php echo esc_html($partner['email']); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('nw_fuel_partner_logout', 'nw_fuel_partner_logout_nonce'); ?>
          <input type="hidden" name="action" value="nw_fuel_partner_logout">
          <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
          <button type="submit" class="partner-account__logout"><?php esc_html_e('Log out', 'nw-fuel'); ?></button>
        </form>
      </div>
    </div>
    <?php else : ?>
    <button type="button" class="partner-login-open" data-partner-login-open aria-haspopup="dialog" aria-controls="partner-login-modal" aria-label="<?php esc_attr_e('Partner login', 'nw-fuel'); ?>">
      <span class="partner-login-open__icon" aria-hidden="true"></span>
    </button>
    <?php endif; ?>
    <?php
}

function nw_fuel_render_partner_login_modal(): void
{
    if (nw_fuel_current_partner()) {
        return;
    }

    $redirect = nw_fuel_partner_return_url();
    $error    = isset($_GET['partner_login']) && sanitize_key((string) wp_unslash($_GET['partner_login'])) === 'error';
    ?>
    <div class="partner-login<?php echo $error ? ' is-open' : ''; ?>" id="partner-login-modal" <?php echo $error ? '' : 'hidden'; ?> role="dialog" aria-modal="true" aria-labelledby="partner-login-title" data-partner-login-modal>
      <div class="partner-login__backdrop" data-partner-login-close></div>
      <div class="partner-login__panel">
        <button type="button" class="partner-login__close" data-partner-login-close aria-label="<?php esc_attr_e('Close', 'nw-fuel'); ?>">×</button>
        <h2 id="partner-login-title"><?php esc_html_e('Partner login', 'nw-fuel'); ?></h2>
        <p><?php esc_html_e('Log in to see your discount pricing.', 'nw-fuel'); ?></p>
        <?php if ($error) : ?>
        <p class="partner-login__error"><?php esc_html_e('Email or password is incorrect.', 'nw-fuel'); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('nw_fuel_partner_login', 'nw_fuel_partner_login_nonce'); ?>
          <input type="hidden" name="action" value="nw_fuel_partner_login">
          <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
          <label for="partner-login-email"><?php esc_html_e('Email', 'nw-fuel'); ?></label>
          <input id="partner-login-email" type="email" name="partner_email" required autocomplete="username">
          <label for="partner-login-password"><?php esc_html_e('Password', 'nw-fuel'); ?></label>
          <input id="partner-login-password" type="password" name="partner_password" required autocomplete="current-password">
          <button type="submit" class="btn btn--primary"><?php esc_html_e('Log in', 'nw-fuel'); ?></button>
        </form>
      </div>
    </div>
    <?php
}
