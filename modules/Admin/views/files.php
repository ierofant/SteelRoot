<?php
$ap      = $ap ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin');
$basePath = defined('APP_ROOT') ? APP_ROOT . '/storage/uploads' : '';
$rel      = $basePath !== '' ? ltrim(substr($currentDir ?? '', strlen($basePath)), '/') : '';

ob_start();
?>
<div class="stack">

<?php if (!empty($fmFlash)): ?>
    <div class="alert <?= htmlspecialchars($fmFlash['type']) ?>"><?= htmlspecialchars($fmFlash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Admin</p>
            <h3>File Manager</h3>
        </div>
    </div>

    <!-- Breadcrumbs -->
    <nav class="fm-breadcrumbs">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
                <a href="<?= htmlspecialchars($ap . '/files' . ($crumb['rel'] !== '' ? '?dir=' . urlencode($crumb['rel']) : '')) ?>">
                    <?= htmlspecialchars($crumb['label']) ?>
                </a>
                <span class="fm-sep">/</span>
            <?php else: ?>
                <span class="fm-current"><?= htmlspecialchars($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Actions row -->
<div class="fm-actions-row">
    <!-- Upload -->
    <form method="post" action="<?= htmlspecialchars($ap) ?>/files/upload" enctype="multipart/form-data" class="card fm-action-card">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($rel) ?>">
        <p class="eyebrow">Upload file</p>
        <div class="fm-action-body">
            <input type="file" name="file" required class="fm-file-input">
            <button type="submit" class="btn primary small">Upload</button>
        </div>
    </form>

    <!-- New folder -->
    <form method="post" action="<?= htmlspecialchars($ap) ?>/files/mkdir" class="card fm-action-card">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($rel) ?>">
        <p class="eyebrow">New folder</p>
        <div class="fm-action-body">
            <input type="text" name="name" placeholder="folder-name" pattern="[a-zA-Z0-9_\-]+" required>
            <button type="submit" class="btn ghost small">Create</button>
        </div>
    </form>
</div>

<!-- Folders -->
<?php if (!empty($folders)): ?>
<div class="card">
    <div class="card-header"><h4>Folders</h4></div>
    <div class="fm-folders">
        <?php foreach ($folders as $f): ?>
        <div class="fm-folder-item">
            <a href="<?= htmlspecialchars($ap . '/files?dir=' . urlencode($f['rel'])) ?>" class="fm-folder-link">
                <span class="fm-folder-icon">📁</span>
                <span class="fm-folder-name"><?= htmlspecialchars($f['name']) ?></span>
                <span class="muted fm-folder-count"><?= (int)$f['count'] ?></span>
            </a>
            <form method="post" action="<?= htmlspecialchars($ap) ?>/files/delete"
                  onsubmit="return confirm('Delete folder ' + <?= json_encode('«' . $f['name'] . '»') ?> + '? It must be empty.')">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="dir"  value="<?= htmlspecialchars($rel) ?>">
                <input type="hidden" name="path" value="<?= htmlspecialchars($f['rel']) ?>">
                <button type="submit" class="btn danger small">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Files -->
<div class="card">
    <div class="card-header"><h4>Files <?php if (!empty($files)): ?><span class="muted">(<?= count($files) ?>)</span><?php endif; ?></h4></div>
    <?php if (empty($files)): ?>
        <p class="muted" style="padding:1rem">No files in this folder.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>URL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td class="fm-thumb-cell">
                        <?php if ($file['isImage']): ?>
                            <img src="<?= htmlspecialchars($file['url']) ?>" alt="" class="fm-thumb">
                        <?php else: ?>
                            <span class="fm-file-icon muted"><?= htmlspecialchars(pathinfo($file['name'], PATHINFO_EXTENSION)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($file['name']) ?></td>
                    <td class="muted"><?= number_format($file['size'] / 1024, 1) ?> KB</td>
                    <td>
                        <input type="text" value="<?= htmlspecialchars($file['url']) ?>"
                               class="fm-url-input" readonly
                               onclick="this.select();document.execCommand('copy');this.blur();this.classList.add('copied')">
                    </td>
                    <td class="actions">
                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" class="btn ghost small">Open</a>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/files/delete"
                              onsubmit="return confirm('Delete ' + <?= json_encode('«' . $file['name'] . '»') ?> + '?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="dir"  value="<?= htmlspecialchars($rel) ?>">
                            <input type="hidden" name="path" value="<?= htmlspecialchars($file['rel']) ?>">
                            <button type="submit" class="btn danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- .stack -->

<style>
.fm-breadcrumbs { display:flex; align-items:center; gap:.4rem; padding:.75rem 1.25rem; flex-wrap:wrap; }
.fm-breadcrumbs a { color:var(--accent); text-decoration:none; }
.fm-breadcrumbs a:hover { text-decoration:underline; }
.fm-sep { color:var(--muted); }
.fm-current { font-weight:600; }

.fm-actions-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.fm-action-card { padding:1rem 1.25rem; }
.fm-action-body { display:flex; gap:.5rem; align-items:center; margin-top:.5rem; flex-wrap:wrap; }
.fm-action-body input[type="text"],
.fm-action-body input[type="file"] { flex:1; min-width:0; }
.fm-file-input { font-size:.85em; }

.fm-folders { display:flex; flex-direction:column; gap:0; }
.fm-folder-item { display:flex; align-items:center; justify-content:space-between;
    padding:.5rem 1.25rem; border-bottom:1px solid var(--border); }
.fm-folder-item:last-child { border-bottom:none; }
.fm-folder-link { display:flex; align-items:center; gap:.5rem; text-decoration:none;
    color:var(--text); flex:1; }
.fm-folder-link:hover .fm-folder-name { color:var(--accent); }
.fm-folder-icon { font-size:1.2rem; }
.fm-folder-count { font-size:.8rem; margin-left:.25rem; }

.fm-thumb-cell { width:72px; }
.fm-thumb { width:64px; height:48px; object-fit:cover; border-radius:4px; border:1px solid var(--border); display:block; }
.fm-file-icon { display:block; width:64px; text-align:center; font-size:.75rem; text-transform:uppercase;
    padding:.25rem; border:1px solid var(--border); border-radius:4px; }

.fm-url-input { font-size:.78rem; padding:.25rem .4rem; background:var(--bg);
    border:1px solid var(--border); border-radius:4px; color:var(--muted);
    cursor:pointer; width:180px; transition:border-color .15s; }
.fm-url-input:focus { outline:none; border-color:var(--accent); color:var(--text); }
.fm-url-input.copied { border-color:#22c55e; }

@media(max-width:640px) {
    .fm-actions-row { grid-template-columns:1fr; }
    .fm-url-input { width:120px; }
}
</style>

<?php
$title   = 'File Manager';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
