<?php
namespace App\Services;

use Core\Request;

class CaptchaService
{
    private array $config;
    private SettingsService $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
        $this->config = [
            'provider' => $settings->get('captcha_provider', 'none'),
            'site_key' => $settings->get('captcha_site_key', ''),
            'secret_key' => $settings->get('captcha_secret_key', ''),
            'enable_admin_login' => (bool)$settings->get('captcha_login_enabled', false),
        ];
    }

    public function config(): array
    {
        return $this->config;
    }

    public function verify(Request $request): bool
    {
        $provider = $this->config['provider'] ?? 'none';
        $secret = $this->config['secret_key'] ?? '';
        if ($provider === 'none' || $secret === '') {
            return true;
        }
        $ip = $request->server['REMOTE_ADDR'] ?? '';

        if ($provider === 'google') {
            $token = $request->body['g-recaptcha-response'] ?? '';
            if ($token === '') {
                return false;
            }
            $data = http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);
            $resp = $this->httpPost('https://www.google.com/recaptcha/api/siteverify', $data);
            if (!$resp) return false;
            $json = json_decode($resp, true);
            return !empty($json['success']);
        }

        if ($provider === 'yandex') {
            $token = $request->body['smart-token'] ?? '';
            if ($token === '') {
                return false;
            }
            $url = 'https://smartcaptcha.yandexcloud.net/validate?secret=' . urlencode($secret) . '&token=' . urlencode($token) . '&ip=' . urlencode($ip);
            $resp = $this->httpGet($url);
            if (!$resp) return false;
            $json = json_decode($resp, true);
            return isset($json['status']) && $json['status'] === 'ok';
        }
        return true;
    }

    private function httpPost(string $url, string $data): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res ?: null;
        }
        return @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $data,
                'timeout' => 5,
            ]
        ])) ?: null;
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res ?: null;
        }
        return @file_get_contents($url) ?: null;
    }
}
