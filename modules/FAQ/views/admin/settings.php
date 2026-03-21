<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('faq.settings.title') ?></p>
            <h3><?= __('faq.settings.subtitle') ?></h3>
        </div>
    </div>
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert success"><?= __('faq.settings.saved') ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="muted"><?= __('faq.settings.description') ?></div>

        <div class="grid two">
            <label class="field locale-ru">
                <span><?= __('faq.settings.seo_title_ru') ?></span>
                <input type="text" name="faq_seo_title_ru" value="<?= htmlspecialchars((string)($settings['seo_title_ru'] ?? '')) ?>" placeholder="FAQ о татуировках">
            </label>
            <label class="field locale-en">
                <span><?= __('faq.settings.seo_title_en') ?></span>
                <input type="text" name="faq_seo_title_en" value="<?= htmlspecialchars((string)($settings['seo_title_en'] ?? '')) ?>" placeholder="Tattoo FAQ">
            </label>
        </div>

        <div class="grid two">
            <label class="field locale-ru">
                <span><?= __('faq.settings.seo_desc_ru') ?></span>
                <textarea name="faq_seo_desc_ru" rows="3" placeholder="Краткое SEO-описание страницы FAQ"><?= htmlspecialchars((string)($settings['seo_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field locale-en">
                <span><?= __('faq.settings.seo_desc_en') ?></span>
                <textarea name="faq_seo_desc_en" rows="3" placeholder="Short SEO description for FAQ page"><?= htmlspecialchars((string)($settings['seo_desc_en'] ?? '')) ?></textarea>
            </label>
        </div>

        <div class="grid two">
            <label class="field locale-ru">
                <span><?= __('faq.settings.og_title_ru') ?></span>
                <input type="text" name="faq_og_title_ru" value="<?= htmlspecialchars((string)($settings['og_title_ru'] ?? '')) ?>" placeholder="FAQ о татуировках">
            </label>
            <label class="field locale-en">
                <span><?= __('faq.settings.og_title_en') ?></span>
                <input type="text" name="faq_og_title_en" value="<?= htmlspecialchars((string)($settings['og_title_en'] ?? '')) ?>" placeholder="Tattoo FAQ">
            </label>
        </div>

        <div class="grid two">
            <label class="field locale-ru">
                <span><?= __('faq.settings.og_desc_ru') ?></span>
                <textarea name="faq_og_desc_ru" rows="3" placeholder="Описание для Open Graph на русском"><?= htmlspecialchars((string)($settings['og_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field locale-en">
                <span><?= __('faq.settings.og_desc_en') ?></span>
                <textarea name="faq_og_desc_en" rows="3" placeholder="Open Graph description in English"><?= htmlspecialchars((string)($settings['og_desc_en'] ?? '')) ?></textarea>
            </label>
        </div>

        <label class="field">
            <span><?= __('faq.settings.og_image') ?></span>
            <input type="text" name="faq_og_image" value="<?= htmlspecialchars((string)($settings['og_image'] ?? '')) ?>" placeholder="https://.../faq-og.jpg">
        </label>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('faq.settings.save') ?></button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = __('faq.settings.page_title');
include APP_ROOT . '/modules/Admin/views/layout.php';
