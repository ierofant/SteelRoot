# SteelRoot Architecture

This document describes the core architectural principles of SteelRoot CMS,
its internal structure, and extension points.

SteelRoot is designed as a **modular, self-hosted PHP system** with clear
boundaries between the core and feature modules.

---

## Core Principles

1. **Explicit over implicit**
   - No magic autowiring
   - No hidden globals
   - All dependencies are registered explicitly

2. **Core is stable, modules are replaceable**
   - Core contains infrastructure only
   - Business logic lives in modules

3. **Shared hosting first**
   - No background daemons required
   - No system-level dependencies
   - Everything runs via HTTP + optional CLI

4. **Readable PHP**
   - No heavy frameworks
   - Plain PHP classes and templates
   - Easy debugging without IDE magic

---

## High-Level Structure

public_html/
├── index.php
├── prefilter.php
├── .htaccess
│
├── core/ # Framework kernel
├── app/ # App-level controllers & views
├── modules/ # Feature modules
├── database/ # Migrations & runner
├── assets/ # SCSS / CSS / JS
├── storage/ # Runtime data (cache, logs, uploads)
└── installer.php # One-time installer

yaml
Copy code

---

## Request Lifecycle

1. **HTTP request**
2. `.htaccess` rewrites to `index.php`
3. `prefilter.php`:
   - blocks forbidden extensions
   - basic rate-limit / security checks
4. `index.php` bootstraps:
   - autoload
   - container
   - router
   - enabled modules
5. Router resolves:
   - middleware
   - controller
6. Controller:
   - reads Request
   - uses Services
   - returns Response or View
7. Renderer outputs HTML

---

## Core Components

### Dependency Injection

`core/Container`

- Manual DI container
- Supports:
  - `set()`
  - `singleton()`
  - `get()`
- No reflection
- No auto-resolution

---

### Router

`core/Router`

- HTTP verbs: GET / POST / PUT / DELETE
- Route groups
- Middleware
- Named parameters: `{slug}`
- Admin prefix support

---

### Views

`core/Renderer`

- PHP templates
- Resolves paths from:
  - `app/views`
  - `modules/*/views`
- No Blade/Twig — native PHP only

---

### i18n

- `Lang` service
- Helper: `__()`
- Sources:
  - `app/lang/{locale}.php`
  - `modules/*/lang/{locale}.php`
- All UI strings must go through lang files

---

### Database

`core/Database`

- PDO wrapper
- ERRMODE_EXCEPTION
- No persistent connections
- Migrations via `database/MigrationRunner`

---

### Cache

- File-based cache
- Path: `storage/cache`
- Simple API: set / get / delete / clear
- Used for:
  - lists
  - sitemap
  - redirects
  - search

---

## Security Model

- No direct PHP access (prefilter + .htaccess)
- CSRF for all admin forms
- Rate-limiting (session-based)
- Upload validation (MIME / size / dimensions)
- Security events logged to `storage/logs/security.log`
- IP blocking (admin / site scopes)

---

## Themes & Styles

- SCSS-based
- Tokens and CSS variables
- Light / Dark themes
- No inline styles allowed
- Admin and frontend styles are separated

---

## Extension Philosophy

- Core **never** depends on modules
- Modules may depend on:
  - Core
  - Other modules (soft-dependency)
- All optional functionality lives outside core

---

## Summary

SteelRoot is intentionally **boring in the core** and **flexible at the edges**.

If something feels complex inside `core/`, it's probably wrong.
If something is complex, it should live in a module.
