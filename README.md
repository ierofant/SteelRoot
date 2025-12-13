# SteelRoot
Modular PHP CMS focused on clarity, security, and sane defaults.

# SteelRoot CMS

Modular PHP CMS for shared hosting (public_html). Ships with admin panel, themes, i18n (en/ru), articles, gallery, popups, pages, forms, search, and PWA support.

## Requirements
- PHP 8.1+ with extensions: pdo, pdo_mysql, mbstring, json, openssl, fileinfo, gd.
- MySQL/MariaDB.
- Web server with URL rewrite (Apache/Nginx) pointing to `public_html/`.
- Writable: `public_html/storage/{cache,logs,tmp,uploads/*}`, `public_html/database/migrations`. See `DEPENDENCIES.md` for full checklist.

## Install (browser)
1) Ensure writable dirs above exist (place `.gitkeep` are included).
2) Point vhost to `public_html/` with rewrites to `prefilter.php`.
3) Open `installer.php` in browser, fill DB/admin, optional admin_secret for custom prefix. Installer writes configs and runs migrations (core + pages table).
4) Delete `installer.php` after success.

Config files `app/config/app.php` and `app/config/database.php` are generated; examples: `app/config/app.example.php`, `database.example.php`.

## Structure (key)
```
public_html/
  index.php / prefilter.php / .htaccess
  core/        # kernel, router, DI, renderer, lang, cache
  app/         # controllers, services, views, lang, config
  modules/     # Admin, Articles, Gallery, Popups, Pages, Users, Search
  database/    # migrations/, seeds/, MigrationRunner.php
  storage/     # cache, logs, tmp, uploads/{gallery,articles,users}
  assets/      # scss/css/js; build via tools/build_sass.sh (sass or npx sass)
```

## Features (current)
- Articles: list/detail, tags, previews, meta; admin CRUD, module settings.
- Gallery: masonry list, lightbox, likes/views, tags; admin upload/edit/delete, module settings, sitemap provider.
- Pages: static pages with admin CRUD, menu integration, sitemap; embeds handled in content.
- Embeddable forms: admin tab `/admin/forms/embeds`, JSON-defined fields, localized success, embed via `{{ form:slug }}`; CSRF/rate-limit/spam protections reused from contact form.
- Users: auth/registration/profile with avatars; admin user management; registration settings `/admin/users/settings` (enable/disable, email verification, default role, username/password rules, domain allow/deny, IP/CIDR blocks, rate limit, optional auto-login).
- Error pages: admin `/admin/template/errors` per-code (403/404/500/503) custom content (title/message/description/CTA/icon/home button) with safe rendering.
- Popups: cookie/adult popups with delays/targets; admin UI `/admin/popups`.
- Redirects: `/admin/redirects` with cache; handled before 404.
- Search: full-text articles/gallery with source filters; autocomplete tags; API v1.
- PWA: admin-managed manifest/SW version/cache list; runtime cache with versioning.
- Themes: light/dark via tokens/variables; no inline colors.
- i18n: lang files per app/module; helper `__()`.
- Cache: file cache; sitemap cached 10 min.
- Module system: `core/ModuleManager`, per-module migrations (`ModuleMigrationRunner`), lang/views/routes providers.

## Development
- DI via `Container::set/singleton/get`, no autowiring.
- Router supports middleware/groups; 404 handled by Kernel error views.
- Run migrations via `/migrate?up|down|status` (web) or `database/MigrationRunner.php`.
- SCSS build: `bash tools/build_sass.sh` (uses sass or npx sass).
- Avoid committing generated configs (`app/config/app.php`, `database.php`), uploads, cache, tmp; see `.gitignore`.

## Deployment notes
- Keep `vendor/` out of VCS unless required for zero-composer deploys.
- Ensure `storage/tmp/user_tokens` and `storage/uploads/users` exist for Users module.
- Admin prefix configurable via `admin_secret`; stored in `app/config/app.php`.

## License

MIT © 2025 ierofant

SteelRoot is released under the MIT License.
You are free to use, modify, and distribute it.
Commercial usage is allowed.
