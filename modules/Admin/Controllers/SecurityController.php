<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;
use App\Services\SecurityLog;

class SecurityController
{
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->settings = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $blockedAdmin = $this->blockedList('admin');
        $blockedSite = $this->blockedList('site');
        $events = SecurityLog::tail(200);
        $flash = $request->query['msg'] ?? null;
        $content = $this->render($blockedAdmin, $blockedSite, $events, Csrf::token('security_admin'), $flash);
        return new Response($content);
    }

    public function block(Request $request): Response
    {
        if (!Csrf::check('security_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $ip = trim($request->body['ip'] ?? '');
        $scope = $this->scope($request->body['scope'] ?? 'admin');
        if ($ip !== '') {
            $list = $this->blockedList($scope);
            if (!in_array($ip, $list, true)) {
                $list[] = $ip;
                $this->saveBlocked($scope, $list);
            }
        }
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security?msg=blocked']);
    }

    public function unblock(Request $request): Response
    {
        if (!Csrf::check('security_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $ip = trim($request->body['ip'] ?? '');
        $scope = $this->scope($request->body['scope'] ?? 'admin');
        $list = array_filter($this->blockedList($scope), fn($x) => $x !== $ip);
        $this->saveBlocked($scope, $list);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security?msg=unblocked']);
    }

    public function clearLogs(Request $request): Response
    {
        if (!Csrf::check('security_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        @file_put_contents(\App\Services\SecurityLog::filePath(), '');
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security?msg=cleared']);
    }

    private function blockedList(string $scope = 'admin'): array
    {
        $key = $scope === 'site' ? 'site_blocked_ips' : 'admin_blocked_ips';
        return array_values(array_filter(array_map('trim', explode(',', (string)$this->settings->get($key, '')))));
    }

    private function saveBlocked(string $scope, array $list): void
    {
        $key = $scope === 'site' ? 'site_blocked_ips' : 'admin_blocked_ips';
        $this->settings->set($key, implode(',', $list));
    }

    private function scope(string $raw): string
    {
        return $raw === 'site' ? 'site' : 'admin';
    }

    private function render(array $blockedAdmin, array $blockedSite, array $events, string $csrf, ?string $flash): string
    {
        ob_start();
        ?>
        <div class="card stack glass">
            <div class="card-header">
                <div>
                    <p class="eyebrow"><?= __('security.title') ?></p>
                    <h3><?= __('security.subtitle') ?></h3>
                    <p class="muted"><?= __('security.description') ?></p>
                </div>
            </div>
            <?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
            <div class="grid two">
                <form class="stack glass" style="padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);" method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security/block') ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                    <label class="stack">
                        <span class="muted"><?= __('security.form.block_label') ?></span>
                        <input class="input-ghost" type="text" name="ip" placeholder="<?= __('security.form.block_placeholder') ?>" required style="border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px;color:inherit;">
                    </label>
                    <label class="stack">
                        <span class="muted"><?= __('security.form.scope_label') ?></span>
                        <select name="scope" class="input-ghost" style="border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px;color:inherit;">
                            <option value="admin"><?= __('security.form.scope_admin') ?></option>
                            <option value="site"><?= __('security.form.scope_site') ?></option>
                        </select>
                    </label>
                    <button class="btn danger" type="submit" style="align-self:flex-start;"><?= __('security.form.block_button') ?></button>
                </form>
                <div class="stack">
                    <p class="muted"><?= __('security.blocked.title') ?></p>
                    <div class="pill-row" style="display:flex;flex-wrap:wrap;gap:8px;">
                        <strong><?= __('security.blocked.admin_label') ?></strong>
                        <?php if (empty($blockedAdmin)): ?><span class="muted"><?= __('security.blocked.empty') ?></span><?php endif; ?>
                        <?php foreach ($blockedAdmin as $ip): ?>
                            <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security/unblock') ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                                <input type="hidden" name="scope" value="admin">
                                <button class="pill" type="submit">✕ <?= htmlspecialchars($ip) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <strong style="margin-left:12px;"><?= __('security.blocked.site_label') ?></strong>
                        <?php if (empty($blockedSite)): ?><span class="muted"><?= __('security.blocked.empty') ?></span><?php endif; ?>
                        <?php foreach ($blockedSite as $ip): ?>
                            <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security/unblock') ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="ip" value="<?= htmlspecialchars($ip) ?>">
                                <input type="hidden" name="scope" value="site">
                                <button class="pill" type="submit">✕ <?= htmlspecialchars($ip) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="table-wrap" style="margin-top:12px;">
                <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/security/clear') ?>" style="margin-bottom:8px;">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="btn ghost small"><?= __('security.actions.clear_log') ?></button>
                </form>
                <table class="data">
                    <thead>
                        <tr><th><?= __('security.table.time') ?></th><th><?= __('security.table.type') ?></th><th><?= __('security.table.ip') ?></th><th><?= __('security.table.path') ?></th><th><?= __('security.table.data') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr><td colspan="5" class="muted"><?= __('security.table.empty') ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= htmlspecialchars($ev['ts'] ?? '') ?></td>
                                <td><?= htmlspecialchars($ev['type'] ?? '') ?></td>
                                <td><?= htmlspecialchars($ev['ip'] ?? '') ?></td>
                                <td><?= htmlspecialchars($ev['path'] ?? '') ?></td>
                                <td><small><?= htmlspecialchars(json_encode($ev['data'] ?? [], JSON_UNESCAPED_UNICODE)) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        $title = __('security.page_title');
        $showSidebar = true;
        ob_start();
        include __DIR__ . '/../views/layout.php';
        return ob_get_clean();
    }
}
