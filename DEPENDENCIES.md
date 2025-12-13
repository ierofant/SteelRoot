# SteelRoot Hosting Requirements

Minimal checklist to deploy on a new shared/VPS hosting. No elevated privileges required beyond writing to the project root.

## Runtime
- PHP 8.1+ with extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`.
- MySQL/MariaDB reachable over TCP; recommended MySQL 5.7+ / MariaDB 10.5+.
- Web server with URL rewriting (Apache mod_rewrite or Nginx equivalent) pointing to `public_html/` and rewriting to `prefilter.php` when the file is not found.

## File system
- Writable paths:
  - `public_html/storage/`
  - `public_html/storage/cache/`
  - `public_html/storage/logs/`
  - `public_html/storage/tmp/`
  - `public_html/storage/tmp/user_tokens/`
  - `public_html/storage/uploads/`
  - `public_html/storage/uploads/{gallery,articles,users}/`
- Optional: `public_html/assets/css/backups/` (auto-created by build script for SCSS backups).

## CLI tools (optional but recommended for development)
- PHP CLI (same version as web).
- `sass` or `npx sass` for rebuilding SCSS (`tools/build_sass.sh`).
- `composer` only if you need to reinstall vendor packages (vendor is typically shipped).

## Installation steps (summary)
1) Point vhost/root to `public_html/` with rewrites enabled.
2) Ensure writable directories above exist.
3) Create database and user with full access to that DB.
4) Run `installer.php` in the browser, fill DB/admin credentials; installer writes configs and runs migrations.
5) Remove `installer.php` after success.

## Notes
- Admin prefix can be customized during install (`admin_secret` → `/admin-{secret}`).
- No external services are required beyond DB and SMTP (if email is used).
