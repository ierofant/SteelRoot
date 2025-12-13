# SteelRoot CMS

Модульная PHP CMS с упором на ясную архитектуру, безопасность и здравые дефолты.

SteelRoot предназначен для shared/VPS-хостинга (каталог `public_html`) и поставляется
«из коробки» с админ-панелью, системой модулей, темами, i18n (en/ru), статьями,
галереей, страницами, формами, попапами, поиском и поддержкой PWA.

---

## Возможности

- **Статьи**: список/детальная, теги (единые с галереей), превью и meta-поля; CRUD в админке, настройки модуля.
- **Галерея**: masonry-список, lightbox, лайки/просмотры, теги (единые со статьями); загрузка/редактирование,
  sitemap-провайдер, настройки модуля.
- **Страницы** — статичные страницы, меню, sitemap, встраиваемый контент.
- **Формы** — конструктор и встраиваемые формы (`{{ form:slug }}`),
  CSRF / rate-limit / anti-spam защита.
- **Пользователи** — регистрация, авторизация, профиль, аватары, управление.
- **Попапы** — cookie-consent и 18+ с условиями и задержками.
- **Ошибки** — кастомные страницы 403 / 404 / 500 / 503 из админки.
- **Редиректы** — управление редиректами с кешированием.
- **Поиск** — полнотекстовый поиск по статьям и галерее, autocomplete.
- **Темы** — светлая и тёмная на CSS-токенах, без inline-стилей.
- **i18n** — языковые файлы для app и модулей, helper `__()`.
- **PWA** — управление manifest и service worker из админки.
- **Модульная система** — включение/отключение модулей, миграции, настройки.

---

## Требования

- PHP **8.1+** (`pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`)
- MySQL / MariaDB
- Apache или Nginx с rewrite на `public_html/`
- Права на запись:
  - `public_html/storage/cache`
  - `public_html/storage/logs`
  - `public_html/storage/tmp`
  - `public_html/storage/uploads/*`
  - `public_html/database/migrations`

Полный чек-лист: **DEPENDENCIES.md**

---

## Установка (через браузер)

1. Убедитесь, что все необходимые каталоги доступны для записи
   (`.gitkeep` включены).
2. Укажите корень сайта на `public_html/` и настройте rewrite на `prefilter.php`.
3. Откройте `installer.php`:
   - укажите данные БД
   - создайте администратора
   - при необходимости задайте `admin_secret`
4. Инсталлятор создаст конфиги и выполнит миграции.
5. **Удалите `installer.php` после установки.**

Создаются автоматически:
- `app/config/app.php`
- `app/config/database.php`

Примеры:
- `app/config/app.example.php`
- `app/config/database.example.php`

---

## Структура проекта

public_html/
├── index.php
├── prefilter.php
├── .htaccess
├── core/ # ядро: роутер, DI, рендер, cache, lang
├── app/ # контроллеры, сервисы, views, lang
├── modules/ # Articles, Gallery, Pages, Users, Popups, Search, Admin
├── database/ # migrations, seeds, MigrationRunner
├── storage/ # cache, logs, tmp, uploads
└── assets/ # scss/css/js (сборка через tools)

---

## Для разработчиков

- DI: `Container::set / singleton / get` (без автосвязывания).
- Роутинг: middleware, группы, admin-prefix.
- Миграции: `/migrate?up|down|status` или `MigrationRunner.php`.
- SCSS: `bash tools/build_sass.sh`.
- Все строки UI — **только через lang-файлы**.
- Настройки модулей — через `ModuleSettings`.
- Не коммитьте runtime-данные (см. `.gitignore`).

---

## Развёртывание

- `vendor/` можно не хранить в git (если используется Composer).
- Для Users-модуля необходимы:
  - `storage/tmp/user_tokens`
  - `storage/uploads/users`
- Префикс админки задаётся через `admin_secret`.

---

## License

MIT © 2025 **ierofant**

SteelRoot распространяется по лицензии MIT.
Разрешено использование, модификация и коммерческое применение.
