<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use App\Services\SecurityLog;
use App\Services\SettingsService;
use Core\Container;
use Core\Request;
use Core\Response;
use Modules\Users\Services\UserRepository;

class ExternalLinkController
{
    private Container $container;
    private UserRepository $users;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
        $this->settings = $container->get(SettingsService::class);
    }

    public function redirect(Request $request): Response
    {
        $identifier = trim((string)($request->params['id'] ?? ''));
        $platform = strtolower(trim((string)($request->params['platform'] ?? '')));
        if ($identifier === '' || $platform === '') {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'missing_params']);
            return new Response('Not found', 404);
        }

        $user = ctype_digit($identifier)
            ? $this->users->findFull((int)$identifier)
            : $this->users->findFullByUsername($identifier);
        if (!$user || (string)($user['status'] ?? 'active') !== 'active') {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'user_not_found']);
            return new Response('Not found', 404);
        }

        if ((string)($user['profile_visibility'] ?? 'public') === 'private' || empty($user['show_contacts'])) {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'profile_hidden', 'user_id' => (int)$user['id']]);
            return new Response('Not found', 404);
        }

        if (empty($this->currentSettings()['external_links_enabled'])) {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'links_disabled', 'user_id' => (int)$user['id']]);
            return new Response('Not found', 404);
        }

        $links = json_decode((string)($user['external_links_json'] ?? ''), true);
        if (!is_array($links) || empty($links[$platform])) {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'link_missing', 'user_id' => (int)$user['id']]);
            return new Response('Not found', 404);
        }

        $url = $this->normalizeSocialLink($platform, (string)$links[$platform], $user);
        if ($url === null) {
            SecurityLog::log('users.external_link_invalid', ['identifier' => $identifier, 'platform' => $platform, 'reason' => 'link_rejected', 'user_id' => (int)$user['id']]);
            return new Response('Not found', 404);
        }

        return new Response('', 302, ['Location' => $url]);
    }

    private function currentSettings(): array
    {
        $settings = $this->settings->all();

        return [
            'external_links_enabled' => array_key_exists('external_links_enabled', $settings) ? !empty($settings['external_links_enabled']) : true,
            'allowed_social_platforms' => trim((string)($settings['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp')),
        ];
    }

    private function allowedSocialPlatforms(): array
    {
        $raw = $this->currentSettings()['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp';
        $platforms = array_values(array_unique(array_filter(array_map(static function (string $value): string {
            return strtolower(trim($value));
        }, explode(',', (string)$raw)))));
        $supported = ['telegram', 'vk', 'instagram', 'youtube', 'tiktok', 'whatsapp'];

        return array_values(array_intersect($supported, $platforms));
    }

    private function normalizeSocialLink(string $platform, string $value, array $user): ?string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, $this->allowedSocialPlatforms(), true)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $patterns = [
            'telegram' => '#^https://(t\.me|telegram\.me)/[A-Za-z0-9_]{3,}$#i',
            'vk' => '#^https://(www\.)?vk\.com/[A-Za-z0-9_.-]+$#i',
            'instagram' => '#^https://(www\.)?instagram\.com/[A-Za-z0-9_.-]+/?$#i',
            'youtube' => '#^https://(www\.)?(youtube\.com|youtu\.be)/.+$#i',
            'tiktok' => '#^https://(www\.)?tiktok\.com/@[A-Za-z0-9_.-]+/?$#i',
            'whatsapp' => '#^https://wa\.me/[0-9]{6,}$#i',
        ];

        if (!isset($patterns[$platform]) || !preg_match($patterns[$platform], $value)) {
            return null;
        }

        return $value;
    }
}
