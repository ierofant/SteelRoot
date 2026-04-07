<?php ob_start(); ?>
<form method="post" class="card">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <div class="tabs settings-tabs" id="settings-tabs">
        <button type="button" class="btn ghost small tab-btn active" data-tab="basic"><?= __('settings.tab.basic') ?></button>
        <button type="button" class="btn ghost small tab-btn" data-tab="infra"><?= __('settings.tab.infra') ?></button>
        <button type="button" class="btn ghost small tab-btn" data-tab="url"><?= __('settings.tab.url_query') ?></button>
    </div>

    <div class="tab-pane is-active" data-pane="basic">
        <div class="settings-compact-grid settings-compact-grid--two">
            <section class="card subtle stack settings-compact-card">
                <div>
                    <p class="eyebrow"><?= __('settings.section.basic') ?></p>
                    <h3><?= __('settings.section.basic') ?></h3>
                </div>
                <div class="grid two settings-compact-fields">
                    <label class="field">
                        <span><?= __('settings.field.site_name') ?></span>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'SteelRoot') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.contact_email') ?></span>
                        <input type="text" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.site_url') ?></span>
                        <input type="text" name="site_url" value="<?= htmlspecialchars($settings['site_url'] ?? '') ?>" placeholder="<?= __('settings.placeholder.site_url') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.theme') ?></span>
                        <select name="theme">
                            <option value="light" <?= (($settings['theme'] ?? 'light') === 'light') ? 'selected' : '' ?>><?= __('settings.theme.light') ?></option>
                            <option value="dark" <?= (($settings['theme'] ?? 'light') === 'dark') ? 'selected' : '' ?>><?= __('settings.theme.dark') ?></option>
                            <option value="custom" <?= (($settings['theme'] ?? 'light') === 'custom') ? 'selected' : '' ?>><?= __('settings.theme.custom') ?></option>
                        </select>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.theme_custom_url') ?></span>
                        <input type="text" name="theme_custom_url" value="<?= htmlspecialchars($settings['theme_custom_url'] ?? '') ?>" placeholder="<?= __('settings.placeholder.theme_custom_url') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.locale_mode') ?></span>
                        <select name="locale_mode">
                            <option value="ru" <?= (($settings['locale_mode'] ?? 'multi') === 'ru') ? 'selected' : '' ?>><?= __('settings.locale.ru') ?></option>
                            <option value="en" <?= (($settings['locale_mode'] ?? 'multi') === 'en') ? 'selected' : '' ?>><?= __('settings.locale.en') ?></option>
                            <option value="multi" <?= (($settings['locale_mode'] ?? 'multi') === 'multi') ? 'selected' : '' ?>><?= __('settings.locale.multi') ?></option>
                        </select>
                    </label>
                </div>
                <div class="settings-inline-checks">
                    <label class="field checkbox card search-settings-card">
                        <input type="checkbox" name="footer_copy_enabled" value="1" <?= !empty($settings['footer_copy_enabled']) ? 'checked' : '' ?>>
                        <span><?= __('settings.field.footer_copy_enabled') ?></span>
                    </label>
                </div>
            </section>

            <section class="card subtle stack settings-compact-card">
                <div>
                    <p class="eyebrow"><?= __('settings.section.uploads') ?></p>
                    <h3><?= __('settings.section.uploads') ?></h3>
                </div>
                <div class="settings-compact-grid settings-compact-grid--three">
                    <label class="field">
                        <span><?= __('settings.field.upload_max_mb') ?></span>
                        <input type="number" name="upload_max_mb" value="<?= htmlspecialchars((int) (($settings['upload_max_bytes'] ?? (5 * 1024 * 1024)) / (1024 * 1024))) ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.upload_max_width') ?></span>
                        <input type="number" name="upload_max_w" value="<?= htmlspecialchars($settings['upload_max_width'] ?? 8000) ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.upload_max_height') ?></span>
                        <input type="number" name="upload_max_h" value="<?= htmlspecialchars($settings['upload_max_height'] ?? 8000) ?>">
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.gallery_open_mode') ?></span>
                    <select name="gallery_open_mode">
                        <option value="lightbox" <?= (($settings['gallery_open_mode'] ?? 'lightbox') === 'lightbox') ? 'selected' : '' ?>><?= __('settings.gallery.mode.lightbox') ?></option>
                        <option value="page" <?= (($settings['gallery_open_mode'] ?? 'lightbox') === 'page') ? 'selected' : '' ?>><?= __('settings.gallery.mode.page') ?></option>
                    </select>
                </label>
            </section>
        </div>

        <div class="settings-compact-grid settings-compact-grid--two">
            <section class="card subtle stack settings-compact-card">
                <div>
                    <p class="eyebrow"><?= __('settings.section.captcha') ?></p>
                    <h3><?= __('settings.section.captcha') ?></h3>
                </div>
                <div class="grid two settings-compact-fields">
                    <label class="field">
                        <span><?= __('settings.field.captcha_provider') ?></span>
                        <select name="captcha_provider">
                            <option value="none" <?= (($settings['captcha_provider'] ?? 'none') === 'none') ? 'selected' : '' ?>><?= __('settings.captcha.none') ?></option>
                            <option value="google" <?= (($settings['captcha_provider'] ?? 'none') === 'google') ? 'selected' : '' ?>><?= __('settings.captcha.google') ?></option>
                            <option value="yandex" <?= (($settings['captcha_provider'] ?? 'none') === 'yandex') ? 'selected' : '' ?>><?= __('settings.captcha.yandex') ?></option>
                        </select>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.captcha_google_mode') ?></span>
                        <?php $googleMode = $settings['captcha_google_mode'] ?? 'v2'; ?>
                        <select name="captcha_google_mode">
                            <option value="v2" <?= $googleMode === 'v2' ? 'selected' : '' ?>><?= __('settings.captcha.google_v2') ?></option>
                            <option value="v3" <?= $googleMode === 'v3' ? 'selected' : '' ?>><?= __('settings.captcha.google_v3') ?></option>
                        </select>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.captcha_site_key') ?></span>
                        <input type="text" name="captcha_site_key" value="<?= htmlspecialchars($settings['captcha_site_key'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.captcha_secret_key') ?></span>
                        <input type="text" name="captcha_secret_key" value="<?= htmlspecialchars($settings['captcha_secret_key'] ?? '') ?>">
                    </label>
                </div>
                <div class="settings-inline-checks">
                    <label class="field checkbox card search-settings-card">
                        <input type="checkbox" name="captcha_login_enabled" value="1" <?= !empty($settings['captcha_login_enabled']) ? 'checked' : '' ?>>
                        <span><?= __('settings.field.captcha_login_enabled') ?></span>
                    </label>
                </div>
            </section>

            <section class="card subtle stack settings-compact-card">
                <div>
                    <p class="eyebrow"><?= __('settings.section.security') ?></p>
                    <h3><?= __('settings.section.security') ?></h3>
                </div>
                <label class="field">
                    <span><?= __('settings.field.admin_guard_key') ?></span>
                    <input type="text" name="admin_guard_key" value="<?= htmlspecialchars($settings['admin_guard_key'] ?? '') ?>" placeholder="<?= __('settings.placeholder.admin_guard_key') ?>">
                    <span class="muted"><?= __('settings.help.admin_guard_key') ?></span>
                </label>
                <label class="field">
                    <span><?= __('settings.field.admin_ip_regex') ?></span>
                    <input type="text" name="admin_ip_regex" value="<?= htmlspecialchars($settings['admin_ip_regex'] ?? '') ?>" placeholder="<?= __('settings.placeholder.admin_ip_regex') ?>">
                    <span class="muted"><?= __('settings.help.admin_ip_regex') ?></span>
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.admin_max_attempts') ?></span>
                        <input type="number" name="admin_max_attempts" value="<?= htmlspecialchars($settings['admin_max_attempts'] ?? 5) ?>" min="1" max="20">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.admin_lock_minutes') ?></span>
                        <input type="number" name="admin_lock_minutes" value="<?= htmlspecialchars($settings['admin_lock_minutes'] ?? 5) ?>" min="1" max="120">
                    </label>
                </div>
            </section>
        </div>

        <section class="card subtle stack settings-compact-card">
            <div>
                <p class="eyebrow"><?= __('settings.section.breadcrumbs') ?></p>
                <h3><?= __('settings.section.breadcrumbs') ?></h3>
            </div>
            <div class="settings-compact-grid settings-compact-grid--two">
                <div class="stack">
                    <label class="field checkbox card search-settings-card">
                        <input type="checkbox" name="breadcrumbs_enabled" value="1" <?= ($settings['breadcrumbs_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span><?= __('settings.field.breadcrumbs_enabled') ?></span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.breadcrumb_home') ?></span>
                        <input type="text" name="breadcrumb_home" value="<?= htmlspecialchars($settings['breadcrumb_home'] ?? 'Home') ?>">
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.breadcrumbs_custom') ?></span>
                    <textarea name="breadcrumbs_custom" rows="5" placeholder='<?= __('settings.placeholder.breadcrumbs_custom') ?>'><?= htmlspecialchars($settings['breadcrumbs_custom'] ?? '') ?></textarea>
                    <span class="muted"><?= __('settings.help.breadcrumbs_custom') ?></span>
                </label>
            </div>
        </section>
    </div>

    <div class="tab-pane" data-pane="infra">
        <div class="grid two">
            <div class="stack">
                <h3><?= __('settings.section.mail') ?></h3>
                <?php $mailDriver = $settings['mail_driver'] ?? 'smtp'; ?>
                <label class="field">
                    <span><?= __('settings.field.mail_driver') ?></span>
                    <select name="mail_driver">
                        <option value="smtp" <?= $mailDriver === 'smtp' ? 'selected' : '' ?>><?= __('settings.mail.driver.smtp') ?></option>
                        <option value="sendmail" <?= $mailDriver === 'sendmail' ? 'selected' : '' ?>><?= __('settings.mail.driver.sendmail') ?></option>
                        <option value="php" <?= $mailDriver === 'php' ? 'selected' : '' ?>><?= __('settings.mail.driver.php') ?></option>
                    </select>
                </label>
                <label class="field" data-mail-wrapper="smtp">
                    <span><?= __('settings.field.mail_host') ?></span>
                    <input type="text" name="mail_host" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_host'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_host') ?>">
                </label>
                <div class="grid two">
                    <label class="field" data-mail-wrapper="smtp">
                        <span><?= __('settings.field.mail_port') ?></span>
                        <input type="number" name="mail_port" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_port'] ?? 587) ?>">
                    </label>
                    <label class="field" data-mail-wrapper="smtp">
                        <span><?= __('settings.field.mail_secure') ?></span>
                        <?php $secure = $settings['mail_secure'] ?? 'tls'; ?>
                        <select name="mail_secure" data-mail-field="smtp">
                            <option value="none" <?= $secure === 'none' ? 'selected' : '' ?>><?= __('settings.mail.secure.none') ?></option>
                            <option value="ssl" <?= $secure === 'ssl' ? 'selected' : '' ?>><?= __('settings.mail.secure.ssl') ?></option>
                            <option value="tls" <?= $secure === 'tls' ? 'selected' : '' ?>><?= __('settings.mail.secure.tls') ?></option>
                        </select>
                    </label>
                </div>
                <label class="field" data-mail-wrapper="smtp">
                    <span><?= __('settings.field.mail_user') ?></span>
                    <input type="text" name="mail_user" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_user'] ?? '') ?>">
                </label>
                <label class="field" data-mail-wrapper="smtp">
                    <span><?= __('settings.field.mail_pass') ?></span>
                    <input type="password" name="mail_pass" data-mail-field="smtp" value="" autocomplete="new-password" placeholder="<?= __('settings.placeholder.mail_pass') ?>">
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.mail_from') ?></span>
                        <input type="text" name="mail_from" value="<?= htmlspecialchars($settings['mail_from'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_from') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.mail_from_name') ?></span>
                        <input type="text" name="mail_from_name" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_from_name') ?>">
                    </label>
                </div>
                <div class="card subtle stack">
                    <h4><?= __('settings.section.mail_templates') ?></h4>
                    <p class="muted"><?= __('settings.help.mail_template_vars') ?></p>
                    <div class="card soft stack">
                        <h5><?= __('settings.mail.block.registration') ?></h5>
                        <label class="field">
                            <span><?= __('settings.field.mail_registration_subject') ?></span>
                            <input type="text" name="mail_registration_subject" value="<?= htmlspecialchars($settings['mail_registration_subject'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_registration_subject') ?>">
                        </label>
                        <label class="field">
                            <span><?= __('settings.field.mail_registration_body') ?></span>
                            <textarea name="mail_registration_body" rows="6" placeholder="<?= __('settings.placeholder.mail_registration_body') ?>"><?= htmlspecialchars($settings['mail_registration_body'] ?? '') ?></textarea>
                        </label>
                    </div>
                    <div class="card soft stack">
                        <h5><?= __('settings.mail.block.reset') ?></h5>
                        <label class="field">
                            <span><?= __('settings.field.mail_reset_subject') ?></span>
                            <input type="text" name="mail_reset_subject" value="<?= htmlspecialchars($settings['mail_reset_subject'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_reset_subject') ?>">
                        </label>
                        <label class="field">
                            <span><?= __('settings.field.mail_reset_body') ?></span>
                            <textarea name="mail_reset_body" rows="6" placeholder="<?= __('settings.placeholder.mail_reset_body') ?>"><?= htmlspecialchars($settings['mail_reset_body'] ?? '') ?></textarea>
                        </label>
                    </div>
                    <div class="card soft stack">
                        <h5><?= __('settings.mail.block.notification') ?></h5>
                        <label class="field">
                            <span><?= __('settings.field.mail_notification_subject') ?></span>
                            <input type="text" name="mail_notification_subject" value="<?= htmlspecialchars($settings['mail_notification_subject'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_notification_subject') ?>">
                        </label>
                        <label class="field">
                            <span><?= __('settings.field.mail_notification_body') ?></span>
                            <textarea name="mail_notification_body" rows="6" placeholder="<?= __('settings.placeholder.mail_notification_body') ?>"><?= htmlspecialchars($settings['mail_notification_body'] ?? '') ?></textarea>
                        </label>
                    </div>
                </div>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.mail_test_to') ?></span>
                        <input type="email" name="mail_test_to" value="">
                    </label>
                    <label class="field">
                        <span>&nbsp;</span>
                        <span class="muted"><?= __('settings.help.mail_test_hint') ?></span>
                    </label>
                </div>
            </div>

            <div class="stack">
                <h3><?= __('settings.section.sessions') ?></h3>
                <?php $driver = $settings['session_driver'] ?? 'files'; ?>
                <label class="field">
                    <span><?= __('settings.field.session_driver') ?></span>
                    <select name="session_driver">
                        <option value="files" <?= $driver === 'files' ? 'selected' : '' ?>><?= __('settings.session.driver.files') ?></option>
                        <option value="redis" <?= $driver === 'redis' ? 'selected' : '' ?>><?= __('settings.session.driver.redis') ?></option>
                        <option value="memcached" <?= $driver === 'memcached' ? 'selected' : '' ?>><?= __('settings.session.driver.memcached') ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= __('settings.field.session_files_path') ?></span>
                    <input type="text" name="session_path" value="<?= htmlspecialchars($settings['session_path'] ?? (APP_ROOT . '/storage/tmp/sessions')) ?>">
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.session_redis_host') ?></span>
                        <input type="text" name="session_redis_host" value="<?= htmlspecialchars($settings['session_redis_host'] ?? '127.0.0.1') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.session_redis_port') ?></span>
                        <input type="number" name="session_redis_port" value="<?= htmlspecialchars($settings['session_redis_port'] ?? 6379) ?>">
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.session_redis_db') ?></span>
                    <input type="number" name="session_redis_db" value="<?= htmlspecialchars($settings['session_redis_db'] ?? 0) ?>">
                </label>
                <label class="field">
                    <span><?= __('settings.field.session_redis_password') ?></span>
                    <input type="password" name="session_redis_password" value="" placeholder="<?= __('settings.placeholder.session_redis_password') ?>">
                    <span class="muted"><?= __('settings.help.session_redis_password') ?></span>
                </label>
                <label class="field">
                    <span><?= __('settings.field.session_memcached_servers') ?></span>
                    <input type="text" name="session_memcached_servers" value="<?= htmlspecialchars($settings['session_memcached_servers'] ?? '') ?>" placeholder="<?= __('settings.placeholder.session_memcached_servers') ?>">
                </label>
            </div>
        </div>
    </div>

    <div class="tab-pane" data-pane="url">
        <?php $urlQueryMode = $settings['url_query_policy_mode'] ?? 'redirect'; ?>
        <div class="stack">
            <div class="card subtle stack">
                <div class="card-header">
                    <div>
                        <p class="eyebrow"><?= __('settings.section.url_query_policy') ?></p>
                        <h3><?= __('settings.section.url_query_policy') ?></h3>
                        <p class="muted"><?= __('settings.help.url_query_policy_overview') ?></p>
                    </div>
                </div>
                <div class="grid two">
                    <label class="field checkbox card search-settings-card">
                        <input type="checkbox" name="url_query_policy_enabled" value="1" <?= ($settings['url_query_policy_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span><?= __('settings.field.url_query_policy_enabled') ?></span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_policy_mode') ?></span>
                        <select name="url_query_policy_mode">
                            <option value="redirect" <?= $urlQueryMode === 'redirect' ? 'selected' : '' ?>><?= __('settings.url_query_policy.mode.redirect') ?></option>
                            <option value="404" <?= $urlQueryMode === '404' ? 'selected' : '' ?>><?= __('settings.url_query_policy.mode.404') ?></option>
                            <option value="ignore" <?= $urlQueryMode === 'ignore' ? 'selected' : '' ?>><?= __('settings.url_query_policy.mode.ignore') ?></option>
                        </select>
                        <span class="muted"><?= __('settings.help.url_query_policy_mode') ?></span>
                    </label>
                </div>
            </div>

            <div class="grid two">
                <div class="card subtle stack">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow"><?= __('settings.url_query.group.search') ?></p>
                            <h3><?= __('settings.url_query.group.search') ?></h3>
                            <p class="muted"><?= __('settings.url_query.help.csv') ?></p>
                        </div>
                    </div>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_search') ?></span>
                        <input type="text" name="url_query_allow_search" value="<?= htmlspecialchars($settings['url_query_allow_search'] ?? 'q,sources') ?>" placeholder="q,sources">
                        <span class="muted">`/search`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_forum_search') ?></span>
                        <input type="text" name="url_query_allow_forum_search" value="<?= htmlspecialchars($settings['url_query_allow_forum_search'] ?? 'q') ?>" placeholder="q">
                        <span class="muted">`/forum/search`</span>
                    </label>
                </div>

                <div class="card subtle stack">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow"><?= __('settings.url_query.group.tags') ?></p>
                            <h3><?= __('settings.url_query.group.tags') ?></h3>
                            <p class="muted"><?= __('settings.url_query.help.csv') ?></p>
                        </div>
                    </div>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_tags_index') ?></span>
                        <input type="text" name="url_query_allow_tags_index" value="<?= htmlspecialchars($settings['url_query_allow_tags_index'] ?? 'sort') ?>" placeholder="sort">
                        <span class="muted">`/tags`, `/tags/top`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_tags_entity') ?></span>
                        <input type="text" name="url_query_allow_tags_entity" value="<?= htmlspecialchars($settings['url_query_allow_tags_entity'] ?? 'ap,np,gp,pp') ?>" placeholder="ap,np,gp,pp">
                        <span class="muted">`/tags/{slug}`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_tags_gallery') ?></span>
                        <input type="text" name="url_query_allow_tags_gallery" value="<?= htmlspecialchars($settings['url_query_allow_tags_gallery'] ?? 'page,sort') ?>" placeholder="page,sort">
                        <span class="muted">`/tags/{slug}/gallery`</span>
                    </label>
                </div>
            </div>

            <div class="grid two">
                <div class="card subtle stack">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow"><?= __('settings.url_query.group.community') ?></p>
                            <h3><?= __('settings.url_query.group.community') ?></h3>
                            <p class="muted"><?= __('settings.url_query.help.csv') ?></p>
                        </div>
                    </div>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_forum_topic') ?></span>
                        <input type="text" name="url_query_allow_forum_topic" value="<?= htmlspecialchars($settings['url_query_allow_forum_topic'] ?? 'page,msg,error,reply_body,quote') ?>" placeholder="page,msg,error,reply_body,quote">
                        <span class="muted">`/forum/topic/*`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_profile') ?></span>
                        <input type="text" name="url_query_allow_profile" value="<?= htmlspecialchars($settings['url_query_allow_profile'] ?? 'tab,collection,msg,err') ?>" placeholder="tab,collection,msg,err">
                        <span class="muted">`/profile`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_profile_tab') ?></span>
                        <input type="text" name="url_query_allow_profile_tab" value="<?= htmlspecialchars($settings['url_query_allow_profile_tab'] ?? 'overview,settings,activity,community,collections') ?>" placeholder="overview,settings,activity,community,collections">
                        <span class="muted">`/profile?tab=` — <?= __('settings.field.url_query_allow_profile_tab_hint') ?></span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_comments_fragment') ?></span>
                        <input type="text" name="url_query_allow_comments_fragment" value="<?= htmlspecialchars($settings['url_query_allow_comments_fragment'] ?? '_xhr,entity_type,entity_id,return_url,comments_page') ?>" placeholder="_xhr,entity_type,entity_id,return_url,comments_page">
                        <span class="muted">`/comments/fragment`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_reset_password') ?></span>
                        <input type="text" name="url_query_allow_reset_password" value="<?= htmlspecialchars($settings['url_query_allow_reset_password'] ?? 'token') ?>" placeholder="token">
                        <span class="muted">`/reset-password`</span>
                    </label>
                </div>

                <div class="card subtle stack">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow"><?= __('settings.url_query.group.media') ?></p>
                            <h3><?= __('settings.url_query.group.media') ?></h3>
                            <p class="muted"><?= __('settings.url_query.help.csv') ?></p>
                        </div>
                    </div>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_piercing_try_on') ?></span>
                        <input type="text" name="url_query_allow_piercing_try_on" value="<?= htmlspecialchars($settings['url_query_allow_piercing_try_on'] ?? 'upload,render,saved') ?>" placeholder="upload,render,saved">
                        <span class="muted">`/piercing-try-on`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_gallery_view') ?></span>
                        <input type="text" name="url_query_allow_gallery_view" value="<?= htmlspecialchars($settings['url_query_allow_gallery_view'] ?? 'id') ?>" placeholder="id">
                        <span class="muted">`/gallery/view`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_gallery_photo') ?></span>
                        <input type="text" name="url_query_allow_gallery_photo" value="<?= htmlspecialchars($settings['url_query_allow_gallery_photo'] ?? 'msg,err') ?>" placeholder="msg,err">
                        <span class="muted">`/gallery/photo/*`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_gallery_list') ?></span>
                        <input type="text" name="url_query_allow_gallery_list" value="<?= htmlspecialchars($settings['url_query_allow_gallery_list'] ?? 'sort,tag,cat') ?>" placeholder="sort,tag,cat">
                        <span class="muted">`/gallery`, `/gallery/category/{slug}`</span>
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.url_query_allow_articles_list') ?></span>
                        <input type="text" name="url_query_allow_articles_list" value="<?= htmlspecialchars($settings['url_query_allow_articles_list'] ?? 'sort') ?>" placeholder="sort">
                        <span class="muted">`/articles`, `/articles/category/{slug}`</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn primary"><?= __('settings.action.save') ?></button>
        <span class="muted"><?= __('settings.note.apply_immediately') ?></span>
    </div>
</form>

<!-- menu builder removed; managed in Menu module -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mailDriver = document.querySelector('select[name="mail_driver"]');
    const smtpFields = document.querySelectorAll('[data-mail-field="smtp"]');
    const toggleSmtp = () => {
        const mode = mailDriver.value;
        const show = mode === 'smtp';
        smtpFields.forEach(el => {
            const wrapper = el.closest('[data-mail-wrapper="smtp"]');
            if (wrapper) {
                wrapper.classList.toggle('u-hide', !show);
            }
        });
    };
    mailDriver?.addEventListener('change', toggleSmtp);
    toggleSmtp();

    const tabs = document.querySelectorAll('.tab-btn');
    const panes = document.querySelectorAll('.tab-pane');
    tabs.forEach(btn => btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const target = btn.dataset.tab;
        panes.forEach(p => p.classList.toggle('is-active', p.dataset.pane === target));
    }));
});
</script>
<?php
$title = 'Settings';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
