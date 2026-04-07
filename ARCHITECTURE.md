# SteelRoot v2 Architecture

This document describes the core architectural principles of SteelRoot CMS,
its internal structure, and extension points.

SteelRoot is designed as a **modular, self-hosted PHP system** with clear
boundaries between the core and feature modules.

---

## Core Principles

1. **Explicit over implicit**
   - No magic autowiring
   - No hidden globals
   - All dependencies are registered explicitly

2. **Core is stable, modules are replaceable**
   - Core contains infrastructure only
   - Business logic lives in modules

3. **Shared hosting first**
   - No background daemons required
   - No system-level dependencies
   - Everything runs via HTTP + optional CLI

4. **Readable PHP**
   - No heavy frameworks
   - Plain PHP classes and templates
   - Easy debugging without IDE magic

---

## High-Level Structure

public_html/
├── index.php
├── prefilter.php
├── .htaccess
│
├── core/ # Framework kernel
│   ├── Container.php
│   ├── Router.php
│   ├── Database.php
│   └── Meta/ # JSON-LD & meta infrastructure
├── app/ # App-level controllers & views
├── modules/ # Feature modules
├── database/ # Migrations & runner
├── assets/ # SCSS / CSS / JS
├── storage/ # Runtime data (cache, logs, uploads)
└── installer.php # One-time installer

In v2, `installer.php` is no longer tied to a small hardcoded migration list.
It bootstraps core migrations from `database/migrations` and auto-discovers
module migrations from `modules/*/migrations`.

---

## Request Lifecycle

1. **HTTP request**
2. `.htaccess` rewrites to `index.php`
3. `prefilter.php`:
   - blocks forbidden extensions
   - basic rate-limit / security checks
4. `index.php` bootstraps:
   - autoload
   - container
   - router
   - enabled modules
5. Router resolves:
   - middleware
   - controller
6. Controller:
   - reads Request
   - uses Services
   - returns Response or View
7. Renderer outputs HTML

---

## Core Components

### Dependency Injection

`core/Container`

- Manual DI container
- Supports:
  - `set()`
  - `singleton()`
  - `get()`
- No reflection
- No auto-resolution

---

### Router

`core/Router`

- HTTP verbs: GET / POST / PUT / DELETE
- Route groups
- Middleware
- Named parameters: `{slug}`
- Admin prefix support

---

### Views

`core/Renderer`

- PHP templates
- Resolves paths from:
  - `app/views`
  - `modules/*/views`
- No Blade/Twig — native PHP only

---

### i18n

- `Lang` service
- Helper: `__()`
- Sources:
  - `app/lang/{locale}.php`
  - `modules/*/lang/{locale}.php`
- All UI strings must go through lang files

---

### Database

`core/Database`

- PDO wrapper
- ERRMODE_EXCEPTION
- No persistent connections
- Migrations via `database/MigrationRunner`
- Install bootstrap via `installer.php`:
  - runs core migrations in sorted order
  - creates `migrations_log` for module state
  - discovers module migrations per selected module automatically

---

### Cache

- File-based cache
- Path: `storage/cache`
- Simple API: set / get / delete / clear
- Used for:
  - lists
  - sitemap
  - redirects
  - search

---

### locale_mode

- Setting `locale_mode` in DB: `en` / `ru` / `multi`
- Controls which language fields are shown in all admin forms
- Pattern used across Articles, Gallery, Categories:
  - Controller reads `$settings->get('locale_mode', 'multi')`, passes as `localeMode` to renderer
  - View: `$showEn = ($lm !== 'ru')`, `$showRu = ($lm !== 'en')`
  - Field groups wrapped in `if ($showEn && $showRu) / elseif ($showEn) / else`
- Validation also adapts: `en` mode requires only `title_en`, `ru` only `title_ru`

---

### File Manager

- `modules/Admin/Controllers/FileManagerController`
- Filesystem browser scoped to `APP_ROOT . '/storage/uploads'`
- Path traversal protection: `realpath()` + prefix comparison
- Flash messages via `$_SESSION['file_manager_flash']` (not `$flash` — avoids conflict with layout)

---

### ContentBase Layer

- `modules/ContentBase/Controllers/BaseAdminController`
- `modules/ContentBase/Controllers/BasePublicController`
- Shared foundation for content-style modules such as News
- Keeps cross-module admin/public helpers out of `core/`

---

### News Module

- Dedicated module with own schema and admin area
- Tables created by `modules/News/migrations/001_create_news_tables.php`
  - `news`
  - `news_categories`
- Routed separately from Articles
- Participates in homepage blocks, sitemap, comments policy and admin content flow

---

### Comments Module

- Global moderation/settings live in `modules/Comments`
- Shared entity policy map allows content modules to expose local comment policy selectors
- Articles, News and Pages use comments only when the global module and section toggles allow it

---

### Menu SEO & Navigation

- Menu entries can carry:
  - SEO title
  - description
  - canonical URL
  - OG image URL
  - icon
  - `is_anchor` pointer flag
- Public renderer can output non-clickable parent/pointer items for nested navigation patterns

---

### PWA Layer

- `app/Controllers/PwaController` serves:
  - `manifest.json`
  - `sw.js`
  - `/offline`
- Admin PWA settings manage:
  - manifest fields
  - service worker version
  - cache strategy
  - offline copy
- v2 default SW version is `v2`

---

### Meta & Structured Data

`core/Meta/`

- **MetaResolver**: merges meta tags with priority (Content > Menu > Defaults)
- **JsonLdRenderer**: renders Schema.org JSON-LD structured data
  - XSS-safe JSON encoding
  - Merges multiple schemas via `@graph`
  - Outputs `<script type="application/ld+json">`
- **CommonSchemas**: reusable Schema.org templates
  - Organization
  - WebSite
  - BreadcrumbList

Modules can create schema providers (e.g., `ArticleSchemaProvider`) to generate
structured data for their content types.

---

### Sitemap Stability

- View/like counters must not mutate `updated_at` for content that feeds sitemap lastmod
- Interaction updates use `updated_at = updated_at` when a table exposes that column
- Schema-level `ON UPDATE CURRENT_TIMESTAMP` must be removed by migration where needed
- v2 hardening covers Articles, News, Gallery and `gallery_items`

---

## Security Model

- No direct PHP access (prefilter + .htaccess)
- CSRF for all admin forms
- Rate-limiting (session-based)
- Upload validation (MIME / size / dimensions)
- Security events logged to `storage/logs/security.log`
- IP blocking (admin / site scopes)

---

## Themes & Styles

- SCSS-based
- Tokens and CSS variables
- Light / Dark themes
- No inline styles allowed
- Admin and frontend styles are separated

---

## Extension Philosophy

- Core **never** depends on modules
- Modules may depend on:
  - Core
  - Other modules (soft-dependency)
- All optional functionality lives outside core

### SEO Extensions

Modules can extend SEO capabilities:

- **Sitemap providers**: add URLs to sitemap.xml
- **Meta providers**: custom meta tags per page
- **Schema providers**: generate JSON-LD structured data
  - Example: `ArticleSchemaProvider` for Schema.org Article
  - Uses `JsonLdRenderer::merge()` for multi-schema output

---

## Summary

SteelRoot is intentionally **boring in the core** and **flexible at the edges**.

If something feels complex inside `core/`, it's probably wrong.
If something is complex, it should live in a module...
