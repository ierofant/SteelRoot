<?php
namespace Modules\Users\Controllers;

use App\Services\CaptchaService;
use App\Services\MailTemplateService;
use App\Services\ProjectMailer;
use App\Services\SettingsService;
use Core\Container;
use Core\Csrf;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\UserRepository;

class AuthController
{
    private Container $container;
    private Auth $auth;
    private UserRepository $users;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
    }

    public function form(Request $request): Response
    {
        if ($this->auth->user()) {
            return new Response('', 302, ['Location' => '/profile']);
        }

        $html = $this->container->get('renderer')->render('users/login', [
            'title' => 'Login',
            'csrf' => Csrf::token('login'),
            'error' => null,
            'success' => null,
            'email' => '',
            'remember' => false,
            'captcha' => $this->container->get(CaptchaService::class)->config(),
        ]);

        return new Response($html);
    }

    public function login(Request $request): Response
    {
        $ip = $request->server['REMOTE_ADDR'] ?? 'ip';
        $ua = $request->headers['user-agent'] ?? ($request->server['HTTP_USER_AGENT'] ?? '');
        $limiter = new RateLimiter('login_' . $ip, 5, 300, true);
        if ($limiter->tooManyAttempts()) {
            return $this->renderLoginError('Too many attempts, try later', 429);
        }
        $limiter->hit();

        if (($request->body['website'] ?? '') !== '') {
            return new Response('', 302, ['Location' => '/']);
        }
        if (!Csrf::check('login', $request->body['_token'] ?? null)) {
            return $this->renderLoginError('Invalid CSRF token');
        }
        /** @var CaptchaService $captcha */
        $captcha = $this->container->get(CaptchaService::class);
        if (!$captcha->verify($request, 'login')) {
            return $this->renderLoginError('Captcha failed');
        }

        $email = strtolower(trim((string)($request->body['email'] ?? '')));
        $password = (string)($request->body['password'] ?? '');
        $remember = !empty($request->body['remember']);
        if ($email === '' || $password === '') {
            return $this->renderLoginError('Enter email and password');
        }

        [$ok, $message] = $this->auth->attempt($email, $password, (string)$ip, (string)$ua, $remember);
        if (!$ok) {
            return $this->renderLoginError($message ?: 'Login failed', 400, $email, $remember);
        }

        return new Response('', 302, ['Location' => '/profile']);
    }

    public function logout(Request $request): Response
    {
        $token = $request->body['_token'] ?? null;
        if (!Csrf::check('logout', $token)) {
            Csrf::token('logout');
        }

        $this->auth->logout();

        return new Response('', 302, ['Location' => '/login']);
    }

    public function forgotForm(Request $request): Response
    {
        $this->ensureSessionStarted();
        [$captchaQ, $captchaAnswer] = $this->issueCaptchaChallenge();
        $_SESSION['users_password_reset_captcha'] = $captchaAnswer;

        $html = $this->container->get('renderer')->render('users/forgot', [
            'csrf' => Csrf::token('forgot_password'),
            'captchaQ' => $captchaQ,
            'error' => null,
            'success' => null,
        ]);

        return new Response($html);
    }

    public function forgotSubmit(Request $request): Response
    {
        if (!Csrf::check('forgot_password', $request->body['_token'] ?? null)) {
            return $this->renderForgotError('Invalid CSRF token');
        }

        if (!$this->captchaMatches((string)($request->body['captcha'] ?? ''))) {
            return $this->renderForgotError('Wrong answer to the verification question');
        }

        $email = strtolower(trim((string)($request->body['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForgotError('Enter a valid email');
        }

        $user = $this->users->findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(24));
            $this->users->createResetToken($email, $token, 3600);
            $name = (string)($user['name'] ?? $user['email'] ?? '');
            $link = $this->baseUrl($request) . '/reset-password?token=' . urlencode($token);
            $this->sendResetMail($email, $name, $link);
        }

        unset($_SESSION['users_password_reset_captcha']);

        $html = $this->container->get('renderer')->render('users/forgot', [
            'csrf' => Csrf::token('forgot_password'),
            'captchaQ' => '',
            'error' => null,
            'success' => 'If the account exists, we sent a password reset link to the email address provided.',
        ]);

        return new Response($html);
    }

    public function resetForm(Request $request): Response
    {
        $token = trim((string)($request->query['token'] ?? ''));
        if ($token === '' || !$this->users->findValidResetToken($token)) {
            $html = $this->container->get('renderer')->render('users/reset', [
                'csrf' => Csrf::token('reset_password'),
                'token' => '',
                'captchaQ' => '',
                'error' => 'Invalid or expired reset link',
                'success' => null,
            ]);

            return new Response($html, 404);
        }

        $this->ensureSessionStarted();
        [$captchaQ, $captchaAnswer] = $this->issueCaptchaChallenge();
        $_SESSION['users_password_reset_captcha'] = $captchaAnswer;

        $html = $this->container->get('renderer')->render('users/reset', [
            'csrf' => Csrf::token('reset_password'),
            'token' => $token,
            'captchaQ' => $captchaQ,
            'error' => null,
            'success' => null,
        ]);

        return new Response($html);
    }

    public function resetSubmit(Request $request): Response
    {
        if (!Csrf::check('reset_password', $request->body['_token'] ?? null)) {
            return $this->renderResetError((string)($request->body['token'] ?? ''), 'Invalid CSRF token');
        }

        if (!$this->captchaMatches((string)($request->body['captcha'] ?? ''))) {
            return $this->renderResetError((string)($request->body['token'] ?? ''), 'Wrong answer to the verification question');
        }

        $token = trim((string)($request->body['token'] ?? ''));
        $reset = $token !== '' ? $this->users->findValidResetToken($token) : null;
        if (!$reset) {
            return $this->renderResetError('', 'Invalid or expired reset link');
        }

        $password = (string)($request->body['password'] ?? '');
        $passwordConfirm = (string)($request->body['password_confirm'] ?? '');
        if (strlen($password) < 8) {
            return $this->renderResetError($token, 'Password must be at least 8 characters');
        }
        if ($password !== $passwordConfirm) {
            return $this->renderResetError($token, 'Passwords do not match');
        }

        $user = $this->users->findByEmail((string)($reset['email'] ?? ''));
        if (!$user) {
            $this->users->deleteResetToken($token);
            return $this->renderResetError('', 'User not found');
        }

        $this->users->setPassword((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->users->deleteResetToken($token);
        unset($_SESSION['users_password_reset_captcha']);

        $html = $this->container->get('renderer')->render('users/reset', [
            'csrf' => Csrf::token('reset_password'),
            'token' => '',
            'captchaQ' => '',
            'error' => null,
            'success' => 'Password updated. You can now sign in.',
        ]);

        return new Response($html);
    }

    private function renderLoginError(string $message, int $status = 400, string $email = '', bool $remember = false): Response
    {
        $html = $this->container->get('renderer')->render('users/login', [
            'title' => 'Login',
            'csrf' => Csrf::token('login'),
            'error' => $message,
            'success' => null,
            'email' => $email,
            'remember' => $remember,
            'captcha' => $this->container->get(CaptchaService::class)->config(),
        ]);

        return new Response($html, $status);
    }

    private function renderForgotError(string $message): Response
    {
        $this->ensureSessionStarted();
        [$captchaQ, $captchaAnswer] = $this->issueCaptchaChallenge();
        $_SESSION['users_password_reset_captcha'] = $captchaAnswer;

        $html = $this->container->get('renderer')->render('users/forgot', [
            'csrf' => Csrf::token('forgot_password'),
            'captchaQ' => $captchaQ,
            'error' => $message,
            'success' => null,
        ]);

        return new Response($html, 400);
    }

    private function renderResetError(string $token, string $message): Response
    {
        [$captchaQ, $captchaAnswer] = $this->issueCaptchaChallenge();
        $this->ensureSessionStarted();
        $_SESSION['users_password_reset_captcha'] = $captchaAnswer;

        $html = $this->container->get('renderer')->render('users/reset', [
            'csrf' => Csrf::token('reset_password'),
            'token' => $token,
            'captchaQ' => $token !== '' ? $captchaQ : '',
            'error' => $message,
            'success' => null,
        ]);

        return new Response($html, 400);
    }

    private function issueCaptchaChallenge(): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);

        return [$left . ' + ' . $right . ' = ?', (string)($left + $right)];
    }

    private function captchaMatches(string $answer): bool
    {
        $expected = (string)($_SESSION['users_password_reset_captcha'] ?? '');
        unset($_SESSION['users_password_reset_captcha']);

        return $expected !== '' && trim($answer) === $expected;
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    private function sendResetMail(string $to, string $name, string $link): void
    {
        $settings = $this->container->get(SettingsService::class);
        $siteName = trim((string)$settings->get('site_name', 'TattooRoot'));
        $templates = new MailTemplateService($settings);
        $message = $templates->render(
            'mail_reset_subject',
            'mail_reset_body',
            ['site_name' => $siteName, 'name' => $name, 'email' => $to, 'link' => $link],
            'Password reset on {site_name}',
            ''
        );

        $html = $this->renderEmailTemplate('password_reset', [
            'site_name' => $siteName,
            'name' => $name,
            'link' => $link,
            'subject' => $message['subject'],
        ]);

        (new ProjectMailer($settings))->sendHtml($to, $message['subject'], $html);
    }

    private function renderEmailTemplate(string $template, array $vars): string
    {
        extract($vars);

        ob_start();
        include __DIR__ . '/../views/emails/' . $template . '.php';

        return (string) ob_get_clean();
    }

    private function baseUrl(Request $request): string
    {
        $config = $this->container->get('config');
        $base = rtrim((string)($config['url'] ?? ''), '/');
        if ($base !== '') {
            return $base;
        }

        $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
