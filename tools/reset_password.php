<?php
require_once __DIR__ . '/../lib/DB.php';

$email    = 'admin@stekwired.jp';
$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT);

$pdo  = DB::get();
$stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE email = ?');
$stmt->execute([$hash, $email]);

echo "パスワードをリセットしました。\n";
echo "email: {$email}\n";
echo "password: {$password}\n";
echo "hash: {$hash}\n";
