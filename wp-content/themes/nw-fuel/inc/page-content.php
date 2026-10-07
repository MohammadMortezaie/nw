<?php
/**
 * Editable page content helpers (About, Contact, archives).
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Get stored page content array.
 *
 * @return array<string, mixed>
 */
function nw_fuel_get_page_content(int $post_id): array
{
    $raw = get_post_meta($post_id, '_nw_page_content', true);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    if (is_array($raw)) {
        return $raw;
    }
    return [];
}

/**
 * Save page content array.
 *
 * @param array<string, mixed> $content
 */
function nw_fuel_update_page_content(int $post_id, array $content): void
{
    update_post_meta($post_id, '_nw_page_content', nw_fuel_json_encode_meta($content));
}

/**
 * Merge defaults with stored content.
 *
 * @param array<string, mixed> $defaults
 * @return array<string, mixed>
 */
function nw_fuel_resolve_page_content(int $post_id, array $defaults): array
{
    $stored = nw_fuel_get_page_content($post_id);
    return array_replace_recursive($defaults, $stored);
}

/**
 * About page default copy (static about.php).
 *
 * @return array<string, mixed>
 */
function nw_fuel_about_content_defaults(): array
{
    $company = nw_fuel_company();

    return [
        'hero_eyebrow'     => 'Since 1968',
        'hero_title'       => 'About NW Fuel',
        'hero_desc'        => 'Family owned in Surrey, BC. Bosch authorized shop built on honest diagnostics and long term relationships with diesel repair shops across Canada.',
        'hero_cta_primary' => 'Request a Quote',
        'hero_cta_secondary' => 'Our Services',
        'stats'            => [
            ['value' => '1968', 'label' => 'Founded'],
            ['value' => '14', 'label' => 'Team members'],
            ['value' => '2', 'label' => 'BC locations'],
            ['value' => 'Bosch', 'label' => 'Authorized'],
        ],
        'story_eyebrow'    => 'Our Story',
        'story_title'      => "From New Westminster to Canada's Diesel Injection Leader",
        'story_p1'         => 'NW Fuel Injection Services Ltd. was founded in 1968 by Hans Rusch in New Westminster, BC. Today, with 14 employees and a sister location on Vancouver Island, we serve repair shops across Canada.',
        'story_p2'         => 'Our mission is to provide quality, cost-effective repairs and competitively priced parts with professional service backed by expert technical support to the diesel industry.',
        'history_cta_eyebrow' => 'Since 1968',
        'history_cta_year'    => '1968',
        'history_cta_title'   => '50+ Years in the Diesel Industry',
        'history_cta_text'    => 'From Hans Rusch’s one-room shop on the New Westminster waterfront to a Bosch authorized facility in Surrey — the people, the shops, and the machines that built NW Fuel.',
        'history_cta_button'  => 'Explore Our History',
        'values_eyebrow'   => 'Values',
        'values_title'     => 'What We Stand For',
        'values'           => $company['companyValues'] ?? [],
        'certs_eyebrow'    => 'Certifications',
        'certs_title'      => 'Factory Authorizations',
        'certifications'   => $company['certifications'] ?? [],
        'workshop_eyebrow' => 'Workshop',
        'workshop_title'   => 'Equipment and Facilities',
        'equipment'        => $company['workshopEquipment'] ?? [],
        'team_eyebrow'     => 'Team',
        'team_title'       => 'The People Behind Diesel Done Right',
        'team'             => $company['team'] ?? [],
        'timeline_eyebrow' => 'History',
        'timeline_title'   => 'Company Timeline',
        'timeline'         => $company['timeline'] ?? [],
        'trust_eyebrow'    => 'Trust',
        'trust_title'      => 'Why Customers Trust Us',
        'trust'            => [
            ['value' => '50+', 'label' => 'Years in business'],
            ['value' => '12 mo', 'label' => 'Unlimited km warranty'],
            ['value' => '1,500+', 'label' => 'Parts in stock'],
        ],
        'cta_title'        => 'Work With a Shop That Knows Diesel Injection',
        'cta_description'  => 'Call our Surrey team for injector testing, pump rebuilds, and OEM parts. Bosch authorized since 1968.',
    ];
}

/**
 * Contact page default copy (static contact.php).
 *
 * @return array<string, mixed>
 */
function nw_fuel_contact_content_defaults(): array
{
    $settings = nw_fuel_get_settings();

    return [
        'hero_eyebrow'          => 'Surrey, BC',
        'hero_title'            => 'Contact Us',
        'hero_desc'             => 'Reach our Surrey shop for injector work, pump rebuilds, parts quotes, and technical questions. We respond to messages within one business day.',
        'hero_cta_email'        => 'Email the Shop',
        'stats'                 => [
            ['value' => '', 'label' => 'Surrey shop phone', 'use_phone' => true],
            ['value' => 'Mon to Fri', 'label' => '8:00 AM to 4:30 PM'],
            ['value' => '2', 'label' => 'BC locations'],
            ['value' => '1 day', 'label' => 'Message response'],
        ],
        'details_eyebrow'       => 'Get in Touch',
        'details_title'         => 'Business Information',
        'address_label'         => 'Workshop Address',
        'address_note'          => 'North side of Highway 1, beside Finning',
        'phone_email_label'     => 'Phone and Email',
        'hours_label'           => 'Business Hours',
        'hours_note'            => 'Closed on all statutory holidays',
        'island_label'          => 'Island Diesel, Nanaimo',
        'island_address'        => "#8, 1975 Boxwood Road\nNanaimo, BC",
        'island_phone'          => '(250) 741-0051',
        'island_website'        => 'https://islanddiesel.ca',
        'island_website_label'  => 'islanddiesel.ca',
        'form_eyebrow'          => 'Message',
        'form_title'            => 'Send Us a Message',
        'form_success_title'    => 'Message Sent',
        'form_success_text'     => 'Thank you for contacting NW Fuel. Our team will respond within one business day.',
        'map_eyebrow'           => 'Location',
        'map_title'             => 'Find Our Surrey Shop',
        'map_embed_url'         => 'https://maps.google.com/maps?q=18940+94+Ave+Surrey+BC&t=&z=15&ie=UTF8&iwloc=&output=embed',
        'faq_eyebrow'           => 'FAQ',
        'faq_title'             => 'Contact Questions',
        'faqs'                  => $settings['contact_faqs'] ?? [],
        'cta_title'             => 'Prefer to Talk to a Technician?',
        'cta_description'       => 'Call our Surrey shop directly for injector testing, pump rebuilds, and parts availability.',
    ];
}

/**
 * Resolved About content for the About page.
 *
 * @return array<string, mixed>
 */
function nw_fuel_about_content(): array
{
    $page = get_page_by_path('about');
    $id   = $page instanceof WP_Post ? $page->ID : 0;
    return nw_fuel_resolve_page_content($id, nw_fuel_about_content_defaults());
}

/**
 * Resolved Contact content.
 *
 * @return array<string, mixed>
 */
function nw_fuel_contact_content(): array
{
    $page = get_page_by_path('contact');
    $id   = $page instanceof WP_Post ? $page->ID : 0;
    return nw_fuel_resolve_page_content($id, nw_fuel_contact_content_defaults());
}

/**
 * Archive / listing page copy defaults.
 *
 * @return array<string, array<string, string>>
 */
function nw_fuel_archive_content_defaults(): array
{
    return [
        'services' => [
            'eyebrow'   => 'Bosch Authorized',
            'title'     => 'Diesel Injection Services',
            'desc'      => 'Injector testing, pump rebuilds, and calibration for mechanical, HEUI, EUI, and common rail systems.',
            'cta'       => 'Request a Quote',
            'catalog_title' => 'What We Do',
            'catalog_desc'  => 'From diagnosis to rebuild, every service is backed by precision test equipment, OEM quality parts, and a 12 month unlimited kilometre warranty.',
        ],
        'products' => [
            'eyebrow' => 'OEM Diesel Parts',
            'title'   => 'Diesel Parts Catalog',
            'desc'    => 'Injectors, pumps, turbos, filters, and component parts. Search by name, part number, or brand, then request a quote. We do not sell through online checkout.',
            'cta'     => 'Request a Quote',
        ],
        'tech' => [
            'eyebrow' => 'Shop Reference',
            'title'   => 'Technical Resources',
            'desc'    => 'Installation guides, filter references, and diesel injection notes for repair shops.',
            'cta'     => 'Request a Quote',
        ],
        'blog' => [
            'eyebrow' => 'From Our Shop',
            'title'   => 'Blog and Technical Insights',
            'desc'    => 'Practical diesel injection articles from our Surrey shop.',
            'cta'     => 'Ask a Question',
        ],
        'gallery' => [
            'eyebrow' => 'From Our Shop',
            'title'   => 'Gallery',
            'desc'    => 'Photos and videos from the NW Fuel shop.',
            'cta'     => 'Contact Us',
        ],
        'catalog' => [
            'eyebrow' => 'Shop References',
            'title'   => 'Catalog',
            'desc'    => 'Bosch, diesel injection, and parts catalogs from our Surrey shop. Open a catalog, enter your email, and download the PDF.',
            'cta'     => 'Contact',
        ],
    ];
}

/**
 * Get archive copy for a key (services|products|tech|blog).
 *
 * @return array<string, string>
 */
function nw_fuel_archive_content(string $key): array
{
    $defaults = nw_fuel_archive_content_defaults();
    $base     = $defaults[$key] ?? [];
    $settings = nw_fuel_get_settings();
    $stored   = is_array($settings['archives'][$key] ?? null) ? $settings['archives'][$key] : [];
    $merged   = array_merge($base, $stored);

    if ($key === 'catalog' && ($merged['desc'] ?? '') === 'Download product and parts catalogs. Enter your email on the PDF page to unlock the file.') {
        $merged['desc'] = (string) ($base['desc'] ?? '');
    }

    foreach (array_keys($base) as $field) {
        $mod = get_theme_mod("nw_fuel_archive_{$key}_{$field}", null);
        if (! is_string($mod) || $mod === '') {
            continue;
        }
        if ($key === 'catalog' && $field === 'desc' && $mod === 'Download product and parts catalogs. Enter your email on the PDF page to unlock the file.') {
            continue;
        }
        $merged[$field] = $mod;
    }

    return $merged;
}

/**
 * Seed About/Contact page meta from defaults (idempotent fill of empty).
 */
function nw_fuel_seed_page_content_meta(): void
{
    $about = get_page_by_path('about');
    if ($about instanceof WP_Post && nw_fuel_get_page_content($about->ID) === []) {
        nw_fuel_update_page_content($about->ID, nw_fuel_about_content_defaults());
    }
    $contact = get_page_by_path('contact');
    if ($contact instanceof WP_Post && nw_fuel_get_page_content($contact->ID) === []) {
        nw_fuel_update_page_content($contact->ID, nw_fuel_contact_content_defaults());
    }
    $history = get_page_by_path('history-of-nw-fuel');
    if ($history instanceof WP_Post && nw_fuel_get_page_content($history->ID) === [] && function_exists('nw_fuel_history_content_defaults')) {
        nw_fuel_update_page_content($history->ID, nw_fuel_history_content_defaults());
    }
}
