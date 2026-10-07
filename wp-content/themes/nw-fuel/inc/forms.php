<?php
/**
 * Contact and quote form handlers.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_post_nopriv_nw_fuel_contact', 'nw_fuel_handle_contact_form');
add_action('admin_post_nw_fuel_contact', 'nw_fuel_handle_contact_form');
add_action('admin_post_nopriv_nw_fuel_quote', 'nw_fuel_handle_quote_form');
add_action('admin_post_nw_fuel_quote', 'nw_fuel_handle_quote_form');
add_action('admin_post_nopriv_nw_fuel_newsletter', 'nw_fuel_handle_newsletter_form');
add_action('admin_post_nw_fuel_newsletter', 'nw_fuel_handle_newsletter_form');

/**
 * Handle contact form submission.
 */
function nw_fuel_handle_contact_form(): void
{
    $redirect = nw_fuel_page_url('contact');

    if (! isset($_POST['nw_fuel_contact_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_contact_nonce'])), 'nw_fuel_contact')) {
        wp_safe_redirect(add_query_arg('contact', 'error', $redirect));
        exit;
    }

    $first   = sanitize_text_field(wp_unslash($_POST['firstName'] ?? ''));
    $last    = sanitize_text_field(wp_unslash($_POST['lastName'] ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone   = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    if ($first === '' || $last === '' || $email === '' || $subject === '' || $message === '' || ! is_email($email)) {
        wp_safe_redirect(add_query_arg('contact', 'error', $redirect));
        exit;
    }

    $business = nw_fuel_business();
    $to       = sanitize_email($business['email'] ?? get_option('admin_email'));
    $headers  = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email];

    $body = "Contact form submission\n\n";
    $body .= "Name: {$first} {$last}\n";
    $body .= "Email: {$email}\n";
    $body .= "Phone: {$phone}\n";
    $body .= "Subject: {$subject}\n\n";
    $body .= "Message:\n{$message}\n";

    $sent = wp_mail($to, '[NW Fuel] Contact: ' . $subject, $body, $headers);
    $saved = nw_fuel_store_contact_request([
        'source'     => 'contact',
        'first'      => $first,
        'last'       => $last,
        'email'      => $email,
        'phone'      => $phone,
        'subject'    => $subject,
        'message'    => $message,
        'email_sent' => $sent,
    ]);

    wp_safe_redirect(add_query_arg('contact', ($saved > 0 || $sent) ? 'success' : 'error', $redirect));
    exit;
}

/**
 * Handle product/service quote form submission.
 */
function nw_fuel_handle_quote_form(): void
{
    $redirect = wp_get_referer() ?: nw_fuel_page_url('contact');

    if (! isset($_POST['nw_fuel_quote_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_quote_nonce'])), 'nw_fuel_quote')) {
        wp_safe_redirect(add_query_arg('quote', 'error', $redirect));
        exit;
    }

    $name     = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email    = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone    = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $note     = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
    $quantity = absint($_POST['quantity'] ?? 1);
    $product  = sanitize_text_field(wp_unslash($_POST['product'] ?? ''));
    $part     = sanitize_text_field(wp_unslash($_POST['partNumber'] ?? ''));
    $service  = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));
    $code     = sanitize_text_field(wp_unslash($_POST['productCode'] ?? ''));
    $product_id = absint($_POST['productId'] ?? 0);
    if ($product_id <= 0 && $part !== '' && function_exists('nw_fuel_find_product_id_by_part_number')) {
        $product_id = nw_fuel_find_product_id_by_part_number($part);
    }
    $price_snap = ($product !== '' && $product_id > 0 && function_exists('nw_fuel_product_quote_price_snapshot'))
        ? nw_fuel_product_quote_price_snapshot($product_id)
        : ['price' => 0.0, 'type' => '', 'label' => '', 'partner_level' => 0];
    $name_bits = explode(' ', $name, 2);
    $first     = sanitize_text_field((string) ($name_bits[0] ?? $name));
    $last      = sanitize_text_field((string) ($name_bits[1] ?? ''));

    if ($name === '' || $email === '' || ! is_email($email)) {
        wp_safe_redirect(add_query_arg('quote', 'error', $redirect));
        exit;
    }

    $business = nw_fuel_business();
    $to       = sanitize_email($business['email'] ?? get_option('admin_email'));
    $headers  = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email];
    $label    = $product !== '' ? 'Product Quote' : 'Service Quote';

    $body = "{$label} request\n\n";
    $body .= "Name: {$name}\n";
    $body .= "Email: {$email}\n";
    $body .= "Phone: {$phone}\n";
    if ($product !== '') {
        $body .= "Product: {$product}\n";
        $body .= "Part Number: {$part}\n";
        $body .= "Quantity: {$quantity}\n";
        if ((float) $price_snap['price'] > 0) {
            $price_line = function_exists('nw_fuel_format_price')
                ? nw_fuel_format_price((float) $price_snap['price'])
                : number_format((float) $price_snap['price'], 2);
            $type_line  = (string) $price_snap['label'];
            $body .= "Price: {$price_line}";
            $body .= $type_line !== '' ? " ({$type_line})\n" : "\n";
        }
        if ($code !== '') {
            $body .= "Internal Product Code: {$code}\n";
        }
    }
    if ($service !== '') {
        $body .= "Service: {$service}\n";
    }
    $body .= "\nNotes:\n{$note}\n";

    $sent = wp_mail($to, '[NW Fuel] ' . $label, $body, $headers);
    $saved = nw_fuel_store_contact_request([
        'source'       => $product !== '' ? 'product_quote' : 'service_quote',
        'first'        => $first,
        'last'         => $last,
        'email'        => $email,
        'phone'        => $phone,
        'subject'      => $product !== '' ? 'product-quote' : 'service-quote',
        'message'      => $note,
        'product'      => $product,
        'part'         => $part,
        'quantity'      => $quantity,
        'product_code'  => $code,
        'product_id'    => $product_id,
        'price'         => $price_snap['price'],
        'price_type'    => $price_snap['type'],
        'price_label'   => $price_snap['label'],
        'partner_level' => $price_snap['partner_level'],
        'service'      => $service,
        'email_sent'   => $sent,
    ]);

    wp_safe_redirect(add_query_arg('quote', ($saved > 0 || $sent) ? 'success' : 'error', $redirect));
    exit;
}

/**
 * Homepage newsletter signup → email info@nwfuel.ca.
 */
function nw_fuel_handle_newsletter_form(): void
{
    $home = home_url('/');

    $redirect = static function (string $status) use ($home): void {
        wp_safe_redirect(add_query_arg('newsletter', $status, $home) . '#email-updates');
        exit;
    };

    if (! isset($_POST['nw_fuel_newsletter_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nw_fuel_newsletter_nonce'])), 'nw_fuel_newsletter')) {
        $redirect('error');
    }

    // Hidden honeypot — bots fill this; humans never see it.
    $honeypot = trim((string) wp_unslash($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        $redirect('success');
    }

    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    if ($email === '' || ! is_email($email)) {
        $redirect('error');
    }

    $to      = 'info@nwfuel.ca';
    $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email];
    $body    = "Newsletter signup from the homepage.\n\n";
    $body   .= "Email: {$email}\n";
    $body   .= 'Submitted: ' . gmdate('Y-m-d H:i:s') . " UTC\n";

    $sent  = wp_mail($to, '[NW Fuel] Newsletter signup', $body, $headers);
    $saved = function_exists('nw_fuel_store_newsletter_signup') ? nw_fuel_store_newsletter_signup($email) : 0;
    $redirect(($saved > 0 || $sent) ? 'success' : 'error');
}
