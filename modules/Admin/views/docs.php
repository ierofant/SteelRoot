<?php ob_start(); ?>
<?php $currentTab = $tab ?? 'user'; ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('docs.title') ?></p>
            <h3><?= __('docs.subtitle') ?></h3>
        </div>
        <div class="form-actions" style="gap:8px;">
            <a class="btn ghost <?= $currentTab === 'user' ? 'active' : '' ?>" href="?tab=user"><?= __('docs.tab.user') ?></a>
            <a class="btn ghost <?= $currentTab === 'dev' ? 'active' : '' ?>" href="?tab=dev"><?= __('docs.tab.dev') ?></a>
        </div>
    </div>

    <?php if ($currentTab === 'user'): ?>
        <div class="stack">
            <div class="muted"><?= __('docs.user.intro') ?></div>
            <div class="form-actions" style="gap:8px;">
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/docs/support') ?>"><?= __('docs.support.link') ?></a>
        </div>
            <div class="card soft stack docs-block">
                <p class="eyebrow"><?= __('docs.user.section.install.title') ?></p>
                <ul class="docs-list">
                    <li><?= __('docs.user.section.install.theme') ?></li>
                    <li><?= __('docs.user.section.install.navigation') ?></li>
                    <li><?= __('docs.user.section.install.homepage') ?></li>
                    <li><?= __('docs.user.section.install.articles') ?></li>
                    <li><?= __('docs.user.section.install.gallery') ?></li>
                    <li><?= __('docs.user.section.install.forms') ?></li>
                    <li><?= __('docs.user.section.install.cache') ?></li>
                    <li><?= __('docs.user.section.install.security') ?></li>
                    <li><?= __('docs.user.section.install.backup') ?></li>
                    <li><?= __('docs.user.section.install.contacts') ?></li>
                </ul>
            </div>
            <div class="grid two">
                <div class="card soft stack docs-block">
                    <p class="eyebrow"><?= __('docs.user.section.setup.title') ?></p>
                    <ul class="docs-list">
                        <li><?= __('docs.user.section.setup.theme') ?></li>
                        <li><?= __('docs.user.section.setup.menu') ?></li>
                        <li><?= __('docs.user.section.setup.homepage') ?></li>
                        <li><?= __('docs.user.section.setup.og') ?></li>
                        <li><?= __('docs.user.section.setup.modules') ?></li>
                    </ul>
                </div>
                <div class="card soft stack docs-block">
                    <p class="eyebrow"><?= __('docs.user.section.content.title') ?></p>
                    <ul class="docs-list">
                        <li><?= __('docs.user.section.content.articles') ?></li>
                        <li><?= __('docs.user.section.content.gallery') ?></li>
                        <li><?= __('docs.user.section.content.forms') ?></li>
                        <li><?= __('docs.user.section.content.moderation') ?></li>
                        <li><?= __('docs.user.section.content.media') ?></li>
                        <li><?= __('docs.user.section.content.forms_spam') ?></li>
                        <li><?= __('docs.user.section.content.cache') ?></li>
                    </ul>
                </div>
            </div>
            <div class="card soft stack docs-block">
                <p class="eyebrow"><?= __('docs.user.section.support.title') ?></p>
                <ul class="docs-list">
                    <li><?= __('docs.user.section.support.security') ?></li>
                    <li><?= __('docs.user.section.support.backup') ?></li>
                    <li><?= __('docs.user.section.support.contacts') ?></li>
                    <li><?= __('docs.user.section.support.sitemap') ?></li>
                    <li><?= __('docs.user.section.support.pwa') ?></li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="stack">
            <div class="muted"><?= __('docs.dev.intro') ?></div>
            <div class="grid two">
                <div class="card soft stack docs-block">
                    <p class="eyebrow"><?= __('docs.dev.section.structure.title') ?></p>
                    <ul class="docs-list">
                        <li><?= __('docs.dev.section.structure.modules') ?></li>
                        <li><?= __('docs.dev.section.structure.templates') ?></li>
                        <li><?= __('docs.dev.section.structure.assets') ?></li>
                        <li><?= __('docs.dev.section.structure.migrations') ?></li>
                        <li><?= __('docs.dev.section.structure.lang') ?></li>
                    </ul>
                </div>
                <div class="card soft stack docs-block">
                    <p class="eyebrow"><?= __('docs.dev.section.api.title') ?></p>
                    <ul class="docs-list">
                        <li><?= __('docs.dev.section.api.routes') ?></li>
                        <li><?= __('docs.dev.section.api.settings') ?></li>
                        <li><?= __('docs.dev.section.api.hooks') ?></li>
                        <li><?= __('docs.dev.section.api.cache') ?></li>
                        <li><?= __('docs.dev.section.api.csrf') ?></li>
                    </ul>
                </div>
            </div>
            <div class="card soft stack docs-block">
                <p class="eyebrow"><?= __('docs.dev.section.templates.title') ?></p>
                <ul class="docs-list">
                    <li><?= __('docs.dev.section.templates.structure') ?></li>
                    <li><?= __('docs.dev.section.templates.layout') ?></li>
                    <li><?= __('docs.dev.section.templates.variables') ?></li>
                    <li><?= __('docs.dev.section.templates.assets') ?></li>
                    <li><?= __('docs.dev.section.templates.upload') ?></li>
                </ul>
            </div>
            <div class="card soft stack docs-block">
                <p class="eyebrow"><?= __('docs.dev.section.practices.title') ?></p>
                <ul class="docs-list">
                    <li><?= __('docs.dev.section.practices.theme') ?></li>
                    <li><?= __('docs.dev.section.practices.i18n') ?></li>
                    <li><?= __('docs.dev.section.practices.security') ?></li>
                    <li><?= __('docs.dev.section.practices.assets') ?></li>
                    <li><?= __('docs.dev.section.practices.migrations') ?></li>
                    <li><?= __('docs.dev.section.practices.dependencies') ?></li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
