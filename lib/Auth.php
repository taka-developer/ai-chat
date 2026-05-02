<?php
require_once __DIR__ . '/DB.php';

class Auth
{
    private static string $loginError = '';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
                session_destroy();
                session_start();
                header('Location: /ai-chat/admin/login.php?timeout=1');
                exit;
            }
            $_SESSION['last_activity'] = time();
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::start();
        $pdo = DB::get();

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            self::$loginError = '';
            return false;
        }

        // アカウントロック確認
        if ($user['login_locked_until'] && strtotime($user['login_locked_until']) > time()) {
            self::$loginError = 'locked';
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $failCount   = (int)$user['login_failed_count'] + 1;
            $lockedUntil = null;
            if ($failCount >= 5) {
                $lockedUntil        = date('Y-m-d H:i:s', time() + 1800);
                self::$loginError   = 'locked';
            } else {
                self::$loginError = '';
            }
            $pdo->prepare('UPDATE admin_users SET login_failed_count=?, login_locked_until=? WHERE id=?')
                ->execute([$failCount, $lockedUntil, $user['id']]);
            return false;
        }

        // 成功 — 失敗カウントをリセット
        $pdo->prepare('UPDATE admin_users SET login_failed_count=0, login_locked_until=NULL WHERE id=?')
            ->execute([$user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['client_id']     = $user['client_id'];
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function loginError(): string
    {
        return self::$loginError;
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

    public static function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function userEmail(): string
    {
        return $_SESSION['user_email'] ?? '';
    }
}
