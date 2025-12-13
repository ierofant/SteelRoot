# Admin Guide

Audience: administrators responsible for configuration and operations.

## Core settings
- Theme & locale: set global theme (dark/light) and language; these drive CSS variables and lang files.
- Menu: manage visible navigation entries; keep it aligned with enabled modules.
- Custom CSS/URLs: use provided settings for overrides; avoid inline changes elsewhere.
- Site identity: keep name, contact email, and social links consistent.
- OG/SEO: fill OG title/description/image; set canonical-style paths where applicable.

## Homepage builder
- Adjust hero, badges, CTAs, and optional stats.
- Control gallery/article blocks (order, visibility, limits).
- Keep JSON custom blocks small and validated before saving.
- Choose gallery open mode (lightbox/page) to match module settings.

## Users
- Manage admin accounts, roles, and status.
- Review login attempts in Security logs if access problems occur.
- Maintain at least two admin accounts with strong passwords.

## Modules
- Enable/disable/migrate/rollback modules in Admin → Modules.
- Open module-specific settings (e.g., Articles, Gallery, Popups) via the module list.
- After migrations or setting changes, clear cache if output is stale.
- Keep module versions aligned with migrations; use rollback cautiously.

## Security
- Check Security logs (login attempts, 404, blocked IPs).
- Block IPs for admin or whole site; keep regex/IP list minimal and reviewed.
- Ensure CSRF tokens are present on all admin forms (built-in helpers handle this).
- Use admin guard key/IP regex carefully and verify access after changes.

## Maintenance
- Cache: clear or delete entries if behaviour seems outdated.
- Sitemap: rebuild and clear sitemap cache after major content changes.
- PWA: update manifest fields (name, colors, start URL) and service worker version when assets change.
- Files/Attachments: delete unused files, regenerate thumbnails when required.
- Forms: adjust spam rules (blacklist/regex/domains) when patterns shift.

## Localization
- Switch locale globally in Settings; verify lang files exist for modules in both `en` and `ru`.
- Use __() in templates; never hardcode strings when adjusting views.
