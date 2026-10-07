<?php
/**
 * Contact Us form requests — stored for admin review.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes_nw_contact_request', 'nw_fuel_contact_request_meta_boxes');
add_action('save_post_nw_contact_request', 'nw_fuel_save_contact_request_admin', 10, 2);
add_filter('wp_insert_post_data', 'nw_fuel_preserve_contact_request_content', 10, 2);
add_action('admin_enqueue_scripts', 'nw_fuel_contact_request_admin_assets');
add_action('admin_menu', 'nw_fuel_contact_request_menu_badge', 999);
add_action('load-post.php', 'nw_fuel_contact_request_mark_read');
add_action('admin_notices', 'nw_fuel_contact_request_admin_notices');
add_action('pre_get_posts', 'nw_fuel_contact_request_admin_query');
add_filter('manage_nw_contact_request_posts_columns', 'nw_fuel_contact_request_columns');
add_action('manage_nw_contact_request_posts_custom_column', 'nw_fuel_contact_request_column_content', 10, 2);
add_filter('post_row_actions', 'nw_fuel_contact_request_row_actions', 10, 2);
add_filter('views_edit-nw_contact_request', 'nw_fuel_contact_request_views');
add_filter('bulk_actions-edit-nw_contact_request', 'nw_fuel_contact_request_bulk_actions');
add_filter('handle_bulk_actions-edit-nw_contact_request', 'nw_fuel_handle_contact_request_bulk', 10, 3);
add_action('restrict_manage_posts', 'nw_fuel_contact_request_source_dropdown', 10, 2);
add_action('admin_post_nw_fuel_export_contact_requests', 'nw_fuel_export_contact_requests');
add_filter('posts_search', 'nw_fuel_contact_request_posts_search', 10, 2);
add_filter('post_updated_messages', 'nw_fuel_contact_request_updated_messages');
add_filter('bulk_post_updated_messages', 'nw_fuel_contact_request_bulk_messages', 10, 2);

/**
 * Topic labels matching the Contact Us form.
 *
 * @return array<string, string>
 */
function nw_fuel_contact_subject_labels(): array
{
    return [
        'quote'             => __('Request a Quote', 'nw-fuel'),
        'product-quote'     => __('Product Quote', 'nw-fuel'),
        'service-quote'     => __('Service Quote', 'nw-fuel'),
        'injector-testing'  => __('Injector Testing', 'nw-fuel'),
        'pump-rebuild'      => __('Pump Rebuilding', 'nw-fuel'),
        'parts'             => __('Parts Inquiry', 'nw-fuel'),
        'technical'         => __('Technical Support', 'nw-fuel'),
        'other'             => __('Other', 'nw-fuel'),
    ];
}

/**
 * Admin workflow statuses.
 *
 * @return array<string, string>
 */
function nw_fuel_contact_request_statuses(): array
{
    return [
        'new'      => __('Unread', 'nw-fuel'),
        'open'     => __('Open', 'nw-fuel'),
        'replied'  => __('Replied', 'nw-fuel'),
        'archived' => __('Archived', 'nw-fuel'),
    ];
}

/**
 * Human-readable topic from the form value.
 */
function nw_fuel_contact_subject_label(string $subject): string
{
    $labels = nw_fuel_contact_subject_labels();

    return $labels[$subject] ?? ($subject !== '' ? $subject : __('Contact', 'nw-fuel'));
}

/**
 * Stored or default status for a request.
 */
function nw_fuel_contact_request_status(int $post_id): string
{
    $status = sanitize_key((string) get_post_meta($post_id, '_nw_contact_status', true));

    return array_key_exists($status, nw_fuel_contact_request_statuses()) ? $status : 'new';
}

/**
 * Inbox source labels.
 *
 * @return array<string, string>
 */
function nw_fuel_contact_request_sources(): array
{
    return [
        'contact'       => __('Contact form', 'nw-fuel'),
        'product_quote' => __('Product quote', 'nw-fuel'),
        'service_quote' => __('Service quote', 'nw-fuel'),
    ];
}

/**
 * Stored source, defaulting older rows to the contact form.
 */
function nw_fuel_contact_request_source(int $post_id): string
{
    $source = sanitize_key((string) get_post_meta($post_id, '_nw_contact_source', true));

    return array_key_exists($source, nw_fuel_contact_request_sources()) ? $source : 'contact';
}

/**
 * Persist a public Contact Us or quote submission. Returns the post ID, or 0 on failure.
 *
 * @param array<string, mixed> $data
 */
function nw_fuel_store_contact_request(array $data): int
{
    $first   = (string) ($data['first'] ?? '');
    $last    = (string) ($data['last'] ?? '');
    $email   = (string) ($data['email'] ?? '');
    $phone   = (string) ($data['phone'] ?? '');
    $subject = (string) ($data['subject'] ?? '');
    $message = (string) ($data['message'] ?? '');
    $source  = sanitize_key((string) ($data['source'] ?? 'contact'));
    if (! array_key_exists($source, nw_fuel_contact_request_sources())) {
        $source = 'contact';
    }

    $product      = (string) ($data['product'] ?? '');
    $part         = (string) ($data['part'] ?? '');
    $quantity     = absint($data['quantity'] ?? 0);
    $product_code = (string) ($data['product_code'] ?? '');
    $product_id   = absint($data['product_id'] ?? 0);
    $service      = (string) ($data['service'] ?? '');
    $name         = trim($first . ' ' . $last);

    if ($source === 'product_quote') {
        $topic = $part !== '' ? $part : ($product !== '' ? $product : __('Product Quote', 'nw-fuel'));
        $title = __('Product Quote', 'nw-fuel') . ' — ' . $topic . ($name !== '' ? ' — ' . $name : '');
    } elseif ($source === 'service_quote') {
        $topic = $service !== '' ? $service : __('Service Quote', 'nw-fuel');
        $title = __('Service Quote', 'nw-fuel') . ' — ' . $topic . ($name !== '' ? ' — ' . $name : '');
    } else {
        $title = nw_fuel_contact_subject_label($subject) . ($name !== '' ? ' — ' . $name : '');
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'nw_contact_request',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $message,
            'post_author'  => get_current_user_id(),
        ],
        true
    );

    if (is_wp_error($post_id) || $post_id <= 0) {
        return 0;
    }

    $id = (int) $post_id;
    update_post_meta($id, '_nw_contact_source', $source);
    update_post_meta($id, '_nw_contact_first_name', $first);
    update_post_meta($id, '_nw_contact_last_name', $last);
    update_post_meta($id, '_nw_contact_email', $email);
    update_post_meta($id, '_nw_contact_phone', $phone);
    update_post_meta($id, '_nw_contact_subject', $subject);
    update_post_meta($id, '_nw_contact_status', 'new');
    update_post_meta($id, '_nw_contact_email_sent', ! empty($data['email_sent']) ? '1' : '0');
    update_post_meta($id, '_nw_contact_product', $product);
    update_post_meta($id, '_nw_contact_part', $part);
    update_post_meta($id, '_nw_contact_quantity', $quantity > 0 ? (string) $quantity : '');
    update_post_meta($id, '_nw_contact_product_code', $product_code);
    update_post_meta($id, '_nw_contact_product_id', $product_id > 0 ? (string) $product_id : '');
    update_post_meta($id, '_nw_contact_service', $service);
    if ($source === 'product_quote') {
        $price = isset($data['price']) ? (float) $data['price'] : 0.0;
        update_post_meta($id, '_nw_contact_price', $price > 0 ? number_format($price, 2, '.', '') : '');
        update_post_meta($id, '_nw_contact_price_type', sanitize_key((string) ($data['price_type'] ?? '')));
        update_post_meta($id, '_nw_contact_price_label', sanitize_text_field((string) ($data['price_label'] ?? '')));
        update_post_meta($id, '_nw_contact_partner_level', (string) absint($data['partner_level'] ?? 0));
    }

    return $id;
}

/**
 * Meta boxes on the request screen.
 */
function nw_fuel_contact_request_meta_boxes(): void
{
    remove_post_type_support('nw_contact_request', 'editor');
    remove_meta_box('slugdiv', 'nw_contact_request', 'normal');
    remove_meta_box('commentstatusdiv', 'nw_contact_request', 'normal');
    remove_meta_box('commentsdiv', 'nw_contact_request', 'normal');

    add_meta_box(
        'nw-fuel-contact-request-details',
        __('Request', 'nw-fuel'),
        'nw_fuel_contact_request_details_box',
        'nw_contact_request',
        'normal',
        'high'
    );

    add_meta_box(
        'nw-fuel-contact-request-manage',
        __('Manage', 'nw-fuel'),
        'nw_fuel_contact_request_manage_box',
        'nw_contact_request',
        'side',
        'high'
    );
}

/**
 * Read-only submission details.
 */
function nw_fuel_contact_request_details_box(WP_Post $post): void
{
    $id      = (int) $post->ID;
    $first   = (string) get_post_meta($id, '_nw_contact_first_name', true);
    $last    = (string) get_post_meta($id, '_nw_contact_last_name', true);
    $email   = (string) get_post_meta($id, '_nw_contact_email', true);
    $phone   = (string) get_post_meta($id, '_nw_contact_phone', true);
    $subject = (string) get_post_meta($id, '_nw_contact_subject', true);
    $source  = nw_fuel_contact_request_source($id);
    $sources = nw_fuel_contact_request_sources();
    $sent    = (string) get_post_meta($id, '_nw_contact_email_sent', true) === '1';
    $product = (string) get_post_meta($id, '_nw_contact_product', true);
    $part    = (string) get_post_meta($id, '_nw_contact_part', true);
    $qty     = (string) get_post_meta($id, '_nw_contact_quantity', true);
    $code    = (string) get_post_meta($id, '_nw_contact_product_code', true);
    $price   = (string) get_post_meta($id, '_nw_contact_price', true);
    $price_label = (string) get_post_meta($id, '_nw_contact_price_label', true);
    $pid     = absint((string) get_post_meta($id, '_nw_contact_product_id', true));
    $service = (string) get_post_meta($id, '_nw_contact_service', true);
    $name    = trim($first . ' ' . $last);
    $mailto  = nw_fuel_contact_request_mailto($id);
    $note    = trim((string) $post->post_content);
    ?>
    <div class="nw-contact-request">
      <dl class="nw-contact-request__dl">
        <div>
          <dt><?php esc_html_e('Type', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($sources[$source]); ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Name', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($name !== '' ? $name : '—'); ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Email', 'nw-fuel'); ?></dt>
          <dd>
            <?php if ($email !== '') : ?>
            <a href="<?php echo esc_url($mailto); ?>"><?php echo esc_html($email); ?></a>
            <?php else : ?>
            —
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt><?php esc_html_e('Phone', 'nw-fuel'); ?></dt>
          <dd>
            <?php if ($phone !== '') : ?>
            <a href="<?php echo esc_url(nw_fuel_phone_href($phone)); ?>"><?php echo esc_html($phone); ?></a>
            <?php else : ?>
            —
            <?php endif; ?>
          </dd>
        </div>
        <?php if ($source === 'contact') : ?>
        <div>
          <dt><?php esc_html_e('Topic', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html(nw_fuel_contact_subject_label($subject)); ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($source === 'product_quote') : ?>
        <div>
          <dt><?php esc_html_e('Product', 'nw-fuel'); ?></dt>
          <dd><?php
            if ($pid > 0) {
                $edit = get_edit_post_link($pid);
                $view = get_permalink($pid);
                echo esc_html($product !== '' ? $product : '#' . $pid);
                if ($edit) {
                    echo ' — <a href="' . esc_url($edit) . '">' . esc_html__('Edit product', 'nw-fuel') . '</a>';
                }
                if ($view) {
                    echo ' — <a href="' . esc_url($view) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View page', 'nw-fuel') . '</a>';
                }
            } else {
                echo esc_html($product !== '' ? $product : '—');
            }
          ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Part number', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($part !== '' ? $part : '—'); ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Quantity', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($qty !== '' ? $qty : '—'); ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Price', 'nw-fuel'); ?></dt>
          <dd><?php
            if ($price !== '' && (float) $price > 0) {
                echo esc_html(function_exists('nw_fuel_format_price') ? nw_fuel_format_price((float) $price) : '$' . $price);
            } else {
                echo '—';
            }
          ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Price type', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($price_label !== '' ? $price_label : '—'); ?></dd>
        </div>
        <?php if ($code !== '') : ?>
        <div>
          <dt><?php esc_html_e('Product code', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($code); ?></dd>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($source === 'service_quote') : ?>
        <div>
          <dt><?php esc_html_e('Service', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($service !== '' ? $service : '—'); ?></dd>
        </div>
        <?php endif; ?>
        <div>
          <dt><?php esc_html_e('Received', 'nw-fuel'); ?></dt>
          <dd><?php
            $received = get_post_timestamp($post);
            echo $received ? esc_html((string) wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format'), $received)) : '—';
          ?></dd>
        </div>
        <div>
          <dt><?php esc_html_e('Shop email', 'nw-fuel'); ?></dt>
          <dd><?php echo esc_html($sent ? __('Notification sent', 'nw-fuel') : __('Not sent', 'nw-fuel')); ?></dd>
        </div>
      </dl>
      <h3 class="nw-contact-request__msg-title"><?php echo esc_html($source === 'contact' ? __('Message', 'nw-fuel') : __('Notes', 'nw-fuel')); ?></h3>
      <div class="nw-contact-request__message"><?php echo esc_html($note !== '' ? $note : __('No notes.', 'nw-fuel')); ?></div>
      <?php if ($email !== '') : ?>
      <p class="nw-contact-request__reply">
        <a class="button button-primary" href="<?php echo esc_url($mailto); ?>"><?php esc_html_e('Reply by email', 'nw-fuel'); ?></a>
      </p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * Status + internal notes.
 */
function nw_fuel_contact_request_manage_box(WP_Post $post): void
{
    wp_nonce_field('nw_fuel_contact_request_admin', 'nw_fuel_contact_request_admin_nonce');
    $status = nw_fuel_contact_request_status((int) $post->ID);
    $notes  = (string) get_post_meta((int) $post->ID, '_nw_contact_notes', true);
    ?>
    <p>
      <label for="nw-contact-status"><strong><?php esc_html_e('Status', 'nw-fuel'); ?></strong></label>
      <select id="nw-contact-status" name="nw_contact_status" class="widefat" style="margin-top:4px;">
        <?php foreach (nw_fuel_contact_request_statuses() as $value => $label) : ?>
        <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <p>
      <label for="nw-contact-notes"><strong><?php esc_html_e('Internal notes', 'nw-fuel'); ?></strong></label>
      <textarea id="nw-contact-notes" name="nw_contact_notes" class="widefat" rows="6" placeholder="<?php esc_attr_e('Only visible to admins.', 'nw-fuel'); ?>"><?php echo esc_textarea($notes); ?></textarea>
    </p>
    <?php
}

/**
 * Keep the original message/title when an admin updates status or notes.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $postarr
 * @return array<string, mixed>
 */
function nw_fuel_preserve_contact_request_content(array $data, array $postarr): array
{
    if (($data['post_type'] ?? '') !== 'nw_contact_request') {
        return $data;
    }

    $id = (int) ($postarr['ID'] ?? 0);
    if ($id < 1) {
        return $data;
    }

    $existing = get_post($id);
    if (! $existing instanceof WP_Post) {
        return $data;
    }

    $data['post_content'] = $existing->post_content;
    $data['post_title']   = $existing->post_title;

    return $data;
}

/**
 * Save status and notes from the admin screen.
 */
function nw_fuel_save_contact_request_admin(int $post_id, WP_Post $post): void
{
    unset($post);

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! isset($_POST['nw_fuel_contact_request_admin_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_contact_request_admin_nonce'])), 'nw_fuel_contact_request_admin')) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    $statuses = nw_fuel_contact_request_statuses();
    $status   = sanitize_key((string) wp_unslash($_POST['nw_contact_status'] ?? 'open'));
    if (! array_key_exists($status, $statuses)) {
        $status = 'open';
    }

    update_post_meta($post_id, '_nw_contact_status', $status);
    update_post_meta($post_id, '_nw_contact_notes', sanitize_textarea_field(wp_unslash($_POST['nw_contact_notes'] ?? '')));
}

/**
 * Admin CSS on the list and detail screens.
 */
function nw_fuel_contact_request_admin_assets(string $hook): void
{
    $screen = get_current_screen();
    if (! $screen || $screen->post_type !== 'nw_contact_request') {
        return;
    }
    if (! in_array($hook, ['post.php', 'edit.php'], true)) {
        return;
    }

    wp_enqueue_style(
        'nw-fuel-admin-meta',
        NW_FUEL_URI . '/assets/css/admin-meta.css',
        [],
        NW_FUEL_VERSION
    );
}

/**
 * Unread count on the admin menu — no total, only unread.
 */
function nw_fuel_contact_request_menu_badge(): void
{
    global $menu;

    if (! is_array($menu) || ! current_user_can('edit_posts')) {
        return;
    }

    $count = nw_fuel_contact_request_unread_count();
    if ($count < 1) {
        return;
    }

    foreach ($menu as $i => $item) {
        if (! is_array($item) || ($item[2] ?? '') !== 'edit.php?post_type=nw_contact_request') {
            continue;
        }
        $menu[$i][0] .= sprintf(
            ' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text"> %3$s</span></span>',
            $count,
            esc_html(number_format_i18n($count)),
            esc_html(_n('unread request', 'unread requests', $count, 'nw-fuel'))
        );
        break;
    }
}

/**
 * Opening a request marks it read so the menu badge drops.
 */
function nw_fuel_contact_request_mark_read(): void
{
    $post_id = absint($_GET['post'] ?? 0);
    if ($post_id < 1 || ! current_user_can('edit_post', $post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (! $post instanceof WP_Post || $post->post_type !== 'nw_contact_request') {
        return;
    }

    if (nw_fuel_contact_request_status($post_id) !== 'new') {
        return;
    }

    update_post_meta($post_id, '_nw_contact_status', 'open');
}

/**
 * Bulk-action confirmation.
 */
function nw_fuel_contact_request_admin_notices(): void
{
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'edit-nw_contact_request') {
        return;
    }

    $updated = absint($_GET['nw_contact_updated'] ?? 0);
    if ($updated < 1) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html(sprintf(
        /* translators: %d: number of requests updated */
        _n('%d request updated.', '%d requests updated.', $updated, 'nw-fuel'),
        $updated
    ));
    echo '</p></div>';
}

/**
 * Current list filters from the request.
 *
 * @return array{status: string, source: string, search: string, post_status: string}
 */
function nw_fuel_contact_request_filters_from_request(): array
{
    $status      = sanitize_key((string) ($_REQUEST['nw_status'] ?? ''));
    $source      = sanitize_key((string) ($_REQUEST['nw_source'] ?? ''));
    $search      = sanitize_text_field(wp_unslash((string) ($_REQUEST['s'] ?? '')));
    $post_status = sanitize_key((string) ($_REQUEST['post_status'] ?? 'publish'));

    if ($status !== '' && ! array_key_exists($status, nw_fuel_contact_request_statuses())) {
        $status = '';
    }
    if ($source !== '' && ! array_key_exists($source, nw_fuel_contact_request_sources())) {
        $source = '';
    }
    if ($post_status !== 'trash') {
        $post_status = 'publish';
    }

    return [
        'status'      => $status,
        'source'      => $source,
        'search'      => $search,
        'post_status' => $post_status,
    ];
}

/**
 * Meta query matching the list filters.
 *
 * @param array{status: string, source: string, search: string, post_status: string} $filters
 * @return array<int, array<string, mixed>>
 */
function nw_fuel_contact_request_meta_query_from_filters(array $filters): array
{
    $meta = [];

    if ($filters['post_status'] !== 'trash' && $filters['status'] !== '') {
        $meta[] = [
            'key'   => '_nw_contact_status',
            'value' => $filters['status'],
        ];
    }

    if ($filters['source'] !== '') {
        if ($filters['source'] === 'contact') {
            $meta[] = [
                'relation' => 'OR',
                [
                    'key'   => '_nw_contact_source',
                    'value' => 'contact',
                ],
                [
                    'key'     => '_nw_contact_source',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        } else {
            $meta[] = [
                'key'   => '_nw_contact_source',
                'value' => $filters['source'],
            ];
        }
    }

    return $meta;
}

/**
 * Apply inbox filters to the requests list.
 */
function nw_fuel_contact_request_admin_query(WP_Query $query): void
{
    if (! is_admin() || ! $query->is_main_query()) {
        return;
    }
    if ($query->get('post_type') !== 'nw_contact_request') {
        return;
    }
    if ($query->get('post_status') === 'trash') {
        return;
    }

    $meta = $query->get('meta_query');
    if (! is_array($meta)) {
        $meta = [];
    }

    $extra = nw_fuel_contact_request_meta_query_from_filters(nw_fuel_contact_request_filters_from_request());
    if ($extra === []) {
        return;
    }

    $query->set('meta_query', array_merge($meta, $extra));
}

/**
 * List table columns.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function nw_fuel_contact_request_columns(array $columns): array
{
    return [
        'cb'     => $columns['cb'] ?? '<input type="checkbox" />',
        'title'  => __('Request', 'nw-fuel'),
        'type'   => __('Type', 'nw-fuel'),
        'email'  => __('Email', 'nw-fuel'),
        'phone'  => __('Phone', 'nw-fuel'),
        'status' => __('Status', 'nw-fuel'),
        'date'   => __('Received', 'nw-fuel'),
    ];
}

/**
 * List table cell output.
 */
function nw_fuel_contact_request_column_content(string $column, int $post_id): void
{
    if ($column === 'type') {
        $source  = nw_fuel_contact_request_source($post_id);
        $sources = nw_fuel_contact_request_sources();
        echo '<span class="nw-status-pill nw-status-pill--' . esc_attr($source) . '">' . esc_html($sources[$source]) . '</span>';
        $part    = (string) get_post_meta($post_id, '_nw_contact_part', true);
        $product = (string) get_post_meta($post_id, '_nw_contact_product', true);
        $service = (string) get_post_meta($post_id, '_nw_contact_service', true);
        $extra   = $part !== '' ? $part : ($product !== '' ? $product : $service);
        if ($extra !== '') {
            echo '<br><span class="description">' . esc_html($extra) . '</span>';
        }
        return;
    }

    if ($column === 'email') {
        $email = (string) get_post_meta($post_id, '_nw_contact_email', true);
        if ($email === '') {
            echo '—';
            return;
        }
        echo '<a href="' . esc_url(nw_fuel_contact_request_mailto($post_id)) . '">' . esc_html($email) . '</a>';
        return;
    }

    if ($column === 'phone') {
        $phone = (string) get_post_meta($post_id, '_nw_contact_phone', true);
        echo $phone !== '' ? '<a href="' . esc_url(nw_fuel_phone_href($phone)) . '">' . esc_html($phone) . '</a>' : '—';
        return;
    }

    if ($column === 'status') {
        $status = nw_fuel_contact_request_status($post_id);
        $labels = nw_fuel_contact_request_statuses();
        echo '<span class="nw-status-pill nw-status-pill--' . esc_attr($status) . '">' . esc_html($labels[$status]) . '</span>';
    }
}

/**
 * Row actions: view, reply, trash — no public View / Quick Edit.
 *
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function nw_fuel_contact_request_row_actions(array $actions, WP_Post $post): array
{
    if ($post->post_type !== 'nw_contact_request') {
        return $actions;
    }

    unset($actions['view'], $actions['inline hide-if-no-js']);

    if (isset($actions['edit'])) {
        $actions['edit'] = '<a href="' . esc_url(get_edit_post_link($post->ID) ?: '') . '">' . esc_html__('View', 'nw-fuel') . '</a>';
    }

    $email = (string) get_post_meta((int) $post->ID, '_nw_contact_email', true);
    if ($email !== '') {
        $reply = [
            'reply' => '<a href="' . esc_url(nw_fuel_contact_request_mailto((int) $post->ID)) . '">' . esc_html__('Reply', 'nw-fuel') . '</a>',
        ];
        $actions = array_merge($reply, $actions);
    }

    return $actions;
}

/**
 * Status tabs above the list.
 *
 * @param array<string, string> $views
 * @return array<string, string>
 */
function nw_fuel_contact_request_views(array $views): array
{
    $unread  = nw_fuel_contact_request_unread_count();
    $current = sanitize_key((string) ($_GET['nw_status'] ?? ''));
    $trash   = sanitize_key((string) ($_GET['post_status'] ?? '')) === 'trash';
    $base    = nw_fuel_contact_request_list_url();
    $out     = [];

    $out['all'] = sprintf(
        '<a href="%s"%s>%s</a>',
        esc_url($base),
        ($current === '' && ! $trash) ? ' class="current" aria-current="page"' : '',
        esc_html__('All', 'nw-fuel')
    );

    foreach (nw_fuel_contact_request_statuses() as $key => $label) {
        if ($key === 'new') {
            $out[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('nw_status', $key, $base)),
                ($current === $key && ! $trash) ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                $unread
            );
            continue;
        }

        $out[$key] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url(add_query_arg('nw_status', $key, $base)),
            ($current === $key && ! $trash) ? ' class="current" aria-current="page"' : '',
            esc_html($label)
        );
    }

    if (isset($views['trash'])) {
        $out['trash'] = $views['trash'];
    }

    return $out;
}

/**
 * Type dropdown on the requests list.
 */
function nw_fuel_contact_request_source_dropdown(string $post_type, string $which): void
{
    if ($post_type !== 'nw_contact_request') {
        return;
    }

    $filters = nw_fuel_contact_request_filters_from_request();
    $field_id = $which === 'bottom' ? 'nw-contact-source-filter-bottom' : 'nw-contact-source-filter';

    if ($filters['status'] !== '') {
        echo '<input type="hidden" name="nw_status" value="' . esc_attr($filters['status']) . '">';
    }
    echo '<label class="screen-reader-text" for="' . esc_attr($field_id) . '">' . esc_html__('Filter by type', 'nw-fuel') . '</label>';
    echo '<select name="nw_source" id="' . esc_attr($field_id) . '">';
    echo '<option value="">' . esc_html__('All types', 'nw-fuel') . '</option>';
    foreach (nw_fuel_contact_request_sources() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($filters['source'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';

    if ($which !== 'top') {
        return;
    }

    $export = [
        'action' => 'nw_fuel_export_contact_requests',
    ];
    if ($filters['source'] !== '') {
        $export['nw_source'] = $filters['source'];
    }
    if ($filters['status'] !== '') {
        $export['nw_status'] = $filters['status'];
    }
    if ($filters['search'] !== '') {
        $export['s'] = $filters['search'];
    }
    if ($filters['post_status'] === 'trash') {
        $export['post_status'] = 'trash';
    }

    $url = wp_nonce_url(add_query_arg($export, admin_url('admin-post.php')), 'nw_fuel_export_contact_requests');
    echo '<a class="button nw-contact-export" href="' . esc_url($url) . '">' . esc_html__('Export CSV', 'nw-fuel') . '</a>';
}

/**
 * Guard a CSV cell against formula injection.
 */
function nw_fuel_contact_request_csv_cell(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

/**
 * Full visitor message plus quote details.
 */
function nw_fuel_contact_request_complete_note(WP_Post $post): string
{
    $id     = (int) $post->ID;
    $source = nw_fuel_contact_request_source($id);
    $lines  = [];

    if ($source === 'contact') {
        $topic = nw_fuel_contact_subject_label((string) get_post_meta($id, '_nw_contact_subject', true));
        if ($topic !== '') {
            $lines[] = sprintf(/* translators: %s: contact topic */ __('Topic: %s', 'nw-fuel'), $topic);
        }
    }

    if ($source === 'product_quote') {
        $product = (string) get_post_meta($id, '_nw_contact_product', true);
        $part    = (string) get_post_meta($id, '_nw_contact_part', true);
        $qty     = (string) get_post_meta($id, '_nw_contact_quantity', true);
        $code    = (string) get_post_meta($id, '_nw_contact_product_code', true);
        $price   = (string) get_post_meta($id, '_nw_contact_price', true);
        $price_label = (string) get_post_meta($id, '_nw_contact_price_label', true);
        if ($product !== '') {
            $lines[] = sprintf(/* translators: %s: product name */ __('Product: %s', 'nw-fuel'), $product);
        }
        if ($part !== '') {
            $lines[] = sprintf(/* translators: %s: part number */ __('Part number: %s', 'nw-fuel'), $part);
        }
        if ($qty !== '') {
            $lines[] = sprintf(/* translators: %s: quantity */ __('Quantity: %s', 'nw-fuel'), $qty);
        }
        if ($price !== '' && (float) $price > 0) {
            $price_text = function_exists('nw_fuel_format_price') ? nw_fuel_format_price((float) $price) : '$' . $price;
            $lines[]    = sprintf(/* translators: %s: price */ __('Price: %s', 'nw-fuel'), $price_text);
        }
        if ($price_label !== '') {
            $lines[] = sprintf(/* translators: %s: retail or discount level */ __('Price type: %s', 'nw-fuel'), $price_label);
        }
        if ($code !== '') {
            $lines[] = sprintf(/* translators: %s: product code */ __('Product code: %s', 'nw-fuel'), $code);
        }
    }

    if ($source === 'service_quote') {
        $service = (string) get_post_meta($id, '_nw_contact_service', true);
        if ($service !== '') {
            $lines[] = sprintf(/* translators: %s: service name */ __('Service: %s', 'nw-fuel'), $service);
        }
    }

    $message = trim((string) $post->post_content);
    if ($message !== '') {
        $lines[] = $message;
    }

    return implode("\n", $lines);
}

/**
 * Download a CSV of requests matching the current list filters.
 */
function nw_fuel_export_contact_requests(): void
{
    if (! current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to export contact requests.', 'nw-fuel'), '', ['response' => 403]);
    }

    check_admin_referer('nw_fuel_export_contact_requests');

    $filters = nw_fuel_contact_request_filters_from_request();
    $args    = [
        'post_type'              => 'nw_contact_request',
        'post_status'            => $filters['post_status'],
        'posts_per_page'         => -1,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'nw_fuel_contact_export' => true,
    ];

    if ($filters['search'] !== '') {
        $args['s'] = $filters['search'];
    }

    $meta = nw_fuel_contact_request_meta_query_from_filters($filters);
    if ($meta !== []) {
        $args['meta_query'] = $meta;
    }

    $query   = new WP_Query($args);
    $sources = nw_fuel_contact_request_sources();
    $date_f  = (string) get_option('date_format') . ' ' . (string) get_option('time_format');
    $filename = 'contact-requests-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die(esc_html__('Could not start the export.', 'nw-fuel'));
    }

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        __('Type', 'nw-fuel'),
        __('Name', 'nw-fuel'),
        __('Email', 'nw-fuel'),
        __('Phone', 'nw-fuel'),
        __('Note', 'nw-fuel'),
        __('Received', 'nw-fuel'),
    ]);

    foreach ($query->posts as $post) {
        if (! $post instanceof WP_Post) {
            continue;
        }

        $id       = (int) $post->ID;
        $source   = nw_fuel_contact_request_source($id);
        $received = get_post_timestamp($post);

        fputcsv($out, [
            nw_fuel_contact_request_csv_cell($sources[$source] ?? $source),
            nw_fuel_contact_request_csv_cell(trim((string) get_post_meta($id, '_nw_contact_first_name', true) . ' ' . (string) get_post_meta($id, '_nw_contact_last_name', true))),
            nw_fuel_contact_request_csv_cell((string) get_post_meta($id, '_nw_contact_email', true)),
            nw_fuel_contact_request_csv_cell((string) get_post_meta($id, '_nw_contact_phone', true)),
            nw_fuel_contact_request_csv_cell(nw_fuel_contact_request_complete_note($post)),
            nw_fuel_contact_request_csv_cell($received ? (string) wp_date($date_f, $received) : ''),
        ]);
    }

    fclose($out);
    exit;
}

/**
 * List URL, keeping the type filter when switching status tabs.
 */
function nw_fuel_contact_request_list_url(): string
{
    $url    = admin_url('edit.php?post_type=nw_contact_request');
    $source = sanitize_key((string) ($_GET['nw_source'] ?? ''));
    if ($source !== '' && array_key_exists($source, nw_fuel_contact_request_sources())) {
        $url = add_query_arg('nw_source', $source, $url);
    }

    return $url;
}

/**
 * Extra bulk actions for status.
 *
 * @param array<string, string> $actions
 * @return array<string, string>
 */
function nw_fuel_contact_request_bulk_actions(array $actions): array
{
    unset($actions['edit']);
    $actions['nw_mark_open']     = __('Mark as Open', 'nw-fuel');
    $actions['nw_mark_replied']  = __('Mark as Replied', 'nw-fuel');
    $actions['nw_mark_archived'] = __('Mark as Archived', 'nw-fuel');

    return $actions;
}

/**
 * Apply bulk status changes.
 *
 * @param array<int, int|string> $post_ids
 */
function nw_fuel_handle_contact_request_bulk(string $redirect, string $action, array $post_ids): string
{
    $map = [
        'nw_mark_open'     => 'open',
        'nw_mark_replied'  => 'replied',
        'nw_mark_archived' => 'archived',
    ];
    if (! isset($map[$action])) {
        return $redirect;
    }

    $status = $map[$action];
    $updated = 0;
    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id < 1 || ! current_user_can('edit_post', $post_id)) {
            continue;
        }
        $post = get_post($post_id);
        if (! $post instanceof WP_Post || $post->post_type !== 'nw_contact_request') {
            continue;
        }
        update_post_meta($post_id, '_nw_contact_status', $status);
        $updated++;
    }

    $redirect = remove_query_arg('nw_contact_updated', $redirect);

    return add_query_arg('nw_contact_updated', $updated, $redirect);
}

/**
 * Include email / phone / name in admin search.
 */
function nw_fuel_contact_request_posts_search(string $search, WP_Query $query): string
{
    global $wpdb;

    if ($search === '' || ! is_admin() || $query->get('post_type') !== 'nw_contact_request') {
        return $search;
    }
    if (! $query->is_main_query() && ! $query->get('nw_fuel_contact_export')) {
        return $search;
    }

    $term = $query->get('s');
    if (! is_string($term) || $term === '') {
        return $search;
    }

    $like  = '%' . $wpdb->esc_like($term) . '%';
    $extra = $wpdb->prepare(
        " OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} nwpm WHERE nwpm.post_id = {$wpdb->posts}.ID AND nwpm.meta_key IN ('_nw_contact_email','_nw_contact_phone','_nw_contact_first_name','_nw_contact_last_name','_nw_contact_product','_nw_contact_part','_nw_contact_service') AND nwpm.meta_value LIKE %s)",
        $like
    );

    $pos = strrpos($search, ')');
    if ($pos === false) {
        return $search . $extra;
    }

    return substr($search, 0, $pos) . $extra . substr($search, $pos);
}

/**
 * @param array<string, array<int, string>> $messages
 * @return array<string, array<int, string>>
 */
function nw_fuel_contact_request_updated_messages(array $messages): array
{
    $messages['nw_contact_request'] = [
        0  => '',
        1  => __('Contact request updated.', 'nw-fuel'),
        4  => __('Contact request updated.', 'nw-fuel'),
        6  => __('Contact request saved.', 'nw-fuel'),
        7  => __('Contact request saved.', 'nw-fuel'),
    ];

    return $messages;
}

/**
 * @param array<string, array<string, string>> $messages
 * @param array<string, int> $counts
 * @return array<string, array<string, string>>
 */
function nw_fuel_contact_request_bulk_messages(array $messages, array $counts): array
{
    $messages['nw_contact_request'] = [
        'updated'   => _n('%s request updated.', '%s requests updated.', $counts['updated'] ?? 0, 'nw-fuel'),
        'deleted'   => _n('%s request permanently deleted.', '%s requests permanently deleted.', $counts['deleted'] ?? 0, 'nw-fuel'),
        'trashed'   => _n('%s request moved to Trash.', '%s requests moved to Trash.', $counts['trashed'] ?? 0, 'nw-fuel'),
        'untrashed' => _n('%s request restored from Trash.', '%s requests restored from Trash.', $counts['untrashed'] ?? 0, 'nw-fuel'),
    ];

    return $messages;
}

/**
 * mailto: link with topic and original message.
 */
function nw_fuel_contact_request_mailto(int $post_id): string
{
    $email   = (string) get_post_meta($post_id, '_nw_contact_email', true);
    $source  = nw_fuel_contact_request_source($post_id);
    $post    = get_post($post_id);
    $label   = nw_fuel_contact_request_sources()[$source];
    $part    = (string) get_post_meta($post_id, '_nw_contact_part', true);
    $product = (string) get_post_meta($post_id, '_nw_contact_product', true);
    $service = (string) get_post_meta($post_id, '_nw_contact_service', true);
    if ($source === 'product_quote' && ($part !== '' || $product !== '')) {
        $label .= ' — ' . ($part !== '' ? $part : $product);
    } elseif ($source === 'service_quote' && $service !== '') {
        $label .= ' — ' . $service;
    } elseif ($source === 'contact') {
        $label = nw_fuel_contact_subject_label((string) get_post_meta($post_id, '_nw_contact_subject', true));
    }

    $body = '';
    if ($post instanceof WP_Post && $post->post_content !== '') {
        $body = "\n\n---\n" . $post->post_content;
    }

    return 'mailto:' . $email . '?subject=' . rawurlencode('Re: ' . $label) . '&body=' . rawurlencode($body);
}

/**
 * Count unread (status new) published requests.
 */
function nw_fuel_contact_request_unread_count(): int
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s
               AND (pm.meta_value = %s OR pm.post_id IS NULL)",
            '_nw_contact_status',
            'nw_contact_request',
            'publish',
            'new'
        )
    );

    return (int) $count;
}
