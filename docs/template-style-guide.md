# Template Style Guide (SteelRoot)

Goals: keep templates consistent, theme-aware, and i18n-ready. Follow these rules when creating or updating PHP views.

## Layout & Structure
- Use existing layout includes (`app/views/layout.php` or module layouts) instead of custom wrappers.
- Wrap page content in semantic containers (`<main>`, `<section>`, `<article>`) as appropriate; avoid deep nesting.
- Use existing utility classes sparingly: `.card`, `.stack`, `.grid`, `.table`, `.form-actions`, `.btn`, `.pill`, `.muted`.
- For error or standalone sections, reuse `.error-page`, `.card`, `.soft`, `.docs-block` where applicable; avoid inline styles.

## Theme & Tokens
- Do not hardcode colors or spacing. Rely on CSS variables/tokens already in SCSS:
  - Background/text: `--bg`, `--bg-card`, `--text`, `--text-muted`, `--border`.
  - Radius/spacings: `--radius`, `--radius-sm`, `--space-*`.
  - Shadows: `--shadow-soft`, `--shadow-strong`.
- Avoid inline styles; if styling is required, add SCSS to `assets/scss/components/` and rebuild via `tools/build_sass.sh`.

## Typography
- Headings: `<h1>`/`<h2>`/`<h3>` with minimal additional classes; prefer using `.muted` for secondary text.
- Links/buttons: use `.btn` variants (`.primary`, `.ghost`, `.danger`, `.small`) and `.pill` for badges/status chips.
- Lists: use `.docs-list` for documentation-style lists; otherwise default UL/OL.

## Forms
- Use `.field` wrapper for inputs/textarea/select; place label text in `<span>` inside `.field`.
- Group related fields with `.grid.two`/`.grid.three` when needed.
- Action row: `.form-actions` with `.btn` elements.
- Always include CSRF tokens passed from controllers; no inline JS beyond existing patterns.

## Tables & Data
- For simple lists, use `.table` with `.table__head` / `.table__row` or `.table-wrap` + `<table class="data">` depending on existing pattern in module.
- Avoid custom table styling; reuse existing classes.

## Components
- Buttons: `.btn`, add modifiers `.primary`, `.ghost`, `.danger`, `.small`.
- Cards: `.card` (optionally `.soft`), content inside `.stack` for vertical rhythm.
- Pills: `.pill`, add semantic classes like `.success`, `.muted`, `.danger` as needed.
- Error page: wrap in `.error-page` for consistent padding/shadow; use existing theme colors.

## Localization
- All visible text via `__()` with keys defined in module lang files (en/ru). No hardcoded RU/EN strings.
- For dynamic labels (e.g., error tabs), prefer specific keys (`errors.settings.code_404`) over placeholders when clarity matters.

## Accessibility & Semantics
- Use proper heading order; avoid skipping levels.
- Buttons vs links: actions that navigate → `<a>` with `.btn`; form submits → `<button>`.
- Forms: associate labels with inputs; show inline validation messages using existing alert styles (`.alert.success|danger`).

## Embeds & Shortcodes
- Content that supports embeds (e.g., pages) should render safe HTML and strip scripts (`stripScripts` pattern already used).
- Use shortcodes like `{{ form:slug }}` only where expected (Pages), avoid parsing in other contexts.

## File/Path Conventions
- Views: place in `modules/{Module}/views/...` or `app/views/...`; keep naming consistent (`admin/...` for admin, `frontend/...` for public).
- SCSS: add component-specific styles to `assets/scss/components/` and import from `assets/scss/app.scss` (or admin.scss for admin-only).
- Avoid adding new global classes unless reused across multiple templates.

## What to Avoid
- No inline colors, fonts, or spacing.
- No external CSS/JS libraries in templates.
- No new design systems; align with existing buttons/cards/forms.
- No hardcoded paths; use configurable prefixes or absolute URLs passed from controllers when needed.
