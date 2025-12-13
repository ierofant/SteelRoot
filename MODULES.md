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
bash
Copy code
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

Users integrates as author provider

Popups works independently of content modules
