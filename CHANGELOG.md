# Changelog

All notable changes to SteelRoot CMS will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added (2026-03-05)

#### locale_mode — Adaptive language fields
- New `locale_mode` setting (`en` / `ru` / `multi`) hides irrelevant language fields across all admin forms.
- Pattern: controller reads setting → passes `localeMode` to view → view computes `$showEn`/`$showRu` → wraps field groups in conditionals.
- Implemented in: Articles (list + create/edit), Article Categories (list + form), Gallery upload form.
- Title validation respects mode: `en` requires only `title_en`, `ru` requires only `title_ru`, `multi` requires at least one.

#### Articles — Author field
- Author dropdown (list of registered users) on article create/edit form.
- `author_id` saved on create and updated on edit.
- Shown only when `author_id` column exists (graceful degradation pre-migration).

#### Article Categories
- New table `article_categories` (slug, name_en/ru, image_url, position, enabled).
- Admin CRUD at `/admin/articles/categories` with cover image upload.
- Public route `/articles/category/{slug}` with nav pills and category breadcrumb.
- Sitemap entries for enabled categories.

#### Gallery Categories + Upload Subfolders
- New table `gallery_categories` (slug, name_en/ru, image_url, position, enabled).
- Admin CRUD at `/admin/gallery/categories` with cover image upload.
- Upload subfolders: files go to `/storage/uploads/gallery/{category-slug}/` (auto-created).
- Folder picker on upload form: lists existing filesystem folders + "New folder…" option.
- Public route `/gallery/category/{slug}` with nav pills on list page.
- Sitemap entries for enabled categories.

#### File Manager (`/admin/files`) — rebuilt
- Full filesystem browser for `storage/uploads/` with breadcrumb navigation.
- Actions: upload file, create folder, delete file, delete empty folder (with confirmation).
- Path traversal protection via `realpath()` prefix check.
- Flash messages via `$_SESSION['file_manager_flash']` (separate from layout `$flash`).

#### Gallery upload — improvements
- File preview shown immediately on file selection (JS `FileReader`, no upload needed).
- locale_mode-aware title/description fields.

#### Attachments (`/admin/attachments`) — fix
- Subdirectories (e.g. `categories/`) excluded from file listing via `array_filter(..., 'is_file')`.

### Added (2025-02-15)
- **JSON-LD Structured Data System**
  - Core infrastructure in `core/Meta/` for Schema.org markup generation
  - `JsonLdRenderer` for rendering and merging JSON-LD schemas with XSS protection
  - `CommonSchemas` for reusable templates (Organization, WebSite, BreadcrumbList)
  - `MetaResolver` for meta tag priority resolution (Content > Menu > Defaults)
  - Articles module integration: auto-generates Schema.org Article + Organization schemas
  - `ArticleSchemaProvider` in `modules/Articles/Providers/`
  - Google Rich Results compatible
  - Extensible for other modules (Gallery, Shop, FAQ, Pages)
  - Documentation: `JSON_LD_IMPLEMENTATION.md`, `IMPLEMENTATION_SUMMARY.md`
  - Architecture docs updated: `ARCHITECTURE.md`, `MODULES.md`
  - README files updated with JSON-LD feature descriptions

---

## [1.0.0] - 2025-01-XX (example version)

### Core Features
- Modular architecture with `core/ModuleManager`
- Per-module migrations via `ModuleMigrationRunner`
- DI container (manual, no autowiring)
- Router with middleware and admin prefix support
- Renderer with PHP templates (no Blade/Twig)
- i18n system with `Lang` service and `__()` helper
- File-based cache system
- Database layer (PDO wrapper)
- CSRF protection
- Rate limiting (session-based)
- Security logging

### Modules
- **Admin**: dashboard, settings, users CRUD, security panel
- **Articles**: list/detail, tags, previews, meta fields, CRUD, fulltext search
- **Gallery**: masonry grid, lightbox, likes/views, tags, image processing
- **Pages**: static pages, menu integration, sitemap provider
- **Users**: auth/registration, profiles, avatars, role management
- **Search**: fulltext search with source filters, autocomplete
- **Popups**: cookie consent, adult content warnings
- **Menu**: RU/EN labels, SEO meta, OG images, dropdown support
- **Redirects**: cached redirect management
- **Templates**: custom theme upload/selection
- **PWA**: manifest and service worker management

### SEO Features
- Sitemap with module providers
- Meta tag resolution with priority system
- Canonical URLs
- Open Graph tags
- Twitter Card tags
- Breadcrumbs with customization

### Themes & UI
- Light/Dark themes via CSS tokens
- No inline styles
- SCSS build pipeline
- Mobile-responsive navigation
- Admin dashboard with drag/drop blocks

### Security
- Prefilter for request validation
- IP blocking (admin/site scopes)
- Upload validation (MIME, size, dimensions)
- Password hashing (bcrypt)
- Admin login rate limiting
- Security event logging

### Developer Experience
- Clear architecture documentation
- PSR-4 autoloading
- Explicit dependency injection
- Migration system
- Module settings API
- Extensible search/sitemap/meta providers

---

## Version History

- **[Unreleased]**: JSON-LD structured data, ongoing development
- **[1.0.0]**: Initial stable release (example)

---

## Notes

- SteelRoot follows **"boring core, flexible edges"** philosophy
- Breaking changes are avoided in core
- All new features are extensions, not replacements
- See `ARCHITECTURE.md` for design principles
- See `MODULES.md` for module development guide

---

**Maintained by**: ierofant
**License**: MIT
**Repository**: [GitHub/GitLab link]
