# NW Fuel WordPress Theme

Custom WooCommerce-ready theme for [NW Fuel Injection Services](https://nwfuel.ca). Pixel-perfect migration of the static PHP site into an editable WordPress installation.

## Migration plan (executed)

| Step | Status |
|------|--------|
| 1. Inventory static routes, includes, CSS/JS, JSON data | Done |
| 2. Theme scaffold (`header`/`footer`/`functions`/templates) | Done |
| 3. Port assets with original class names (no redesign) | Done (synced to static) |
| 4. CPTs: Services + Technical Pages; Products via WooCommerce CPT | Done |
| 5. Theme Settings + Customizer for global/home copy | Done |
| 6. Forms → `admin-post.php` + `wp_mail` | Done |
| 7. Seed importer from `seed-data/` | Done |
| 8. WooCommerce catalog-only (no cart/checkout) | Done |
| 9. Fallbacks: `archive.php`, `404.php`, widgets | Done |
| 10. SEO meta + JSON-LD schema in `<head>` | Done (v1.5) |
| 11. Primary/Footer menus via `wp_nav_menu` | Done (v1.5) |

### Architecture decisions

- **Products** use the WooCommerce `product` post type (a CPT) in **catalog-only** mode: purchasable is forced off, cart/checkout redirect away. This satisfies editable product content *and* future commerce without a second product model.
- **Technical Pages** admin label maps to CPT `nw_tech_resource` with public URL `/technical-resources/` (matches the live static site).
- **Services** CPT `nw_service` supports menu order, featured image, SEO meta, pricing, detail sections, video, and FAQs.
- Frontend CSS/JS is the static site’s design system; WordPress only swaps data sources.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- [WooCommerce](https://woocommerce.com/) (required for the parts catalog; admin notice if missing)

No Elementor, Bootstrap, Tailwind, or page builders.

## Installation

1. Upload `nw-fuel` to `wp-content/themes/` (or install the zip).
2. Activate **NW Fuel**.
3. Install and activate **WooCommerce**.
4. **Tools → NW Fuel Import** → **Run Import**.
5. **Settings → Permalinks** → Save.
6. **Settings → Reading**: Front page = **Home**, Posts page = **Blog**.
7. WooCommerce shop page = **Products** (`/products/`).
8. Configure SMTP so contact/quote forms deliver.

## Where to edit content

| Content | Admin location |
|--------|----------------|
| Logo, phone, email, address, social, homepage copy | **Appearance → Customize** (NW Fuel sections) |
| Business + JSON maps (images, About, FAQs) | **Appearance → NW Fuel Settings** |
| Products | WooCommerce → Products |
| Specs / part # / FAQs | Product → NW Fuel Product Details |
| Services | **Services** → edit any service → **Service Page Content** box (Add/Remove rows; no JSON) |
| Technical pages | Technical Pages CPT |
| Blog | Posts |
| About / Contact | Pages |
| Menus | Appearance → Menus (Primary, Footer) |

## URL map

| Route | Source |
|-------|--------|
| `/` | Front page |
| `/products/` | WooCommerce shop |
| `/products/{slug}/` | Product |
| `/services/` | Services archive |
| `/services/{slug}/` | Service |
| `/technical-resources/` | Technical Pages archive |
| `/technical-resources/{slug}/` | Technical Page |
| `/blog/` | Posts page |
| `/about/`, `/contact/` | Pages |

## Development checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/main.js
```

## File structure

```
nw-fuel/
├── style.css
├── functions.php
├── front-page.php / page.php / single.php / home.php
├── archive.php / 404.php
├── page-about.php / page-contact.php
├── archive-nw_service.php / single-nw_service.php
├── archive-nw_tech_resource.php / single-nw_tech_resource.php
├── woocommerce/          # Product archive & single (design preserved)
├── inc/                  # Setup, Customizer, CPTs, forms, importer
├── template-parts/       # Hero, CTA, FAQ, cards, breadcrumbs
├── assets/css|js|img/    # Legacy design system
└── seed-data/            # Import JSON (synced from static data/)
```
