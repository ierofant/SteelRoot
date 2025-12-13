# Architecture

## Core principles
- Modular design: each feature resides in `/modules/{Name}` with its own routes, controllers, views, and lang files.
- Separation: admin panel and frontend have distinct routes, assets, and layouts.
- Themes: dark/light handled through design tokens and CSS variables; no inline colors.
- i18n: language keys in module `lang/en.php` and `lang/ru.php`, rendered with `__()`.
- Settings: `SettingsService` for global values; `ModuleSettings` for per-module options.

## Request flow
- Request enters router → matched route from a module → controller executes → view renders PHP template.
- Admin routes are guarded by auth middleware and optional IP guard.
- Module settings and global settings are read during controller execution; views are dumb and rely on provided data.

## Configuration philosophy
- Keep defaults in code; persist overrides in settings storage.
- Prefer boolean or small enumerations for feature toggles.
- Do not hardcode URLs, colors, or text in templates; use settings/tokens/lang keys.

## Frontend vs Admin
- Frontend CSS: `assets/css/app.css` built from `assets/scss/app.scss`.
- Admin CSS: `assets/css/admin-theme.css` built from `assets/scss/admin.scss`.
- Scripts and styles must not leak between frontend and admin.

## Data and storage
- Database migrations per module in `/modules/{Name}/migrations`.
- Cache layer available; clear after structural changes.
- Logs: security and system logs stored under `storage/logs`.

## Extensibility
- Add features via modules only.
- Use services (e.g., SettingsService, ModuleSettings) instead of new globals.
- Keep modules independent; avoid direct coupling unless via shared services.***
