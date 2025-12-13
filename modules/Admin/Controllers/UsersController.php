<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Csrf;
use Core\Request;
use Core\Response;

class UsersController
{
    private Container $container;
    private Database $db;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        $users = $this->db->fetchAll("SELECT id, username, created_at FROM admin_users ORDER BY id ASC");
        $html = $this->container->get('renderer')->render('admin/users', [
            'title' => 'Users',
            'users' => $users,
            'csrf' => Csrf::token('admin_users'),
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('admin_users', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $username = trim($request->body['username'] ?? '');
        $password = (string)($request->body['password'] ?? '');
        if ($username && $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $this->db->execute("INSERT INTO admin_users (username, password) VALUES (?, ?)", [$username, $hash]);
            } catch (\Throwable $e) {
                return new Response('User already exists', 409);
            }
        }
        header('Location: /admin/users');
        exit;
    }
}
