# Theming

## Overview
- Two themes: dark and light.
- Color system uses design tokens and CSS variables; no inline colors.
- Frontend and admin styles are separated.

## Tokens
- Defined in `assets/scss/tokens/` (colors, gradients, shadows, borders, spacing, typography).
- Themes set variables in `themes/_theme-dark.scss` and `themes/_theme-light.scss`.
- Components consume variables, not literal values.

## CSS outputs
- Frontend: `assets/css/app.css` from `assets/scss/app.scss`.
- Admin: `assets/css/admin-theme.css` from `assets/scss/admin.scss`.
- Do not import admin CSS into frontend or vice versa.

## Rules
- No inline color/spacing; use variables.
- Keep :root theme blocks scoped to theme files.
- Components must be theme-agnostic; only tokens vary between themes.
- Avoid adding new tokens unless reused; prefer existing scales.

## Light vs Dark
- Light theme values must mirror coverage of dark theme (all variables present).
- Avoid inversions that change layout; only colors/shadows should differ.
- Check contrast for text on `--bg`, `--bg-card`, and muted areas.

## Assets and overrides
- If extra CSS is needed, place it in SCSS and rebuild; avoid inline overrides.
- Cache may need clearing after theme or asset changes.***
