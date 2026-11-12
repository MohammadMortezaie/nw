<?php
/**
 * Product-only image uploads, optimization, and focal positioning.
 *
 * The upload filter exits unless a signed request comes from the product photo
 * media modal, so uploads in every other admin section remain unchanged.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const NW_FUEL_PRODUCT_IMAGE_QUALITY       = 82;
const NW_FUEL_PRODUCT_IMAGE_MAX_DIMENSION = 2400;

add_filter('wp_handle_upload_prefilter', 'nw_fuel_optimize_product_media_upload');
add_action('add_attachment', 'nw_fuel_mark_optimized_product_attachment');

/**
 * Saved focal positions keyed by attachment ID.
 *
 * @return array<int, array{x: float, y: float}>
 */
function nw_fuel_product_image_positions(int $product_id): array
{
    $saved = get_post_meta($product_id, '_nw_product_image_positions', true);
    if (! is_array($saved)) {
        return [];
    }

    $positions = [];
    foreach ($saved as $attachment_id => $position) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id < 1 || ! is_array($position)) {
            continue;
        }
        $positions[$attachment_id] = [
            'x' => nw_fuel_clamp_product_image_position($position['x'] ?? 50),
            'y' => nw_fuel_clamp_product_image_position($position['y'] ?? 50),
        ];
    }

    return $positions;
}

/**
 * Focal position for one product attachment.
 *
 * @return array{x: float, y: float}
 */
function nw_fuel_product_image_position(int $product_id, int $attachment_id): array
{
    $positions = nw_fuel_product_image_positions($product_id);
    return $positions[$attachment_id] ?? ['x' => 50.0, 'y' => 50.0];
}

/**
 * Clamp a focal position to a valid CSS percentage.
 */
function nw_fuel_clamp_product_image_position(mixed $value): float
{
    if (! is_numeric($value)) {
        return 50.0;
    }
    return max(0.0, min(100.0, round((float) $value, 2)));
}

/**
 * Inline object-position declaration for product image frames.
 */
function nw_fuel_product_image_position_style(int $product_id, int $attachment_id): string
{
    if ($attachment_id < 1) {
        return '';
    }
    $position = nw_fuel_product_image_position($product_id, $attachment_id);
    return sprintf('object-position:%s%% %s%%;', nw_fuel_css_number($position['x']), nw_fuel_css_number($position['y']));
}

/**
 * Format a compact, locale-independent CSS number.
 */
function nw_fuel_css_number(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

/**
 * Product gallery data with attachment IDs and saved focal positions.
 * Existing/remote images remain compatible and default to centered.
 *
 * @return list<array{id: int, url: string, x: float, y: float, positioned: bool}>
 */
function nw_fuel_product_gallery_items(int $product_id, string $size = 'large'): array
{
    $items = [];
    $saved_positions = nw_fuel_product_image_positions($product_id);
    foreach (nw_fuel_product_photo_ids($product_id) as $attachment_id) {
        $url = wp_get_attachment_image_url($attachment_id, $size);
        if (! $url) {
            continue;
        }
        $position = nw_fuel_product_image_position($product_id, $attachment_id);
        $items[] = [
            'id'  => $attachment_id,
            'url' => (string) $url,
            'x'   => $position['x'],
            'y'   => $position['y'],
            'positioned' => isset($saved_positions[$attachment_id]),
        ];
    }

    if ($items === []) {
        $urls   = [];
        $remote = (string) get_post_meta($product_id, '_nw_remote_image', true);
        if ($remote !== '') {
            $urls[] = nw_fuel_resolve_image_url($remote);
        }
        foreach (nw_fuel_json_meta($product_id, '_nw_remote_gallery') as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = nw_fuel_resolve_image_url($url);
            }
        }
        foreach (array_slice(array_values(array_unique(array_filter($urls))), 0, NW_FUEL_PRODUCT_PHOTOS_MAX) as $url) {
            $items[] = ['id' => 0, 'url' => $url, 'x' => 50.0, 'y' => 50.0, 'positioned' => false];
        }
    }

    if ($items === []) {
        $items[] = ['id' => 0, 'url' => nw_fuel_product_placeholder_url(), 'x' => 50.0, 'y' => 50.0, 'positioned' => false];
    }

    return array_slice($items, 0, NW_FUEL_PRODUCT_PHOTOS_MAX);
}

/**
 * Convert uploads from the signed product media modal before WordPress stores
 * them. The uploaded temporary path is retained so core's upload validation
 * continues to work; its bytes are replaced only after the WebP validates.
 *
 * @param array<string, mixed> $file A single WordPress upload file.
 * @return array<string, mixed>
 */
function nw_fuel_optimize_product_media_upload(array $file): array
{
    $product_id = absint($_REQUEST['nw_fuel_product_id'] ?? 0);
    $nonce      = sanitize_text_field(wp_unslash($_REQUEST['nw_fuel_product_upload_nonce'] ?? ''));
    if ($product_id < 1 || $nonce === '' || ! wp_verify_nonce($nonce, 'nw_fuel_product_media_upload')) {
        return $file;
    }

    if (get_post_type($product_id) !== 'product' || ! current_user_can('upload_files') || ! current_user_can('edit_post', $product_id)) {
        $file['error'] = __('You are not allowed to upload images for this product.', 'nw-fuel');
        return $file;
    }

    if (! empty($file['error'])) {
        return $file;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    if (! wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        $file['error'] = __('This server cannot create WebP images. Ask the hosting provider to enable WebP support in GD or Imagick.', 'nw-fuel');
        return $file;
    }

    $source_path  = (string) ($file['tmp_name'] ?? '');
    $source_mime  = wp_get_image_mime($source_path);
    $source_bytes = is_file($source_path) ? (int) filesize($source_path) : 0;
    if (! in_array($source_mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $file['error'] = __('Please upload a valid JPG, PNG, or WebP product image.', 'nw-fuel');
        return $file;
    }

    $editor = wp_get_image_editor($source_path);
    if (is_wp_error($editor)) {
        $file['error'] = __('WordPress could not read this image. Try exporting it again and re-uploading.', 'nw-fuel');
        return $file;
    }

    $size = $editor->get_size();
    if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
        $file['error'] = __('The uploaded image dimensions could not be validated.', 'nw-fuel');
        return $file;
    }

    if ((int) $size['width'] > NW_FUEL_PRODUCT_IMAGE_MAX_DIMENSION || (int) $size['height'] > NW_FUEL_PRODUCT_IMAGE_MAX_DIMENSION) {
        $resized = $editor->resize(NW_FUEL_PRODUCT_IMAGE_MAX_DIMENSION, NW_FUEL_PRODUCT_IMAGE_MAX_DIMENSION, false);
        if (is_wp_error($resized)) {
            $file['error'] = __('The image could not be resized safely.', 'nw-fuel');
            return $file;
        }
    }

    $editor->set_quality(NW_FUEL_PRODUCT_IMAGE_QUALITY);
    $webp_temp = wp_tempnam('nw-product-image.webp');
    if (! is_string($webp_temp) || $webp_temp === '') {
        $file['error'] = __('A temporary file could not be created for image optimization.', 'nw-fuel');
        return $file;
    }

    $saved = $editor->save($webp_temp, 'image/webp');
    if (is_wp_error($saved)) {
        wp_delete_file($webp_temp);
        $file['error'] = __('The product image could not be converted to WebP.', 'nw-fuel');
        return $file;
    }

    $webp_path = (string) ($saved['path'] ?? $webp_temp);
    $webp_info = is_file($webp_path) ? wp_getimagesize($webp_path) : false;
    if (! is_array($webp_info) || ($webp_info['mime'] ?? '') !== 'image/webp' || (int) filesize($webp_path) < 1) {
        wp_delete_file($webp_path);
        if ($webp_path !== $webp_temp) {
            wp_delete_file($webp_temp);
        }
        $file['error'] = __('The converted WebP image failed validation and was not saved.', 'nw-fuel');
        return $file;
    }

    // Keep the PHP-uploaded path for core validation, but replace its original
    // JPG/PNG bytes only after the WebP file has passed validation.
    if (! copy($webp_path, $source_path)) {
        wp_delete_file($webp_path);
        $file['error'] = __('The optimized image could not be finalized.', 'nw-fuel');
        return $file;
    }
    wp_delete_file($webp_path);
    if ($webp_path !== $webp_temp) {
        wp_delete_file($webp_temp);
    }

    $original_name = sanitize_file_name((string) ($file['name'] ?? 'product-image'));
    $base_name     = pathinfo($original_name, PATHINFO_FILENAME) ?: 'product-image';
    $file['name']  = $base_name . '.webp';
    $file['type']  = 'image/webp';
    $file['size']  = (int) filesize($source_path);

    // Available to add_attachment later in this same async upload request.
    $_REQUEST['nw_fuel_product_source_mime']  = $source_mime;
    $_REQUEST['nw_fuel_product_source_bytes'] = $source_bytes;

    return $file;
}

/**
 * Mark attachments created by the signed product media upload.
 */
function nw_fuel_mark_optimized_product_attachment(int $attachment_id): void
{
    $product_id = absint($_REQUEST['nw_fuel_product_id'] ?? 0);
    $nonce      = sanitize_text_field(wp_unslash($_REQUEST['nw_fuel_product_upload_nonce'] ?? ''));
    if ($product_id < 1 || ! wp_verify_nonce($nonce, 'nw_fuel_product_media_upload') || get_post_mime_type($attachment_id) !== 'image/webp') {
        return;
    }

    $path = get_attached_file($attachment_id);
    update_post_meta($attachment_id, '_nw_fuel_product_image', 'yes');
    update_post_meta($attachment_id, '_nw_fuel_source_mime', sanitize_mime_type((string) ($_REQUEST['nw_fuel_product_source_mime'] ?? '')));
    update_post_meta($attachment_id, '_nw_fuel_source_bytes', absint($_REQUEST['nw_fuel_product_source_bytes'] ?? 0));
    update_post_meta($attachment_id, '_nw_fuel_optimized_bytes', is_string($path) && is_file($path) ? (int) filesize($path) : 0);
}
