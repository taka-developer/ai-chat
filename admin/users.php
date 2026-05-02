<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo        = DB::get();
$flash      = '';
$currentUid = Auth::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'editor']) ? $_POST['role'] : 'editor';
        $clientId = ($role === 'editor') ? ((int)($_POST['client_id'] ?? 0) ?: null) : null;

        if ($email === '' || $password === '') {
            $flash = 'error:メールアドレスとパスワードは必須です。';
        } elseif ($role === 'editor' && $clientId === null) {
            $flash = 'error:編集者にはクライアントの選択が必要です。';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO admin_users (client_id, email, password_hash, role) VALUES (?, ?, ?, ?)'
                )->execute([$clientId, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                $flash = 'success:ユーザーを追加しました。';
            } catch (PDOException) {
                $flash = 'error:そのメールアドレスは既に使用されています。';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $currentUid) {
            $flash = 'error:自分自身は削除できません。';
        } elseif ($id > 0) {
            $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            $flash = 'success:ユーザーを削除しました。';
        }
    }
}

$users   = $pdo->query(
    'SELECT u.*, c.name AS client_name FROM admin_users u LEFT JOIN clients c ON c.id = u.client_id ORDER BY u.role, u.created_at'
)->fetchAll();
$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">新規ユーザー追加</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <div class="form-group"><label>メールアドレス *</label><input type="email" name="email" required autocomplete="off"></div>
    <div class="form-group"><label>パスワード *</label><input type="password" name="password" required autocomplete="new-password"></div>
    <div class="form-group">
      <label>ロール *</label>
      <select name="role" id="role-select" onchange="toggleClientField()">
        <option value="editor">editor（編集者）</option>
        <option value="admin">admin（管理者）</option>
      </select>
    </div>
    <div class="form-group" id="client-field">
      <label>所属クライアント *</label>
      <select name="client_id">
        <option value="">選択してください</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">追加</button>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>メールアドレス</th><th>ロール</th><th>所属クライアント</th><th>登録日</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-green' : 'badge-gray' ?>"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars($u['client_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(substr($u['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php if ((int)$u['id'] === $currentUid): ?>
            <span style="color:#94a3b8;font-size:12px;">（自分）</span>
          <?php else: ?>
            <form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-danger btn-sm">削除</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
function toggleClientField() {
  document.getElementById('client-field').style.display =
    document.getElementById('role-select').value === 'editor' ? '' : 'none';
}
toggleClientField();
</script>
<?php
adminLayout('ユーザー管理', ob_get_clean(), 'admin');
