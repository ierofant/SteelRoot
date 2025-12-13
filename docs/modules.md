# Modules

## Structure
- `Module.php` — registers routes and middleware.
- `routes.php` (optional) — additional route definitions if used.
- `Controllers/` — controllers per module.
- `views/` — PHP templates; no hardcoded strings.
- `lang/en.php`, `lang/ru.php` — localisation keys.
- `migrations/` — DB schema changes.
- Optional: `Search/`, `sitemap.php`, assets specific to the module.

## Required behaviours
- Provide admin routes under `admin_prefix` for configuration if needed.
- Expose module settings via `ModuleSettings` with defaults in code.
- Use `__()` for all UI strings; keep en/ru files in sync.
- Include migrations for schema changes; avoid manual SQL outside migrations.

## Routing
- Frontend routes: public, defined in `Module.php`.
- Admin routes: must include auth middleware; use CSRF on forms.
- Do not create routes that bypass existing guards.

## Views
- Keep HTML stable; inject data from controllers.
- No inline CSS/JS; rely on shared assets and tokens.
- Respect theme tokens; avoid hardcoded colors or spacing.

## Migrations
- Place incremental scripts in `migrations/`.
- Support migrate/rollback through Admin → Modules actions.
- Keep migrations idempotent where possible and avoid destructive defaults.

## Settings
- Use `ModuleSettings` for per-module toggles.
- Provide a simple admin UI for settings; avoid complex flows.
- Apply settings in controllers; views should not fetch settings directly.

## Must NOT do
- Do not touch core files from a module.
- Do not load admin assets on frontend or vice versa.
- Do not hardcode text; always use lang files.
- Do not bypass cache/security mechanisms; use provided services.
- Do not create cross-module dependencies beyond shared services/helpers.***
