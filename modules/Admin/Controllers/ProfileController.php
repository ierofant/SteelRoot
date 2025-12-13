<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;

class ProfileController
{
    private Database $db;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
    }

    public function show(Request $request): Response
    {
        $username = $_SESSION['admin_user'] ?? '';
        $err = $request->query['err'] ?? null;
        $msg = $request->query['msg'] ?? null;
        $errorText = null;
        if ($err === 'wrongpass') {
            $errorText = 'Неверный текущий пароль';
        } elseif ($err === 'exists') {
            $errorText = 'Такой логин уже существует';
        }
        if ($msg === 'updated') {
            $msg = 'Профиль обновлён';
        }
        $html = $this->container->get('renderer')->render('admin/profile', [
            'title' => 'Profile',
            'username' => $username,
            'csrf' => Csrf::token('admin_profile'),
            'message' => $msg,
            'error' => $errorText,
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('admin_profile', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $current = $_SESSION['admin_user'] ?? '';
        $user = $this->db->fetch("SELECT * FROM admin_users WHERE username = ?", [$current]);
        if (!$user) {
            return new Response('User not found', 404);
        }
        $newUsername = trim($request->body['username'] ?? $current);
        $oldPass = $request->body['old_password'] ?? '';
        $newPass = $request->body['new_password'] ?? '';
        if (!password_verify($oldPass, $user['password'])) {
            return new Response('', 302, ['Location' => $this->prefix() . '/profile?err=wrongpass']);
        }
        // If username changes and exists, block
        if ($newUsername !== $current) {
            $exists = $this->db->fetch("SELECT id FROM admin_users WHERE username = ?", [$newUsername]);
            if ($exists) {
                return new Response('', 302, ['Location' => $this->prefix() . '/profile?err=exists']);
            }
        }
        $params = [$newUsername, $user['password'], $user['id']];
        $sql = "UPDATE admin_users SET username = ?, password = ? WHERE id = ?";
        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $params[1] = $hash;
        }
        $this->db->execute($sql, $params);
        $_SESSION['admin_user'] = $newUsername;
        return new Response('', 302, ['Location' => $this->prefix() . '/profile?msg=updated']);
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }
}
