# User Guide

Audience: editors and content managers working in the admin panel.

## Daily tasks
- Switch theme/locale in Settings → Theme/Locale to match your workflow.
- Keep navigation consistent via Settings → Menu and Homepage builder.
- Publish updates, then clear cache if something looks stale.

## Articles
- Create, edit, or unpublish articles in the Articles module.
- Assign an **author** from the user dropdown; shown on the frontend if configured.
- Assign a **category** to organise articles; manage categories at Admin → Articles → Categories.
- Use tags for grouping and better search.
- Respect module settings (show/hide author, date, likes, views, tags) set by admin.

## Article Categories
- Manage at Admin → Articles → Categories.
- Each category has a name (EN/RU), slug, optional cover image, position, and enabled flag.
- Enabled categories appear as nav pills on the public articles list and as a breadcrumb on each article.

## Gallery
- Upload images in Admin → Gallery → Upload.
- Select a **folder** (existing subfolder or create new) to organise files on disk.
- Select a **category** to assign the item for frontend filtering.
- A file preview is shown immediately after selecting a file, before uploading.
- Thumbnails (360 px) and medium sizes (1200 px) are generated automatically.
- Lightbox and like counters depend on gallery settings configured by admin.

## Gallery Categories
- Manage at Admin → Gallery → Categories.
- Each category maps to a subfolder in `storage/uploads/gallery/{slug}/`.
- Enabled categories appear as nav pills on the public gallery list.

## Files
- Browse, upload, and organise all uploaded files at Admin → Files.
- Navigate into subfolders using breadcrumbs; create new folders as needed.
- Delete individual files or empty folders (confirmation required).

## Attachments
- Admin → Attachments is a popup picker for inserting images into article bodies.
- Upload images here; click Insert to paste the URL into the editor.

## Forms
- Build forms in Admin → Forms. Define fields, required flags, and order.
- Use provided embed/snippet locations; avoid duplicating fields across forms.
- Use blacklist/regex/domain rules to block obvious spam.

## Cache
- Clear cache after bulk edits (articles, gallery, theme, menu).
- Use cache clear instead of server restarts.

## Basic troubleshooting
- Check Security logs if users report access issues.
- Verify theme/locale matches expectations.
- If content does not appear, clear cache and recheck module settings.
- For media issues, ensure file type/size is allowed and regenerate thumbnails if needed.***
