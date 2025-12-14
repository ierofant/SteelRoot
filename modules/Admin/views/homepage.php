<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$s = $settings ?? [];
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('homepage.title') ?></p>
            <h3><?= __('homepage.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('homepage.action.back_admin') ?></a>
    </div>
    <?php if (!empty($saved)): ?><div class="alert success"><?= __('homepage.saved') ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="card subtle stack">
            <p class="eyebrow"><?= __('homepage.hero.section') ?></p>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.eyebrow_ru') ?></span>
                    <input type="text" name="home_hero_eyebrow_ru" value="<?= htmlspecialchars($s['home_hero_eyebrow_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.hero_eyebrow_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.eyebrow_en') ?></span>
                    <input type="text" name="home_hero_eyebrow_en" value="<?= htmlspecialchars($s['home_hero_eyebrow_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.hero_eyebrow_en') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.title') ?></span>
                    <input type="text" name="home_hero_title" value="<?= htmlspecialchars($s['home_hero_title'] ?? 'SteelRoot') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.subtitle') ?></span>
                    <input type="text" name="home_hero_subtitle" value="<?= htmlspecialchars($s['home_hero_subtitle'] ?? __('homepage.defaults.hero_subtitle')) ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.badge') ?></span>
                    <input type="text" name="home_hero_badge" value="<?= htmlspecialchars($s['home_hero_badge'] ?? '') ?>" placeholder="<?= __('homepage.hero.badge_placeholder') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.background') ?></span>
                    <input type="text" name="home_hero_background" value="<?= htmlspecialchars($s['home_hero_background'] ?? '') ?>" placeholder="<?= __('homepage.hero.background_placeholder') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.cta_text') ?></span>
                    <input type="text" name="home_hero_cta_text" value="<?= htmlspecialchars($s['home_hero_cta_text'] ?? __('homepage.defaults.hero_cta')) ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.cta_url') ?></span>
                    <input type="text" name="home_hero_cta_url" value="<?= htmlspecialchars($s['home_hero_cta_url'] ?? '/contact') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field checkbox">
                    <input type="checkbox" name="home_show_secondary_cta" value="1" <?= !empty($s['home_show_secondary_cta']) ? 'checked' : '' ?>>
                    <span><?= __('homepage.hero.secondary_toggle') ?></span>
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.align') ?></span>
                    <select name="home_hero_align">
                        <?php $align = $s['home_hero_align'] ?? 'left'; ?>
                        <option value="left" <?= $align === 'left' ? 'selected' : '' ?>><?= __('homepage.hero.align_left') ?></option>
                        <option value="center" <?= $align === 'center' ? 'selected' : '' ?>><?= __('homepage.hero.align_center') ?></option>
                        <option value="right" <?= $align === 'right' ? 'selected' : '' ?>><?= __('homepage.hero.align_right') ?></option>
                    </select>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.secondary_text') ?></span>
                    <input type="text" name="home_secondary_cta_text" value="<?= htmlspecialchars($s['home_secondary_cta_text'] ?? '') ?>" placeholder="<?= __('homepage.hero.secondary_text_placeholder') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.secondary_url') ?></span>
                    <input type="text" name="home_secondary_cta_url" value="<?= htmlspecialchars($s['home_secondary_cta_url'] ?? '') ?>" placeholder="/price.pdf">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.overlay') ?></span>
                    <input type="number" step="0.05" min="0" max="1" name="home_hero_overlay" value="<?= htmlspecialchars($s['home_hero_overlay'] ?? '0.4') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.section_padding') ?></span>
                    <input type="number" name="home_section_padding" value="<?= htmlspecialchars($s['home_section_padding'] ?? 80) ?>" min="24" max="200">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.hero.layout') ?></span>
                    <?php $layout = $s['home_layout_mode'] ?? 'wide'; ?>
                    <select name="home_layout_mode">
                        <option value="wide" <?= $layout === 'wide' ? 'selected' : '' ?>><?= __('homepage.hero.layout_wide') ?></option>
                        <option value="boxed" <?= $layout === 'boxed' ? 'selected' : '' ?>><?= __('homepage.hero.layout_boxed') ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= __('homepage.hero.gallery_mode') ?></span>
                    <?php $gs = $s['home_gallery_style'] ?? 'lightbox'; ?>
                    <select name="home_gallery_style">
                        <option value="lightbox" <?= $gs === 'lightbox' ? 'selected' : '' ?>><?= __('homepage.hero.gallery_lightbox') ?></option>
                        <option value="page" <?= $gs === 'page' ? 'selected' : '' ?>><?= __('homepage.hero.gallery_page') ?></option>
                    </select>
                </label>
            </div>
            <label class="field checkbox">
                <input type="checkbox" name="home_show_stats" value="1" <?= ($s['home_show_stats'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span><?= __('homepage.hero.show_stats') ?></span>
            </label>
        </div>

        <div class="grid two">
            <label class="field checkbox">
                <input type="checkbox" name="home_show_gallery" value="1" <?= !empty($s['home_show_gallery']) ? 'checked' : '' ?>>
                <span><?= __('homepage.gallery.show') ?></span>
            </label>
            <label class="field">
                <span><?= __('homepage.gallery.limit') ?></span>
                <input type="number" name="home_gallery_limit" value="<?= htmlspecialchars($s['home_gallery_limit'] ?? 6) ?>" min="1" max="30">
            </label>
        </div>
        <label class="field">
            <span><?= __('homepage.gallery.order') ?></span>
            <input type="number" name="home_order_gallery" value="<?= htmlspecialchars($s['home_order_gallery'] ?? 1) ?>" min="1" max="10">
        </label>

        <div class="grid two">
            <label class="field checkbox">
                <input type="checkbox" name="home_show_articles" value="1" <?= !empty($s['home_show_articles']) ? 'checked' : '' ?>>
                <span><?= __('homepage.articles.show') ?></span>
            </label>
            <label class="field">
                <span><?= __('homepage.articles.limit') ?></span>
                <input type="number" name="home_articles_limit" value="<?= htmlspecialchars($s['home_articles_limit'] ?? 3) ?>" min="1" max="20">
            </label>
        </div>
        <label class="field">
            <span><?= __('homepage.articles.order') ?></span>
            <input type="number" name="home_order_articles" value="<?= htmlspecialchars($s['home_order_articles'] ?? 2) ?>" min="1" max="10">
        </label>

        <div class="card subtle stack">
            <p class="eyebrow"><?= __('homepage.localization.section') ?></p>
            <p class="muted"><?= __('homepage.localization.help') ?></p>

            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.stats_gallery_ru') ?></span>
                    <input type="text" name="home_stats_gallery_label_ru" value="<?= htmlspecialchars($s['home_stats_gallery_label_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.stats_gallery_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.stats_gallery_en') ?></span>
                    <input type="text" name="home_stats_gallery_label_en" value="<?= htmlspecialchars($s['home_stats_gallery_label_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.stats_gallery_en') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.stats_articles_ru') ?></span>
                    <input type="text" name="home_stats_articles_label_ru" value="<?= htmlspecialchars($s['home_stats_articles_label_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.stats_articles_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.stats_articles_en') ?></span>
                    <input type="text" name="home_stats_articles_label_en" value="<?= htmlspecialchars($s['home_stats_articles_label_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.stats_articles_en') ?>">
                </label>
            </div>

            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.gallery_title_ru') ?></span>
                    <input type="text" name="home_gallery_title_ru" value="<?= htmlspecialchars($s['home_gallery_title_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.gallery_title_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.gallery_title_en') ?></span>
                    <input type="text" name="home_gallery_title_en" value="<?= htmlspecialchars($s['home_gallery_title_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.gallery_title_en') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.gallery_cta_ru') ?></span>
                    <input type="text" name="home_gallery_cta_ru" value="<?= htmlspecialchars($s['home_gallery_cta_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.gallery_cta_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.gallery_cta_en') ?></span>
                    <input type="text" name="home_gallery_cta_en" value="<?= htmlspecialchars($s['home_gallery_cta_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.gallery_cta_en') ?>">
                </label>
            </div>

            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.articles_title_ru') ?></span>
                    <input type="text" name="home_articles_title_ru" value="<?= htmlspecialchars($s['home_articles_title_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.articles_title_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.articles_title_en') ?></span>
                    <input type="text" name="home_articles_title_en" value="<?= htmlspecialchars($s['home_articles_title_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.articles_title_en') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.articles_cta_ru') ?></span>
                    <input type="text" name="home_articles_cta_ru" value="<?= htmlspecialchars($s['home_articles_cta_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.articles_cta_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.articles_cta_en') ?></span>
                    <input type="text" name="home_articles_cta_en" value="<?= htmlspecialchars($s['home_articles_cta_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.articles_cta_en') ?>">
                </label>
            </div>

            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.custom_blocks_title_ru') ?></span>
                    <input type="text" name="home_custom_blocks_title_ru" value="<?= htmlspecialchars($s['home_custom_blocks_title_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.custom_blocks_title_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.custom_blocks_title_en') ?></span>
                    <input type="text" name="home_custom_blocks_title_en" value="<?= htmlspecialchars($s['home_custom_blocks_title_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.custom_blocks_title_en') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('homepage.localization.custom_block_cta_ru') ?></span>
                    <input type="text" name="home_custom_block_cta_ru" value="<?= htmlspecialchars($s['home_custom_block_cta_ru'] ?? '') ?>" placeholder="<?= __('homepage.defaults.custom_block_cta_ru') ?>">
                </label>
                <label class="field">
                    <span><?= __('homepage.localization.custom_block_cta_en') ?></span>
                    <input type="text" name="home_custom_block_cta_en" value="<?= htmlspecialchars($s['home_custom_block_cta_en'] ?? '') ?>" placeholder="<?= __('homepage.defaults.custom_block_cta_en') ?>">
                </label>
            </div>
        </div>

        <div class="card subtle stack">
            <p class="eyebrow"><?= __('homepage.custom.section') ?></p>
            <p class="muted"><?= __('homepage.custom.help') ?></p>
            <label class="field">
                <span><?= __('homepage.custom.blocks') ?></span>
                <textarea name="home_custom_blocks" rows="6" placeholder='<?= __('homepage.custom.blocks_placeholder') ?>'><?= htmlspecialchars($s['home_custom_blocks'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('homepage.custom.css') ?></span>
                <textarea name="home_custom_css" rows="4" placeholder="<?= __('homepage.custom.css_placeholder') ?>"><?= htmlspecialchars($s['home_custom_css'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('homepage.action.save') ?></button>
            <a class="btn ghost" href="/"><?= __('homepage.action.to_site') ?></a>
        </div>
    </form>
</div>
<?php
$title = __('homepage.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
