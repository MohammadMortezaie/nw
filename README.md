# NW Fuel WordPress Site

This repository contains the custom `nw-fuel` WordPress theme. WordPress core,
third-party plugins, media uploads, generated files, and server configuration are
deliberately excluded.

## Requirements

- WordPress 6.0 or newer
- PHP 8.1 or newer
- WooCommerce

## Local setup

1. Install WordPress in your local development environment.
2. Clone this repository into the WordPress installation directory, or copy
   `wp-content/themes/nw-fuel` into the installation's `wp-content/themes` folder.
3. Install and activate WooCommerce.
4. Activate the **NW Fuel** theme.
5. In WordPress, open **Tools → NW Fuel Import** and run the importer when seed
   content is needed.
6. Save **Settings → Permalinks** once after activation.

See [`wp-content/themes/nw-fuel/README.md`](wp-content/themes/nw-fuel/README.md)
for theme architecture, routes, and development checks.

## What is not in Git

- `wp-config.php` and all credentials/secrets
- The WordPress database and database-managed settings
- Media uploads and generated inventory files
- WordPress core and third-party plugins
- Server logs, caches, backups, and hosting-specific files

The **Code Snippets** plugin stores snippets in the WordPress database. Export any
snippets that are part of the application and add them to the custom theme or a
dedicated custom plugin before relying on this repository as their source of
truth.

## Collaboration workflow

Create a branch for each change, push it to GitHub, and open a pull request for
review. Do not commit production databases, uploads, `.env` files, API keys, or
copies of `wp-config.php`.
