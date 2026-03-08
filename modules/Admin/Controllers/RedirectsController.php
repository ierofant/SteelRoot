<?php
namespace Modules\Admin\Controllers;

use App\Services\RedirectService;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Request;
use Core\Response;

class RedirectsController
{
    private RedirectService $redirects;
    private Database $db;

    public function __construct(Container $container)
    {
        $this->redirects = $container->get(RedirectService::class);
        $this->db        = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        $items = $this->redirects->all();
        $flash = null;
        if (!empty($request->query['msg'])) {
            $flash = match ($request->query['msg']) {
                'created' => 'Redirect сохранён',
                'deleted' => 'Redirect удалён',
                'cleared' => 'Кеш редиректов очищен',
                default   => null,
            };
        }
        $error = $request->query['error'] ?? null;
        $content = $this->render($items, Csrf::token('redirects_admin'), $flash, $error);
        return new Response($content);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('redirects_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $from     = trim($request->body['from'] ?? '');
        $to       = trim($request->body['to'] ?? '');
        $status   = (int)($request->body['status'] ?? 301);
        $isRegexp = !empty($request->body['is_regexp']);

        if ($from === '' || $to === '') {
            return new Response('Missing data', 400);
        }

        $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        $error = $this->redirects->create($from, $to, $status, $isRegexp);
        if ($error !== null) {
            return new Response('', 302, ['Location' => $ap . '/redirects?error=' . urlencode($error)]);
        }

        return new Response('', 302, ['Location' => $ap . '/redirects?msg=created']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('redirects_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->body['id'] ?? 0);
        if ($id > 0) {
            $this->redirects->delete($id);
        }
        $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        return new Response('', 302, ['Location' => $ap . '/redirects?msg=deleted']);
    }

    public function clearCache(Request $request): Response
    {
        if (!Csrf::check('redirects_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $this->redirects->clearCache();
        $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        return new Response('', 302, ['Location' => $ap . '/redirects?msg=cleared']);
    }

    private function render(array $items, string $csrf, ?string $flash, ?string $error): string
    {
        $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        ob_start();
        ?>
        <div class="card stack glass">
            <div class="card-header">
                <div>
                    <p class="eyebrow"><?= __('redirects.title') ?></p>
                    <h3><?= __('redirects.subtitle') ?></h3>
                    <p class="muted"><?= __('redirects.description') ?></p>
                </div>
                <form method="post" action="<?= htmlspecialchars($ap . '/redirects/clear-cache') ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button class="btn ghost" type="submit"><?= __('redirects.action.clear_cache') ?></button>
                </form>
            </div>

            <?php if ($flash): ?>
                <div class="alert success"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form class="stack redirects-form form-dark" method="post" action="<?= htmlspecialchars($ap . '/redirects') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="grid two">
                    <label class="stack">
                        <span class="muted"><?= __('redirects.form.from_label') ?></span>
                        <input type="text" name="from" placeholder="<?= __('redirects.form.from_placeholder') ?>" required>
                    </label>
                    <label class="stack">
                        <span class="muted"><?= __('redirects.form.to_label') ?></span>
                        <input type="text" name="to" placeholder="<?= __('redirects.form.to_placeholder') ?>" required>
                    </label>
                </div>
                <div class="redirects-row">
                    <label class="stack redirects-code-label">
                        <span class="muted"><?= __('redirects.form.code_label') ?></span>
                        <select name="status">
                            <option value="301"><?= __('redirects.form.code_301') ?></option>
                            <option value="302"><?= __('redirects.form.code_302') ?></option>
                            <option value="307"><?= __('redirects.form.code_307') ?></option>
                            <option value="308"><?= __('redirects.form.code_308') ?></option>
                        </select>
                    </label>
                    <label class="redirects-regexp-label">
                        <input type="checkbox" name="is_regexp" value="1" id="is_regexp_cb">
                        <span>Regexp <span class="muted redirects-note">(use <code>$1</code> in target)</span></span>
                    </label>
                    <div class="redirects-submit-wrap">
                        <button class="btn primary" type="submit"><?= __('redirects.form.save') ?></button>
                    </div>
                </div>
                <div id="regexp-hint" class="muted redirects-regexp-hint u-hide">
                    Example — From: <code>~^/old/(.+)$~</code> &nbsp; To: <code>/new/$1</code>
                </div>
            </form>

            <details class="redirects-help">
                <summary>How to use redirects</summary>
                <div class="redirects-help-body">
                    <div class="redirects-help-col">
                        <p><strong>Exact redirect</strong></p>
                        <p>Matches the path literally. Use for simple one-to-one moves.</p>
                        <table class="redirects-help-table">
                            <tr><th>From</th><th>To</th></tr>
                            <tr><td><code>/about-us</code></td><td><code>/about</code></td></tr>
                            <tr><td><code>/old-page</code></td><td><code>https://example.com/new</code></td></tr>
                        </table>
                    </div>
                    <div class="redirects-help-col">
                        <p><strong>Regexp redirect</strong> <span class="pill pill-accent-mark">~</span></p>
                        <p>Use PCRE syntax with a delimiter (e.g. <code>~</code> or <code>#</code>). Capture groups <code>$1</code>, <code>$2</code>… are substituted in the target.</p>
                        <table class="redirects-help-table">
                            <tr><th>From (pattern)</th><th>To</th><th>Result</th></tr>
                            <tr>
                                <td><code>~^/blog/(.+)$~</code></td>
                                <td><code>/articles/$1</code></td>
                                <td><code>/blog/hello</code> → <code>/articles/hello</code></td>
                            </tr>
                            <tr>
                                <td><code>~^/products/(\d+)~</code></td>
                                <td><code>/shop/item/$1</code></td>
                                <td><code>/products/42</code> → <code>/shop/item/42</code></td>
                            </tr>
                            <tr>
                                <td><code>~^/en/(.*)~i</code></td>
                                <td><code>/$1</code></td>
                                <td><code>/en/about</code> → <code>/about</code></td>
                            </tr>
                        </table>
                        <p class="muted redirects-note-block">Exact redirects are checked first. Among regexp rules the first match wins (ordered by ID).</p>
                    </div>
                </div>
            </details>

            <div class="table-wrap redirects-table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= __('redirects.table.from') ?></th>
                            <th><?= __('redirects.table.to') ?></th>
                            <th><?= __('redirects.table.status') ?></th>
                            <th><?= __('redirects.table.hits') ?></th>
                            <th><?= __('redirects.table.last_hit') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="7" class="muted"><?= __('redirects.empty') ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <?php if (!empty($row['is_regexp'])): ?>
                                        <span class="pill pill-accent-mark pill-mr">~</span>
                                    <?php endif; ?>
                                    <code><?= htmlspecialchars($row['from_path']) ?></code>
                                </td>
                                <td><a href="<?= htmlspecialchars($row['to_url']) ?>" target="_blank"><?= htmlspecialchars($row['to_url']) ?></a></td>
                                <td><?= (int)$row['status_code'] ?></td>
                                <td><?= (int)$row['hits'] ?></td>
                                <td><?= htmlspecialchars($row['last_hit'] ?: '—') ?></td>
                                <td>
                                    <form method="post" action="<?= htmlspecialchars($ap . '/redirects/delete') ?>"
                                          onsubmit="return confirm('Delete this redirect?')">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn danger small">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        document.getElementById('is_regexp_cb').addEventListener('change', function() {
            document.getElementById('regexp-hint').classList.toggle('u-hide', !this.checked);
        });
        </script>
        <?php
        $content    = ob_get_clean();
        $title      = __('redirects.page_title');
        $showSidebar = true;
        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }
}
