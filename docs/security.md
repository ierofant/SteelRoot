# Security

## Admin route protection
- All admin routes live under `admin_prefix` (default `/admin`) and require auth middleware.
- Optional IP guard and admin key checks should be kept enabled as configured.
- Never add public routes under the admin prefix.

## CSRF
- All admin forms must include CSRF tokens; built-in helpers provide them.
- Reject or log requests with missing/invalid tokens.

## IP blocking and logs
- Block IPs for admin or whole site via Security settings.
- Keep block lists minimal and reviewed; avoid broad regex that can lock out admins.
- Security logs track login attempts, 404s, and blocks; monitor regularly.

## Data handling
- Use prepared statements/DB helpers; avoid raw input interpolation.
- Validate file uploads (type/size) in modules that accept media.
- Clear cache after security-sensitive changes (routes, settings, auth rules).

## Principles
- No inline secrets or keys in templates or assets.
- Keep modules self-contained; avoid backdoors that bypass middleware.
- Prefer least privilege: only expose settings actually needed in admin UI.***
