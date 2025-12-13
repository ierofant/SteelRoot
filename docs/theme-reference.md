## Design Tokens
- `$color-*` — палитра (фон, текст, бордеры, акценты, danger) для dark/light.
- `$gradient-*` — градиенты карточек, шапок, sidebar.
- `$shadow-*` — тени карточек/попапов/сильные тени.
- `$radius-*` — радиусы скругления.
- `$space-1..5` — шкала отступов.
- `$font-base`, `$font-mono` — базовые шрифты.

## Theme Variables
- Общие: `--bg`, `--bg-card`, `--bg-muted`, `--text`, `--text-muted`, `--accent`, `--accent-2`, `--border`, `--shadow-card`.
- Light overrides: `--accent-hover`, `--border-soft`, `--shadow-popup`, `--panel`, `--card`, `--card-soft`, `--danger`, `--shadow`.
- Применяются через `data-theme` на `<body>`.

## UI Classes
- Layout: `.admin-shell`, `.sidebar`, `.page-header`, `.page-body`, `.footer`, `.card-header`.
- Components: `.card` (и `.subtle`, `.glass`, `.tall`), `.table`, `.table-wrap`, `.btn` (и `.primary`, `.ghost`, `.danger`, `.small`), `.pill` (вкл. `.pill.small`), `.badge`, `.field`, `.form-actions`, `.actions`, `.row-actions`, `.alert` ( `.success`, `.danger`), `.stack`, `.grid` ( `.two`, `.three`, `.stats`), `.avatar`, `.thumb-preview`, `.EasyMDEContainer`, `.dash-block`, `.link-card`.

## Рекомендации
- Всегда использовать существующие CSS-переменные (`--bg`, `--text`, `--accent`, `--border`) вместо жёстких цветов; `data-theme` управляет переключением.
- Новые компоненты строить на токенах: фон (`--bg-card`), текст (`--text`/`--text-muted`), бордер (`--border`/`--border-soft`), тень (`--shadow-card`/`--shadow-popup`), акценты (`--accent`, `--accent-hover`).
- Не добавлять inline-цвета и не дублировать dark-стили; светлые отличия — только в `:root[data-theme="light"]`.
- Если токена не хватает — добавить в tokens и использовать через переменные, избегая прямых значений.
