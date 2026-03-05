# SteelRoot Modules Guide

This document explains how modules are structured, registered, configured,
and extended in SteelRoot CMS.

---

## What Is a Module

A module is a **self-contained feature package**.

Examples:

- Articles (categories, author, JSON-LD, tags)
- Gallery (categories, upload subfolders, lightbox)
- Pages
- Users
- Popups
- FAQ
- Tags / Search
- Shop (premium)

Modules can be:
- enabled / disabled
- migrated independently
- configured independently

---

## Module Directory Structure

modules/Example/
├── Module.php
├── config.php
├── routes.php
├── migrations/
├── Controllers/
├── Services/
├── Providers/ # Schema providers for JSON-LD (optional)
├── views/
├── lang/
├── assets/
└── schema.json (optional)

---

## Module Entry Point

### `Module.php`

Each module must define a Module class:

```php
class ExampleModule
{
    public function register(Container $c): void {}
    public function boot(Container $c): void {}
}
register():

register services

register settings

boot():

register routes

attach hooks/events

Routes
Defined in routes.php or inside boot()

Public and admin routes supported

Admin routes respect admin_prefix

Example:

$router->get('/example', ExampleController::class.'@index');
$router->group($adminPrefix, function () {
    $router->get('/example', AdminExampleController::class.'@index');
});
Views
Located in modules/{Module}/views

Resolved automatically by Renderer

Use native PHP templates

All text via __()

Language Files

modules/Example/lang/en.php
modules/Example/lang/ru.php
Return associative arrays.

Module Settings
Stored in global settings table

Namespaced automatically

Usage:

$settings->get('example.enabled');
$settings->set('example.limit', 10);
Modules may ship default values in config.php.

Migrations
Each module has its own migrations/

Migration state tracked per module

Can be run:

via admin UI

via /migrate

via CLI

Assets
Optional

JS / CSS loaded only if module is enabled

Should respect theme tokens

No hardcoded colors

Schema Providers (Optional)
Modules can generate JSON-LD structured data for SEO:

Create Provider class in `Providers/`

Generate Schema.org markup (Article, Product, ImageObject, etc.)

Use `JsonLdRenderer::merge()` to combine with Organization schema

Pass to layout via `meta['jsonld']`

Example: `modules/Articles/Providers/ArticleSchemaProvider.php`

Auto-CRUD (Optional)
Modules may define schema.json to generate:

CRUD admin UI

Migrations

Basic views

Used for simple content modules (FAQ, Pages).

Module Interactions
Modules may:

read data from other modules

listen to events (e.g. user.registered)

extend search, sitemap, homepage

But should not:

modify core files

assume another module is enabled

Best Practices
One responsibility per module

No direct SQL outside services

No inline JS/CSS

Graceful degradation if disabled

Clean uninstall (optional down migrations)

## Auto-Discovery Files

Modules can expose additional integration points via auto-discovered files:

### `sitemap.php`
Returning an array of `[loc, lastmod, changefreq, priority]` rows. Discovered by `Kernel` to build `sitemap.xml`.

### `home_block.php`
Returning:
```php
return [
    'settings_key'  => 'home_show_{block}',
    'order_key'     => 'home_order_{block}',
    'default_order' => 3,
    'provider'      => function(Database $db, array $settings): array { ... },
    'view'          => __DIR__ . '/views/blocks/{block}.php',
];
```
Discovered by `HomeController::loadModuleBlocks()` to inject content into the homepage.

### `search_provider.php`
Returning a `SearchProvider` instance. Discovered by `bootstrap/search_providers.php`.

---

## locale_mode Pattern

Admin forms should respect the `locale_mode` setting (`en`/`ru`/`multi`):

```php
// In controller constructor:
$this->localeMode = $settings->get('locale_mode', 'multi');
// Pass to renderer:
'localeMode' => $this->localeMode
```

```php
// In view:
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
```

Field groups: `if ($showEn && $showRu)` / `elseif ($showEn)` / `else`.

---

## Example Modules

Articles + Gallery share:

- unified tag system (`tags` + `taggables`)
- unified search integration
- category system pattern (separate tables, admin CRUD, public routes, sitemap entries)

Articles provides:
- Schema.org Article structured data via JSON-LD (`ArticleSchemaProvider` in `Providers/`)
- Author field linked to Users module
- locale_mode-aware admin forms

Gallery provides:
- Upload subfolders per category
- Folder picker on upload form
- locale_mode-aware upload form with file preview

Popups works independently of content modules.

---

## SEO Integration
Modules can extend SEO via:

- Sitemap provider (`sitemap.php` — add URLs to sitemap.xml)
- Search provider (`search_provider.php` — content indexed in search)
- Schema provider (`Providers/` — JSON-LD structured data)
- Meta resolver (custom meta tags)

See `core/Meta/` for infrastructure.
