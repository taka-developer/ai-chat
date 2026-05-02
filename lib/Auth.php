<?php
require_once __DIR__ . '/DB.php';

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::start();
        $pdo = DB::get();

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['client_id'] = $user['client_id'];
        return true;
    }

    public static function logout(): void
    {
        self::start();
        session_destroy();
    }

    public static function requireAdmin(): void
    {
        self::start();
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            header('Location: /ai-chat/admin/login.php');
            exit;
        }
    }

    public static function requireEditor(): void
    {
        self::start();
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
            header('Location: /ai-chat/admin/login.php');
            exit;
        }
    }

    public static function clientId(): ?int
    {
        return isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : null;
    }

    public static function role(): string
    {
        return $_SESSION['role'] ?? '';
    }
}
