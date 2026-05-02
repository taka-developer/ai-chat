<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Claude.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo   = DB::get();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);

        if ($clientId && $question && $answer) {
            if ($keywords === '') {
                try {
                    $kw = (new Claude())->generateFaqKeywords($question, $answer);
                    $keywords = implode(',', $kw);
                } catch (Throwable) {}
            }
            $pdo->prepare(
                'INSERT INTO faqs (client_id, category, question, answer, keywords, priority) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$clientId, $category, $question, $answer, $keywords, $priority]);
            $flash = 'success:FAQを追加しました。';
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE faqs SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM faqs WHERE id = ?')->execute([$id]);
        $flash = 'success:FAQを削除しました。';
    }
}

$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
$faqs    = $pdo->query(
    'SELECT f.*, c.name AS client_name FROM faqs f JOIN clients c ON c.id = f.client_id ORDER BY f.client_id, f.priority DESC, f.updated_at DESC'
)->fetchAll();

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">FAQ追加</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <div class="form-group">
      <label>クライアント *</label>
      <select name="client_id" required>
        <option value="">選択してください</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>カテゴリ</label><input type="text" name="category"></div>
    <div class="form-group"><label>質問 *</label><textarea name="question" rows="2" required></textarea></div>
    <div class="form-group"><label>回答 *</label><textarea name="answer" rows="4" required></textarea></div>
    <div class="form-group"><label>キーワード（空欄なら自動生成）</label><input type="text" name="keywords" placeholder="例: 営業時間,定休日,受付"></div>
    <div class="form-group"><label>優先度</label><input type="number" name="priority" value="0" min="0" style="width:100px"></div>
    <button class="btn btn-primary" type="submit">追加</button>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>クライアント</th><th>カテゴリ</th><th>質問</th><th>状態</th><th>優先度</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($faqs as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($f['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($f['question'], 0, 50), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($f['question']) > 50 ? '…' : '' ?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-sm <?= $f['is_active'] ? 'btn-primary' : '' ?>" style="<?= $f['is_active'] ? '' : 'background:#94a3b8;color:#fff' ?>">
              <?= $f['is_active'] ? '公開中' : '非公開' ?>
            </button>
          </form>
        </td>
        <td><?= (int)$f['priority'] ?></td>
        <td>
          <form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-danger btn-sm">削除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
adminLayout('FAQ管理（管理者）', ob_get_clean(), 'admin');
