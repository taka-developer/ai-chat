<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '/ai-chat/admin/index.php' : '/ai-chat/admin/editor/index.php'));
    exit;
}

$error = '';
if (!empty($_GET['timeout'])) {
    $error = 'セッションの有効期限が切れました。再度ログインしてください。';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (Auth::login($email, $password)) {
        header('Location: ' . ($_SESSION['role'] === 'admin' ? '/ai-chat/admin/index.php' : '/ai-chat/admin/editor/index.php'));
        exit;
    }
    $error = Auth::loginError() === 'locked'
        ? 'ログインに5回失敗しました。30分後に再試行してください。'
        : 'メールアドレスまたはパスワードが正しくありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン — 管理画面</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { background: #fff; border-radius: 12px; padding: 40px; width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    h1 { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 28px; text-align: center; }
    label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
    input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
    input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    button { width: 100%; background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 12px; font-size: 15px; font-weight: 600; cursor: pointer; }
    button:hover { background: #1d4ed8; }
    .error { background: #fee2e2; color: #dc2626; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>管理画面ログイン</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <label for="email">メールアドレス</label>
      <input type="email" id="email" name="email" required autocomplete="email">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit">ログイン</button>
    </form>
  </div>
</body>
</html>
