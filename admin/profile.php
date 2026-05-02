<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/_layout.php';
Auth::requireEditor();

$pdo   = DB::get();
$flash = '';
$uid   = Auth::userId();
$role  = Auth::role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $flash = 'error:現在のパスワードが正しくありません。';
    } elseif ($new === '') {
        $flash = 'error:新しいパスワードを入力してください。';
    } elseif ($new !== $confirm) {
        $flash = 'error:新しいパスワードと確認用パスワードが一致しません。';
    } else {
        $pdo->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        $flash = 'success:パスワードを変更しました。';
    }
}

$stmt = $pdo->prepare('SELECT email FROM admin_users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
  <p style="font-size:13px;color:#64748b;margin-bottom:20px;">
    ログイン中: <strong><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
    （<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>）
  </p>
  <form method="post">
    <div class="form-group"><label>現在のパスワード *</label><input type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="form-group"><label>新しいパスワード *</label><input type="password" name="new_password" required autocomplete="new-password"></div>
    <div class="form-group"><label>新しいパスワード（確認） *</label><input type="password" name="confirm_password" required autocomplete="new-password"></div>
    <button class="btn btn-primary" type="submit">変更する</button>
  </form>
</div>
<?php
adminLayout('パスワード変更', ob_get_clean(), $role);
