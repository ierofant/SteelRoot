<?php
declare(strict_types=1);

namespace Modules\Users\Services;

use App\Services\ProjectMailer;
use App\Services\SettingsService;
use Core\Database;
use Core\Logger;
use Core\ModuleSettings;
use Core\RateLimiter;

class MasterContactService
{
    private const STATUSES = ['new', 'viewed', 'in_progress', 'answered', 'closed', 'spam'];
    private const MAX_FILES = 5;
    private const MAX_FILE_SIZE = 10485760;
    private const BIND_TTL_SECONDS = 900;

    private Database $db;
    private ModuleSettings $moduleSettings;
    private SettingsService $settings;
    private UserRepository $users;
    private string $storageDir;
    private array $tableExists = [];

    public function __construct(Database $db, ModuleSettings $moduleSettings, SettingsService $settings, UserRepository $users)
    {
        $this->db = $db;
        $this->moduleSettings = $moduleSettings;
        $this->settings = $settings;
        $this->users = $users;
        $this->storageDir = APP_ROOT . '/storage/private/contact-requests';
    }

    public function ready(): bool
    {
        return $this->tableExists('master_contact_settings')
            && $this->tableExists('contact_requests')
            && $this->tableExists('contact_request_files');
    }

    public function globalSettings(): array
    {
        $settings = $this->moduleSettings->all('users');
        $moduleConfig = include APP_ROOT . '/modules/Users/config.php';
        return [
            'master_contact_enabled' => array_key_exists('master_contact_enabled', $settings) ? !empty($settings['master_contact_enabled']) : true,
            'master_contact_bot_username' => trim((string)($moduleConfig['master_contact_bot_username'] ?? '')),
            'master_verification_required' => array_key_exists('master_verification_required', $settings) ? !empty($settings['master_verification_required']) : false,
            'master_profiles_enabled' => array_key_exists('master_profiles_enabled', $settings) ? !empty($settings['master_profiles_enabled']) : true,
        ];
    }

    public function isEligibleMaster(array $master): bool
    {
        $global = $this->globalSettings();
        if (!$this->ready() || empty($global['master_contact_enabled']) || empty($global['master_profiles_enabled'])) {
            return false;
        }
        if (($master['status'] ?? '') !== 'active' || empty($master['is_master'])) {
            return false;
        }
        if (!empty($global['master_verification_required']) && empty($master['is_verified'])) {
            return false;
        }
        return true;
    }

    public function publicAvailability(array $master): array
    {
        $enabled = $this->isEligibleMaster($master);
        $settings = $enabled ? $this->settingsForMaster((int)($master['id'] ?? 0), $master) : $this->defaultSettings($master);
        $available = $enabled && !empty($settings['accept_requests']) && !empty($settings['show_contact_cta']);
        return [
            'available' => $available,
            'settings' => $settings,
        ];
    }

    public function settingsForMaster(int $masterId, ?array $master = null): array
    {
        $master = $master ?? $this->users->findFull($masterId) ?? [];
        $defaults = $this->defaultSettings($master);
        if (!$this->ready() || $masterId < 1) {
            return $defaults;
        }

        $row = $this->db->fetch('SELECT * FROM master_contact_settings WHERE master_id = ? LIMIT 1', [$masterId]) ?: [];
        if ($row === []) {
            $this->db->execute("
                INSERT INTO master_contact_settings (
                    master_id, accept_requests, show_contact_cta, notification_email, telegram_notifications_enabled, auto_reply_text, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 0, ?, NOW(), NOW())
            ", [
                $masterId,
                !empty($defaults['accept_requests']) ? 1 : 0,
                !empty($defaults['show_contact_cta']) ? 1 : 0,
                $defaults['notification_email'],
                $defaults['auto_reply_text'],
            ]);
            $row = $this->db->fetch('SELECT * FROM master_contact_settings WHERE master_id = ? LIMIT 1', [$masterId]) ?: [];
        }

        return array_merge($defaults, $row, [
            'accept_requests' => !empty($row['accept_requests']),
            'show_contact_cta' => !empty($row['show_contact_cta']),
            'telegram_notifications_enabled' => !empty($row['telegram_notifications_enabled']),
        ]);
    }

    public function updateSettings(int $masterId, array $data, ?array $master = null): void
    {
        $current = $this->settingsForMaster($masterId, $master);
        $preferTelegram = !empty($data['telegram_notifications_enabled']);
        if (array_key_exists('notification_channel', $data)) {
            $preferTelegram = trim((string)($data['notification_channel'] ?? '')) === 'telegram' && !empty($current['telegram_bound_at']);
        }
        $payload = [
            'accept_requests' => !empty($data['accept_requests']) ? 1 : 0,
            'show_contact_cta' => !empty($data['show_contact_cta']) ? 1 : 0,
            'notification_email' => $this->normalizeEmail($data['notification_email'] ?? $current['notification_email'] ?? null),
            'telegram_notifications_enabled' => $preferTelegram ? 1 : 0,
            'auto_reply_text' => $this->sanitizeText($data['auto_reply_text'] ?? $current['auto_reply_text'] ?? '', 500),
        ];

        $this->db->execute("
            UPDATE master_contact_settings
               SET accept_requests = :accept_requests,
                   show_contact_cta = :show_contact_cta,
                   notification_email = :notification_email,
                   telegram_notifications_enabled = :telegram_notifications_enabled,
                   auto_reply_text = :auto_reply_text,
                   updated_at = NOW()
             WHERE master_id = :master_id
        ", [
            ':accept_requests' => $payload['accept_requests'],
            ':show_contact_cta' => $payload['show_contact_cta'],
            ':notification_email' => $payload['notification_email'],
            ':telegram_notifications_enabled' => $payload['telegram_notifications_enabled'],
            ':auto_reply_text' => $payload['auto_reply_text'],
            ':master_id' => $masterId,
        ]);
    }

    public function createRequest(int $masterId, array $body, array $files, array $server, ?array $master = null, int $requesterUserId = 0): int
    {
        $master = $master ?? $this->users->findFull($masterId) ?? [];
        $availability = $this->publicAvailability($master);
        if (empty($availability['available'])) {
            throw new \RuntimeException('contact-unavailable');
        }

        $ip = trim((string)($server['REMOTE_ADDR'] ?? 'contact'));
        $limiter = new RateLimiter('master_contact_' . $masterId . '_' . $ip, 5, 900, true);
        if ($limiter->tooManyAttempts()) {
            throw new \RuntimeException('contact-rate-limit');
        }

        $payload = $this->validateRequestPayload($body);
        $normalizedFiles = $this->normalizeUploads($files['references'] ?? []);
        if (count($normalizedFiles) > self::MAX_FILES) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $requestId = $this->db->transaction(function () use ($masterId, $payload, $normalizedFiles, $server, $requesterUserId): int {
            $this->db->execute("
                INSERT INTO contact_requests (
                    master_id, requester_user_id, client_name, client_contact, preferred_contact_method, city, body_placement, approx_size,
                    request_summary, description, budget, target_date, coverup_flag, extra_notes,
                    status, ip_hash, user_agent_hash, created_at, updated_at
                ) VALUES (
                    :master_id, :requester_user_id, :client_name, :client_contact, :preferred_contact_method, :city, :body_placement, :approx_size,
                    :request_summary, :description, :budget, :target_date, :coverup_flag, :extra_notes,
                    'new', :ip_hash, :user_agent_hash, NOW(), NOW()
                )
            ", [
                ':master_id' => $masterId,
                ':requester_user_id' => $requesterUserId > 0 ? $requesterUserId : null,
                ':client_name' => $payload['client_name'],
                ':client_contact' => $payload['client_contact'],
                ':preferred_contact_method' => $payload['preferred_contact_method'],
                ':city' => $payload['city'],
                ':body_placement' => $payload['body_placement'],
                ':approx_size' => $payload['approx_size'],
                ':request_summary' => $payload['request_summary'],
                ':description' => $payload['description'],
                ':budget' => $payload['budget'],
                ':target_date' => $payload['target_date'],
                ':coverup_flag' => $payload['coverup_flag'],
                ':extra_notes' => $payload['extra_notes'],
                ':ip_hash' => hash('sha256', (string)($server['REMOTE_ADDR'] ?? '')),
                ':user_agent_hash' => hash('sha256', (string)($server['HTTP_USER_AGENT'] ?? '')),
            ]);
            $requestId = (int)$this->db->pdo()->lastInsertId();

            foreach ($normalizedFiles as $index => $upload) {
                $stored = $this->storeImage($requestId, $upload, $index + 1);
                $this->db->execute("
                    INSERT INTO contact_request_files (
                        request_id, path, original_name, mime_type, file_size, image_width, image_height, sort_order, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $requestId,
                    $stored['path'],
                    $stored['original_name'],
                    $stored['mime_type'],
                    $stored['file_size'],
                    $stored['image_width'],
                    $stored['image_height'],
                    $stored['sort_order'],
                ]);
            }

            return $requestId;
        });

        $limiter->hit();
        $this->notifyMaster($masterId, $requestId, $payload, $availability['settings']);
        return $requestId;
    }

    public function countForMaster(int $masterId, string $status = ''): int
    {
        if (!$this->ready() || $masterId < 1) {
            return 0;
        }

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $row = $this->db->fetch('SELECT COUNT(*) AS cnt FROM contact_requests WHERE master_id = ? AND status = ?', [$masterId, $status]);
        } else {
            $row = $this->db->fetch('SELECT COUNT(*) AS cnt FROM contact_requests WHERE master_id = ?', [$masterId]);
        }
        return (int)($row['cnt'] ?? 0);
    }

    public function listForMaster(int $masterId, int $limit = 20, int $offset = 0, string $status = ''): array
    {
        if (!$this->ready() || $masterId < 1) {
            return [];
        }

        $params = [$masterId];
        $where = 'WHERE cr.master_id = ?';
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND cr.status = ?';
            $params[] = $status;
        }
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $rows = $this->db->fetchAll("
            SELECT cr.*,
                   (SELECT COUNT(*) FROM contact_request_files f WHERE f.request_id = cr.id) AS files_count
              FROM contact_requests cr
              {$where}
             ORDER BY cr.created_at DESC, cr.id DESC
             LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return array_map(function (array $row): array {
            $row['files_count'] = (int)($row['files_count'] ?? 0);
            $row['coverup_flag'] = !empty($row['coverup_flag']);
            return $row;
        }, $rows);
    }

    public function findForMaster(int $masterId, int $requestId, bool $markViewed = false): ?array
    {
        if (!$this->ready() || $masterId < 1 || $requestId < 1) {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM contact_requests WHERE id = ? AND master_id = ? LIMIT 1', [$requestId, $masterId]);
        if (!$row) {
            return null;
        }

        if ($markViewed && (string)($row['status'] ?? '') === 'new') {
            $this->db->execute("
                UPDATE contact_requests
                   SET status = 'viewed',
                       viewed_at = IFNULL(viewed_at, NOW()),
                       updated_at = NOW()
                 WHERE id = ? AND master_id = ? AND status = 'new'
            ", [$requestId, $masterId]);
            $row['status'] = 'viewed';
            $row['viewed_at'] = $row['viewed_at'] ?? date('Y-m-d H:i:s');
        }

        $row['coverup_flag'] = !empty($row['coverup_flag']);
        $row['files'] = $this->filesForRequest($requestId);
        return $row;
    }

    public function updateStatus(int $masterId, int $requestId, string $status): bool
    {
        if (!$this->ready() || !in_array($status, self::STATUSES, true)) {
            return false;
        }

        $updated = $this->db->execute("
            UPDATE contact_requests
               SET status = ?,
                   viewed_at = CASE WHEN viewed_at IS NULL THEN NOW() ELSE viewed_at END,
                   updated_at = NOW()
             WHERE id = ? AND master_id = ?
        ", [$status, $requestId, $masterId]);

        return $updated > 0;
    }

    public function countForRequester(int $userId, string $status = ''): int
    {
        if (!$this->ready() || $userId < 1 || !$this->hasRequesterColumn()) {
            return 0;
        }

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $row = $this->db->fetch('SELECT COUNT(*) AS cnt FROM contact_requests WHERE requester_user_id = ? AND status = ?', [$userId, $status]);
        } else {
            $row = $this->db->fetch('SELECT COUNT(*) AS cnt FROM contact_requests WHERE requester_user_id = ?', [$userId]);
        }

        return (int)($row['cnt'] ?? 0);
    }

    public function listForRequester(int $userId, int $limit = 20, int $offset = 0, string $status = ''): array
    {
        if (!$this->ready() || $userId < 1 || !$this->hasRequesterColumn()) {
            return [];
        }

        $params = [$userId];
        $where = 'WHERE cr.requester_user_id = ?';
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND cr.status = ?';
            $params[] = $status;
        }
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $rows = $this->db->fetchAll("
            SELECT cr.*,
                   u.username AS master_username,
                   u.name AS master_name,
                   up.display_name AS master_display_name,
                   (SELECT COUNT(*) FROM contact_request_files f WHERE f.request_id = cr.id) AS files_count
              FROM contact_requests cr
              JOIN users u ON u.id = cr.master_id
              LEFT JOIN user_profiles up ON up.user_id = u.id
              {$where}
             ORDER BY cr.created_at DESC, cr.id DESC
             LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return array_map(function (array $row): array {
            $row['files_count'] = (int)($row['files_count'] ?? 0);
            $row['coverup_flag'] = !empty($row['coverup_flag']);
            return $row;
        }, $rows);
    }

    public function findForRequester(int $userId, int $requestId): ?array
    {
        if (!$this->ready() || $userId < 1 || $requestId < 1 || !$this->hasRequesterColumn()) {
            return null;
        }

        $row = $this->db->fetch("
            SELECT cr.*,
                   u.username AS master_username,
                   u.name AS master_name,
                   up.display_name AS master_display_name
              FROM contact_requests cr
              JOIN users u ON u.id = cr.master_id
              LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE cr.id = ? AND cr.requester_user_id = ?
             LIMIT 1
        ", [$requestId, $userId]);
        if (!$row) {
            return null;
        }

        $row['coverup_flag'] = !empty($row['coverup_flag']);
        $row['files'] = $this->filesForRequest($requestId);
        return $row;
    }

    public function createTelegramBindLink(int $masterId, ?array $master = null): ?string
    {
        $settings = $this->settingsForMaster($masterId, $master);
        $botUsername = $this->globalSettings()['master_contact_bot_username'] ?? '';
        if ($botUsername === '') {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $this->db->execute("
            UPDATE master_contact_settings
               SET telegram_bind_token = ?,
                   telegram_bind_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                   updated_at = NOW()
             WHERE master_id = ?
        ", [$token, self::BIND_TTL_SECONDS, $masterId]);

        return 'https://t.me/' . ltrim($botUsername, '@') . '?start=bind_' . $token;
    }

    public function bindTelegram(string $token, array $telegramUser): array
    {
        if (!$this->ready() || trim($token) === '') {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        $row = $this->db->fetch("
            SELECT mcs.master_id, u.username, up.display_name, u.name
              FROM master_contact_settings mcs
              JOIN users u ON u.id = mcs.master_id
              LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE mcs.telegram_bind_token = ?
               AND mcs.telegram_bind_expires_at IS NOT NULL
               AND mcs.telegram_bind_expires_at >= NOW()
             LIMIT 1
        ", [$token]);
        if (!$row) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        $masterId = (int)($row['master_id'] ?? 0);
        if ($masterId < 1) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        $this->db->execute("
            UPDATE master_contact_settings
               SET telegram_user_id = ?,
                   telegram_chat_id = ?,
                   telegram_username = ?,
                   telegram_bound_at = NOW(),
                   telegram_bind_token = NULL,
                   telegram_bind_expires_at = NULL,
                   updated_at = NOW()
             WHERE master_id = ?
        ", [
            (int)($telegramUser['telegram_user_id'] ?? 0),
            (int)($telegramUser['telegram_chat_id'] ?? 0),
            $this->sanitizeText($telegramUser['telegram_username'] ?? '', 191),
            $masterId,
        ]);

        return [
            'ok' => true,
            'master_id' => $masterId,
            'master_name' => (string)($row['display_name'] ?: ($row['name'] ?? 'master')),
            'master_username' => (string)($row['username'] ?? ''),
        ];
    }

    public function unbindTelegram(int $masterId): void
    {
        if (!$this->ready() || $masterId < 1) {
            return;
        }

        $this->db->execute("
            UPDATE master_contact_settings
               SET telegram_notifications_enabled = 0,
                   telegram_chat_id = NULL,
                   telegram_user_id = NULL,
                   telegram_username = NULL,
                   telegram_bound_at = NULL,
                   telegram_bind_token = NULL,
                   telegram_bind_expires_at = NULL,
                   updated_at = NOW()
             WHERE master_id = ?
        ", [$masterId]);
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function findFileForMaster(int $masterId, int $fileId): ?array
    {
        if (!$this->ready() || $masterId < 1 || $fileId < 1) {
            return null;
        }

        $row = $this->db->fetch("
            SELECT f.*, cr.master_id
              FROM contact_request_files f
              JOIN contact_requests cr ON cr.id = f.request_id
             WHERE f.id = ? AND cr.master_id = ?
             LIMIT 1
        ", [$fileId, $masterId]);
        if (!$row) {
            return null;
        }
        $row['absolute_path'] = APP_ROOT . (string)($row['path'] ?? '');
        return $row;
    }

    public function findFileForRequester(int $userId, int $fileId): ?array
    {
        if (
            !$this->ready()
            || $userId < 1
            || $fileId < 1
            || !$this->hasRequesterColumn()
        ) {
            return null;
        }

        $row = $this->db->fetch("
            SELECT f.*, cr.requester_user_id
              FROM contact_request_files f
              JOIN contact_requests cr ON cr.id = f.request_id
             WHERE f.id = ? AND cr.requester_user_id = ?
             LIMIT 1
        ", [$fileId, $userId]);
        if (!$row) {
            return null;
        }
        $row['absolute_path'] = APP_ROOT . (string)($row['path'] ?? '');
        return $row;
    }

    private function filesForRequest(int $requestId): array
    {
        $rows = $this->db->fetchAll("
            SELECT *
              FROM contact_request_files
             WHERE request_id = ?
             ORDER BY sort_order ASC, id ASC
        ", [$requestId]);

        return array_map(static function (array $row): array {
            $row['is_image'] = str_starts_with((string)($row['mime_type'] ?? ''), 'image/');
            return $row;
        }, $rows);
    }

    private function defaultSettings(array $master): array
    {
        return [
            'master_id' => (int)($master['id'] ?? 0),
            'accept_requests' => true,
            'show_contact_cta' => true,
            'notification_email' => $this->normalizeEmail($master['email'] ?? null),
            'telegram_notifications_enabled' => false,
            'telegram_chat_id' => null,
            'telegram_user_id' => null,
            'telegram_username' => null,
            'telegram_bound_at' => null,
            'telegram_bind_token' => null,
            'telegram_bind_expires_at' => null,
            'auto_reply_text' => '',
        ];
    }

    private function validateRequestPayload(array $body): array
    {
        $payload = [
            'client_name' => $this->sanitizeText($body['client_name'] ?? '', 160),
            'client_contact' => $this->sanitizeText($body['client_contact'] ?? '', 255),
            'preferred_contact_method' => $this->sanitizeText($body['preferred_contact_method'] ?? '', 80),
            'city' => $this->sanitizeText($body['city'] ?? '', 120),
            'body_placement' => $this->sanitizeText($body['body_placement'] ?? '', 120),
            'approx_size' => $this->sanitizeText($body['approx_size'] ?? '', 120),
            'request_summary' => $this->sanitizeText($body['request_summary'] ?? '', 255),
            'description' => $this->sanitizeText($body['description'] ?? '', 5000),
            'budget' => $this->sanitizeText($body['budget'] ?? '', 120),
            'target_date' => $this->sanitizeText($body['target_date'] ?? '', 120),
            'coverup_flag' => !empty($body['coverup_flag']) ? 1 : 0,
            'extra_notes' => $this->sanitizeText($body['extra_notes'] ?? '', 3000),
        ];

        if ($payload['client_name'] === '' || $payload['client_contact'] === '' || $payload['request_summary'] === '') {
            throw new \RuntimeException('contact-required');
        }

        return $payload;
    }

    private function normalizeUploads(array $files): array
    {
        if ($files === [] || !isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $uploads = [];
        foreach ($files['name'] as $index => $name) {
            $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = [
                'name' => (string)$name,
                'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
                'size' => (int)($files['size'][$index] ?? 0),
                'error' => $error,
            ];
        }
        return $uploads;
    }

    private function storeImage(int $requestId, array $upload, int $sortOrder): array
    {
        if ((int)($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $size = (int)($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $info = @getimagesize($tmp);
        $mime = strtolower((string)($info['mime'] ?? ''));
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null,
            default => null,
        };
        if (!$source) {
            throw new \RuntimeException('contact-files-invalid');
        }

        $source = $this->applyExifOrientation($source, $tmp, $mime);
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 20 || $srcH < 20) {
            imagedestroy($source);
            throw new \RuntimeException('contact-files-invalid');
        }

        $targetW = $srcW;
        $targetH = $srcH;
        $maxSide = 1600;
        if (max($srcW, $srcH) > $maxSide) {
            if ($srcW >= $srcH) {
                $targetW = $maxSide;
                $targetH = (int)round(($srcH / max(1, $srcW)) * $targetW);
            } else {
                $targetH = $maxSide;
                $targetW = (int)round(($srcW / max(1, $srcH)) * $targetH);
            }
        }

        $dest = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dest, true);
        $white = imagecolorallocate($dest, 255, 255, 255);
        imagefilledrectangle($dest, 0, 0, $targetW, $targetH, $white);
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        $relativeDir = '/storage/private/contact-requests/' . date('Y') . '/' . date('m') . '/' . $requestId;
        $absoluteDir = APP_ROOT . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            imagedestroy($source);
            imagedestroy($dest);
            throw new \RuntimeException('contact-files-invalid');
        }

        $filename = bin2hex(random_bytes(10)) . '.jpg';
        $absolutePath = $absoluteDir . '/' . $filename;
        if (!@imagejpeg($dest, $absolutePath, 84)) {
            imagedestroy($source);
            imagedestroy($dest);
            throw new \RuntimeException('contact-files-invalid');
        }

        imagedestroy($source);
        imagedestroy($dest);

        return [
            'path' => $relativeDir . '/' . $filename,
            'original_name' => $this->sanitizeText($upload['name'] ?? 'reference.jpg', 255),
            'mime_type' => 'image/jpeg',
            'file_size' => (int)@filesize($absolutePath),
            'image_width' => $targetW,
            'image_height' => $targetH,
            'sort_order' => $sortOrder,
        ];
    }

    private function notifyMaster(int $masterId, int $requestId, array $payload, array $settings): void
    {
        $master = $this->users->findFull($masterId) ?? [];
        $email = $this->normalizeEmail($settings['notification_email'] ?? ($master['email'] ?? null));
        if ($email === null) {
            return;
        }

        $site = rtrim((string)($this->settings->get('site_url', '') ?: $this->appUrl()), '/');
        $url = $site . '/profile/master-requests/' . $requestId;
        $subject = 'New master contact request';
        $body = "New contact request\n\n"
            . "Master: " . (string)($master['display_name'] ?? ($master['name'] ?? '')) . "\n"
            . "Client: " . $payload['client_name'] . "\n"
            . "Contact: " . $payload['client_contact'] . "\n"
            . "Summary: " . $payload['request_summary'] . "\n"
            . "Open: " . $url . "\n";

        try {
            (new ProjectMailer($this->settings))->sendText($email, $subject, $body);
        } catch (\Throwable $e) {
            Logger::log('Master contact email failed: ' . $e->getMessage());
        }
    }

    private function normalizeEmail($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return strtolower($value);
    }

    private function sanitizeText($value, int $limit): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }
        return substr($value, 0, $limit);
    }

    private function appUrl(): string
    {
        $config = include APP_ROOT . '/app/config/app.php';
        return (string)($config['url'] ?? 'https://tattootoday.org');
    }

    private function applyExifOrientation($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExists)) {
            return $this->tableExists[$table];
        }
        $row = $this->db->fetch('SHOW TABLES LIKE ?', [$table]);
        return $this->tableExists[$table] = $row !== null;
    }

    private function hasRequesterColumn(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        $row = $this->db->fetch("SHOW COLUMNS FROM contact_requests LIKE 'requester_user_id'");
        return $exists = $row !== null;
    }
}
