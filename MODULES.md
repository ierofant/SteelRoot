# SteelRoot Modules Guide

This document explains how modules are structured, registered, configured,
and extended in SteelRoot CMS.

---

## What Is a Module

A module is a **self-contained feature package**.

Examples:

- Articles
- Gallery
- Pages
- Users
- Popups
- FAQ
- TAGS
- SEARCHE
- Shop (future)
- Forum (future)

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

Example Modules
Articles + Gallery share:

unified tag system (tags + taggables)

unified search integration

Articles provides:

Schema.org Article structured data via JSON-LD

`ArticleSchemaProvider` in `Providers/`

Auto-generates headline, dates, description, image

Users integrates as author provider

Popups works independently of content modules

SEO Integration
Modules can extend SEO via:

Sitemap provider (add URLs to sitemap.xml)

Search provider (content indexed in search)

Schema provider (JSON-LD structured data)

Meta resolver (custom meta tags)

See `core/Meta/` for infrastructure.
