<?php
/**
 * History of NW Fuel page — timeline stories and page helpers.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', 'nw_fuel_ensure_history_page', 30);

/**
 * Default copy for the History page hero / CTA.
 *
 * @return array<string, mixed>
 */
function nw_fuel_history_content_defaults(): array
{
    return [
        'hero_eyebrow'     => '1968 — Today',
        'hero_title'       => 'History of NW Fuel',
        'hero_desc'        => 'From a one-room shop on the New Westminster waterfront to a Bosch authorized diesel injection facility in Surrey, BC.',
        'hero_cta_primary' => 'Back to About Us',
        'cta_title'        => 'Work With a Shop That Knows Diesel Injection',
        'cta_description'  => 'Call our Surrey team for injector testing, pump rebuilds, and OEM parts. Bosch authorized since 1968.',
    ];
}

/**
 * Resolved History page copy.
 *
 * @return array<string, mixed>
 */
function nw_fuel_history_content(): array
{
    $page = get_page_by_path('history-of-nw-fuel');
    $id   = $page instanceof WP_Post ? $page->ID : 0;
    return nw_fuel_resolve_page_content($id, nw_fuel_history_content_defaults());
}

/**
 * Timeline stories from seed data (newest first, matching nwfuel.ca).
 *
 * @return list<array{year: string, date: string, title: string, image: string, body: string}>
 */
function nw_fuel_history_stories(): array
{
    $raw = function_exists('nw_fuel_seed_json') ? nw_fuel_seed_json('history.json') : [];
    $out = [];
    foreach ($raw as $row) {
        if (! is_array($row)) {
            continue;
        }
        $title = trim((string) ($row['title'] ?? ''));
        $body  = trim((string) ($row['body'] ?? ''));
        if ($title === '' && $body === '') {
            continue;
        }
        $image = trim((string) ($row['image'] ?? ''));
        $out[] = [
            'year'  => (string) ($row['year'] ?? ''),
            'date'  => (string) ($row['date'] ?? ''),
            'title' => $title,
            'image' => $image !== '' ? nw_fuel_asset_url($image) : '',
            'body'  => $body,
        ];
    }

    return $out;
}

/**
 * Unique years in timeline order.
 *
 * @param list<array{year: string}> $stories
 * @return list<string>
 */
function nw_fuel_history_years(array $stories): array
{
    $years = [];
    foreach ($stories as $story) {
        $year = (string) ($story['year'] ?? '');
        if ($year !== '' && ! in_array($year, $years, true)) {
            $years[] = $year;
        }
    }

    return $years;
}

/**
 * Create the History page if it does not exist yet.
 */
function nw_fuel_ensure_history_page(): void
{
    if (wp_installing()) {
        return;
    }

    $page = get_page_by_path('history-of-nw-fuel');
    if ($page instanceof WP_Post) {
        if (get_page_template_slug($page->ID) === '') {
            update_post_meta($page->ID, '_wp_page_template', 'page-history.php');
        }
        return;
    }

    $id = wp_insert_post([
        'post_title'   => 'History of NW Fuel',
        'post_name'    => 'history-of-nw-fuel',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ], true);

    if (is_wp_error($id) || ! $id) {
        return;
    }

    update_post_meta((int) $id, '_wp_page_template', 'page-history.php');
    update_post_meta((int) $id, '_nw_meta_title', 'History of NW Fuel');
    update_post_meta(
        (int) $id,
        '_nw_meta_description',
        'The history of NW Fuel Injection Services — from Hans Rusch’s 1968 shop in New Westminster to a Bosch authorized diesel injection facility in Surrey, BC.'
    );
    nw_fuel_update_page_content((int) $id, nw_fuel_history_content_defaults());
}

/**
 * Split story body into paragraphs.
 *
 * @return list<string>
 */
function nw_fuel_history_paragraphs(string $body): array
{
    $parts = preg_split("/\n\s*\n/", $body) ?: [];
    $out   = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out !== [] ? $out : ($body !== '' ? [$body] : []);
}
