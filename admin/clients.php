<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo   = DB::get();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name          = trim($_POST['name'] ?? '');
        $systemPrompt  = trim($_POST['system_prompt'] ?? '');
        $contactUrl    = trim($_POST['contact_url'] ?? '');
        $widgetKey     = bin2hex(random_bytes(32));
        if ($name !== '') {
            $pdo->prepare('INSERT INTO clients (name, system_prompt, contact_url, widget_key) VALUES (?, ?, ?, ?)')
                ->execute([$name, $systemPrompt, $contactUrl, $widgetKey]);
            $flash = 'success:クライアントを追加しました。';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
            $flash = 'success:クライアントを削除しました。';
        }
    }
}

$clients = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM faqs WHERE client_id = c.id AND is_active = 1) AS faq_count
       FROM clients c ORDER BY c.created_at DESC'
)->fetchAll();

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">新規クライアント追加</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <div class="form-group"><label>会社名 *</label><input type="text" name="name" required></div>
    <div class="form-group"><label>システムプロンプト</label><textarea name="system_prompt" rows="3"></textarea></div>
    <div class="form-group"><label>お問い合わせURL</label><input type="url" name="contact_url"></div>
    <button class="btn btn-primary" type="submit">追加</button>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>会社名</th><th>FAQ数</th><th>widget_key</th><th>埋め込みタグ</th><th>登録日</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int)$c['faq_count'] ?></td>
        <td><code><?= htmlspecialchars($c['widget_key'], ENT_QUOTES, 'UTF-8') ?></code></td>
        <td><code>&lt;script src="https://chat.stekwired.jp/widget.js" data-key="<?= htmlspecialchars($c['widget_key'], ENT_QUOTES, 'UTF-8') ?>"&gt;&lt;/script&gt;</code></td>
        <td><?= htmlspecialchars(substr($c['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" onsubmit="return confirm('削除してよろしいですか？');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-danger btn-sm">削除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
adminLayout('クライアント管理', ob_get_clean(), 'admin');
