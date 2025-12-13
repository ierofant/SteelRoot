<?php
namespace App\Services;

use Core\Database;
use Core\Csrf;

class EmbedFormService
{
    private Database $db;
    private SettingsService $settings;

    public function __construct(Database $db, SettingsService $settings)
    {
        $this->db = $db;
        $this->settings = $settings;
    }

    public function render(string $slug, string $locale, array $state = []): string
    {
        $form = $this->load($slug);
        if (!$form || empty($form['enabled'])) {
            return '';
        }
        $fields = json_decode($form['fields'] ?? '[]', true) ?: [];
        $csrf = Csrf::token($this->tokenName($slug));
        $successMessage = $locale === 'ru'
            ? ($form['success_ru'] ?: ($form['success_en'] ?? ''))
            : ($form['success_en'] ?: ($form['success_ru'] ?? ''));
        $errors = $state['errors'] ?? [];
        $old = $state['old'] ?? [];
        $sent = !empty($state['sent']);

        ob_start();
        ?>
        <?php if ($sent && $successMessage): ?>
            <div class="alert success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if (!$sent): ?>
            <?php if ($errors): ?>
                <div class="alert danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="" class="stack embed-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="_embed_form" value="<?= htmlspecialchars($slug) ?>">
                <?php foreach ($fields as $field): ?>
                    <?php
                        $name = $field['name'] ?? '';
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                        $required = !empty($field['required']);
                        $value = $old[$name] ?? '';
                    ?>
                    <label class="field">
                        <span><?= htmlspecialchars($label) ?><?= $required ? ' *' : '' ?></span>
                        <?php if ($type === 'textarea'): ?>
                            <textarea name="<?= htmlspecialchars($name) ?>" rows="4" <?= $required ? 'required' : '' ?>><?= htmlspecialchars($value) ?></textarea>
                        <?php else: ?>
                            <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>" <?= $required ? 'required' : '' ?>>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn primary"><?= __('submit') ?></button>
                </div>
            </form>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public function handle(string $slug, string $locale, array $data): array
    {
        $form = $this->load($slug);
        if (!$form || empty($form['enabled'])) {
            return ['sent' => false, 'errors' => [__('form_disabled')]];
        }
        if (!Csrf::check($this->tokenName($slug), $data['_token'] ?? null)) {
            return ['sent' => false, 'errors' => [__('invalid_csrf')]];
        }
        $fields = json_decode($form['fields'] ?? '[]', true) ?: [];
        $errors = [];
        $filtered = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $value = trim((string)($data[$name] ?? ''));
            if (!empty($field['required']) && $value === '') {
                $errors[] = ($field['label'] ?? $name) . ': ' . __('forms.error.required');
            }
            $filtered[$name] = $value;
        }
        if ($errors) {
            return ['sent' => false, 'errors' => $errors, 'old' => $filtered];
        }
        $to = trim($form['recipient_email'] ?? '') ?: (string)$this->settings->get('contact_email', '');
        $subject = 'Form ' . ($form['name'] ?? $slug);
        $body = '';
        foreach ($filtered as $k => $v) {
            $body .= $k . ': ' . $v . "\n";
        }
        if ($to) {
            @mail($to, $subject, $body, 'From: no-reply@example.com');
        }
        return ['sent' => true, 'errors' => [], 'old' => []];
    }

    private function load(string $slug): ?array
    {
        return $this->db->fetch("SELECT * FROM embed_forms WHERE slug = ?", [$slug]) ?: null;
    }

    private function tokenName(string $slug): string
    {
        return 'embed_form_' . $slug;
    }
}
