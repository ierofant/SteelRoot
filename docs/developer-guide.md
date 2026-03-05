# Developer Guide

Audience: developers extending or maintaining SteelRoot.

## Project layout
- `core/` — framework components (router, container, DB, settings).
- `modules/{Name}/` — feature modules, each self-contained.
- `assets/` — SCSS, CSS, JS; admin and frontend are separated.
- `app/` — shared config, views, and helpers.
- `docs/` — documentation only (keep code untouched here).

## Module lifecycle
- Entry: `modules/{Name}/Module.php` registers routes and middleware.
- Controllers handle requests and render PHP views via the renderer.
- Migrations live in `modules/{Name}/migrations` and are run from Admin → Modules.
- Settings: use `SettingsService` for global values; `ModuleSettings` for per-module toggles.
- Views: use `__()` for all UI strings; provide `lang/en.php` and `lang/ru.php`.

## Routing
- Frontend routes are public and defined in Module::register.
- Admin routes are namespaced under `admin_prefix` (default `/admin`) and require auth middleware.
- Avoid adding routes that bypass existing middleware or CSRF protections.

## Assets
- Frontend styles compile from `assets/scss/app.scss` → `assets/css/app.css`.
- Admin styles compile from `assets/scss/admin.scss` → `assets/css/admin-theme.css`.
- Use design tokens (colors/spacing/typography/shadows) and CSS variables; no inline colors.

## Configuration
- Read global config via `SettingsService`; respect defaults and avoid hardcoded paths.
- Module settings: define defaults in code, persist via `ModuleSettings`, and expose a simple admin UI.

## locale_mode pattern
Admin forms with bilingual fields must respect `locale_mode`:
```php
// Controller constructor:
$this->localeMode = $settings->get('locale_mode', 'multi');
// Passed to renderer as 'localeMode'
```
```php
// View top:
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
// Wrap field groups:
// if ($showEn && $showRu) → grid-two
// elseif ($showEn)        → single EN
// else                    → single RU
```
Validation: `en` → require `title_en` only; `ru` → require `title_ru` only; `multi` → require at least one.

## Category system pattern
Articles and Gallery share the same pattern for categories:
- Separate table: `{module}_categories` (id, slug UNIQUE, name_en, name_ru, image_url, position, enabled).
- Service class with graceful `try/catch` fallback if table not yet migrated (`all()` returns `[]`).
- Admin CRUD at `/{admin}/articles/categories` or `/gallery/categories`, registered **before** `{slug}` wildcard routes.
- Cover image upload to `storage/uploads/{module}/categories/` with `{prefix}_` filename.
- Public list page shows nav pills; detail/view page shows breadcrumb.
- Sitemap auto-discovers enabled categories.

## Auto-discovery files
Modules may ship:
- `sitemap.php` — returns array of sitemap rows; discovered by Kernel.
- `home_block.php` — returns block config array; discovered by HomeController.
- `search_provider.php` — returns SearchProvider; discovered by `bootstrap/search_providers.php`.

## File Manager (admin)
- `FileManagerController` scopes all operations to `APP_ROOT . '/storage/uploads'`.
- Use `resolveDir()` / `resolvePath()` helpers (both validate via `realpath()` + prefix check) before any filesystem operation.
- Flash messages use `$_SESSION['file_manager_flash']` — **not** `$flash` which is reserved for layout.php string flash.

## Best practices
- Keep HTML unchanged when altering behaviour; adjust styling via SCSS and tokens.
- No direct DB schema edits outside migrations.
- No hardcoded strings; ensure lang keys exist for en/ru.
- Respect light/dark tokens; do not duplicate color values.
- Clear cache after schema or settings changes that affect output.

## Constraints
- Do not mix admin CSS/JS into frontend or vice versa.
- Avoid inline scripts/styles; use existing asset pipelines.
- Keep modules self-contained; no cross-module coupling beyond services/helpers.***
