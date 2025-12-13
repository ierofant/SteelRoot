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
        $this->db = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        $items = $this->redirects->all();
        $flash = null;
        if (!empty($request->query['msg'])) {
            $flash = match ($request->query['msg']) {
                'created' => 'Redirect сохранён',
                'cleared' => 'Кеш редиректов очищен',
                default => null,
            };
        }
        $content = $this->render($items, Csrf::token('redirects_admin'), $flash);
        return new Response($content);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('redirects_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $from = trim($request->body['from'] ?? '');
        $to = trim($request->body['to'] ?? '');
        $status = (int)($request->body['status'] ?? 301);
        if ($from === '' || $to === '') {
            return new Response('Missing data', 400);
        }
        $this->redirects->create($from, $to, $status);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/redirects?msg=created']);
    }

    public function clearCache(Request $request): Response
    {
        if (!Csrf::check('redirects_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $this->redirects->clearCache();
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/redirects?msg=cleared']);
    }

    private function render(array $items, string $csrf, ?string $flash): string
    {
        ob_start();
        ?>
        <div class="card stack glass">
            <div class="card-header">
                <div>
                    <p class="eyebrow"><?= __('redirects.title') ?></p>
                    <h3><?= __('redirects.subtitle') ?></h3>
                    <p class="muted"><?= __('redirects.description') ?></p>
                </div>
                <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/redirects/clear-cache') ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button class="btn ghost" type="submit"><?= __('redirects.action.clear_cache') ?></button>
                </form>
            </div>
            <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
            <form class="grid two redirects-form form-dark" method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/redirects') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                <label class="stack">
                    <span class="muted"><?= __('redirects.form.from_label') ?></span>
                    <input type="text" name="from" placeholder="<?= __('redirects.form.from_placeholder') ?>" required>
                </label>
                <label class="stack">
                    <span class="muted"><?= __('redirects.form.to_label') ?></span>
                    <input type="text" name="to" placeholder="<?= __('redirects.form.to_placeholder') ?>" required>
                </label>
                <label class="stack">
                    <span class="muted"><?= __('redirects.form.code_label') ?></span>
                    <select name="status">
                        <option value="301"><?= __('redirects.form.code_301') ?></option>
                        <option value="302"><?= __('redirects.form.code_302') ?></option>
                        <option value="307"><?= __('redirects.form.code_307') ?></option>
                        <option value="308"><?= __('redirects.form.code_308') ?></option>
                    </select>
                </label>
                <div class="stack" style="justify-content:flex-end;">
                    <button class="btn primary" type="submit" style="align-self:flex-start;"><?= __('redirects.form.save') ?></button>
                </div>
            </form>

            <div class="table-wrap" style="margin-top:12px;">
                <table class="data">
                    <thead>
                        <tr>
                            <th>ID</th><th><?= __('redirects.table.from') ?></th><th><?= __('redirects.table.to') ?></th><th><?= __('redirects.table.status') ?></th><th><?= __('redirects.table.hits') ?></th><th><?= __('redirects.table.last_hit') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="6" class="muted"><?= __('redirects.empty') ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td><code><?= htmlspecialchars($row['from_path']) ?></code></td>
                                <td><a href="<?= htmlspecialchars($row['to_url']) ?>" target="_blank"><?= htmlspecialchars($row['to_url']) ?></a></td>
                                <td><?= (int)$row['status_code'] ?></td>
                                <td><?= (int)$row['hits'] ?></td>
                                <td><?= htmlspecialchars($row['last_hit'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        $title = __('redirects.page_title');
        $showSidebar = true;
        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }
}
