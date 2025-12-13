# SteelRoot Themes Guide (for front-end devs)

Focus: keep light/dark themes consistent, token-driven, and reusable across frontend/admin without inline overrides.

## Core principles
- Two themes: light and dark.
- Components must be theme-agnostic: no hardcoded colors in markup; use CSS variables/tokens.
- Frontend and admin styles are separate: `assets/css/app.css` (frontend) and `assets/css/admin-theme.css` (admin). Never import admin CSS into frontend.

## Tokens & Variables
Defined in `assets/scss/tokens/`:
- Colors: see `_colors.scss`
- Gradients: `_gradients.scss`
- Shadows: `_shadows.scss`
- Borders: `_borders.scss`
- Spacing: `_spacing.scss`
- Typography: `_typography.scss`

Theme values:
- Light: `themes/_theme-light.scss`
- Dark: `themes/_theme-dark.scss`

Use CSS vars:
- Backgrounds: `--bg`, `--bg-card`, `--bg-muted`
- Text: `--text`, `--text-muted`
- Accent: `--accent` (and derived accent hover if present)
- Border: `--border`, `--border-soft`
- Radius: `--radius`, `--radius-sm`, `--radius-lg`
- Shadows: `--shadow-soft`, `--shadow-strong`
- Spacing: `--space-*`

## SCSS entry points
- Frontend: `assets/scss/app.scss` → `assets/css/app.css`
- Admin: `assets/scss/admin.scss` → `assets/css/admin-theme.css`
- Build with `bash tools/build_sass.sh` (uses `sass` or `npx sass`), backups in `assets/css/backups/`.

## Components
Existing components are under `assets/scss/components/`:
- Buttons (`_buttons.scss`), Forms (`_forms.scss`), Cards (`_cards.scss`), Popups (`_popups.scss`), HR (`_hr.scss`), Article tags (`_article-tags.scss`), Errors (`_errors.scss`), Admin helpers (`_admin.scss`).
- When adding styles, place them in a new component file and import in `app.scss` (or `admin.scss` for admin-only).
- Do not add inline styles; use classes and tokens.

## Layout
- Shared layout SCSS: `assets/scss/layout/` (header, footer, sidebar, mobile).
- Pages: `assets/scss/pages/` for page-specific tweaks (public, dashboard, settings, auth).

## Do / Don’t
- Do: add new colors via tokens and apply in theme files.
- Do: keep :root theme blocks scoped to theme files.
- Do: ensure both light and dark have the same variable coverage.
- Don’t: hardcode hex values in components.
- Don’t: import admin CSS into frontend or vice versa.
- Don’t: use inline `style` or per-template CSS; update SCSS and rebuild instead.

## Testing checklist
- Switch themes and verify: text contrast on `--bg`, `--bg-card`, muted text legibility, borders visible but soft.
- Popups, forms, buttons, pills: hover/active states visible in both themes.
- Error pages and docs blocks: padding/shadows consistent; no theme leaks.
- Mobile: check header/nav overlays and cards for readability.

## Assets separation
- Keep uploads and user content out of SCSS; no base64 images in styles.
- If adding icons, prefer existing fonts or SVG inline; avoid external icon fonts.

## Lint/Build notes
- Sass uses legacy `@import`; deprecation warnings are expected. Keep structure until ready to migrate to `@use`.
- Build is manual; no CI assumed. Run `tools/build_sass.sh` after SCSS changes.
