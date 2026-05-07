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

    if ($action === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $systemPrompt = trim($_POST['system_prompt'] ?? '');
        $contactUrl   = trim($_POST['contact_url'] ?? '');
        if ($id && $name) {
            $pdo->prepare('UPDATE clients SET name=?, system_prompt=?, contact_url=? WHERE id=?')
                ->execute([$name, $systemPrompt, $contactUrl, $id]);
            $flash = 'success:クライアント情報を更新しました。';
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

<div id="add-panel" style="display:none" class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">新規クライアント追加</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <div class="form-group"><label>会社名 *</label><input type="text" name="name" required></div>
    <div class="form-group"><label>システムプロンプト</label><textarea name="system_prompt" rows="3"></textarea></div>
    <div class="form-group"><label>お問い合わせURL</label><input type="url" name="contact_url"></div>
    <button class="btn btn-primary" type="submit">追加</button>
    <button type="button" class="btn btn-sm" onclick="togglePanel()" style="background:#94a3b8;color:#fff;margin-left:8px">キャンセル</button>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="font-size:16px;margin:0;">クライアント一覧</h2>
    <button class="btn btn-primary btn-sm" onclick="togglePanel()">＋ 新規追加</button>
  </div>
  <table>
    <thead><tr><th>会社名</th><th>FAQ数</th><th>widget_key</th><th>埋め込みタグ</th><th>登録日</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <?php
        $key     = htmlspecialchars($c['widget_key'], ENT_QUOTES, 'UTF-8');
        $embedTag = htmlspecialchars('<script src="https://chat.stekwired.jp/widget.js" data-key="' . $c['widget_key'] . '"></script>', ENT_QUOTES, 'UTF-8');
      ?>
      <tr>
        <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int)$c['faq_count'] ?></td>
        <td style="white-space:nowrap">
          <code style="font-size:11px"><?= substr($key, 0, 8) ?>…</code>
          <button class="btn btn-sm" style="font-size:11px;padding:2px 8px;margin-left:4px" onclick="copyText('<?= $key ?>', this)">コピー</button>
        </td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" style="font-size:11px;padding:2px 8px" onclick="copyText('<?= $embedTag ?>', this)">タグをコピー</button>
        </td>
        <td><?= htmlspecialchars(substr($c['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-sm" style="background:#0ea5e9;color:#fff" onclick="toggleEdit(<?= (int)$c['id'] ?>)">編集</button>
          <form method="post" onsubmit="return confirm('削除してよろしいですか？');" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-danger btn-sm">削除</button>
          </form>
        </td>
      </tr>
      <tr id="edit-row-<?= (int)$c['id'] ?>" style="display:none">
        <td colspan="6" style="padding:0">
          <div style="background:#f8fafc;border-top:2px solid #2563eb;padding:16px">
            <form method="post">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <div class="form-group"><label>会社名 *</label><input type="text" name="name" value="<?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
              <div class="form-group"><label>システムプロンプト</label><textarea name="system_prompt" rows="3"><?= htmlspecialchars($c['system_prompt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
              <div class="form-group"><label>お問い合わせURL</label><input type="url" name="contact_url" value="<?= htmlspecialchars($c['contact_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
              <p style="font-size:12px;color:#94a3b8;margin-bottom:12px">widget_key: <code><?= htmlspecialchars($c['widget_key'], ENT_QUOTES, 'UTF-8') ?></code>（変更不可）</p>
              <button class="btn btn-primary btn-sm" type="submit">更新</button>
              <button type="button" class="btn btn-sm" onclick="toggleEdit(<?= (int)$c['id'] ?>)" style="background:#94a3b8;color:#fff;margin-left:8px">キャンセル</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
function togglePanel() {
  const p = document.getElementById('add-panel');
  p.style.display = p.style.display === 'none' ? '' : 'none';
}
function toggleEdit(id) {
  const row = document.getElementById('edit-row-' + id);
  row.style.display = row.style.display === 'none' ? '' : 'none';
}
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ コピー済み';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}
</script>
<?php
adminLayout('クライアント管理', ob_get_clean(), 'admin');
