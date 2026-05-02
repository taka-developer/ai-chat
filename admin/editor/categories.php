<?php
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/DB.php';
require_once __DIR__ . '/../_layout.php';
Auth::requireEditor();

$pdo      = DB::get();
$clientId = Auth::clientId();
$flash    = '';

// カテゴリ管理用テーブルがなければFAQのcategoryカラムから集計
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            try {
                $pdo->prepare('INSERT INTO faq_categories (client_id, name) VALUES (?, ?)')
                    ->execute([$clientId, $name]);
                $flash = 'success:カテゴリを追加しました。';
            } catch (PDOException $e) {
                $flash = 'error:追加に失敗しました。';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM faq_categories WHERE id = ? AND client_id = ?')->execute([$id, $clientId]);
        $flash = 'success:カテゴリを削除しました。';
    }
}

// faq_categoriesテーブルがない場合はFAQのcategoryから一覧表示
try {
    $stmt = $pdo->prepare('SELECT * FROM faq_categories WHERE client_id = ? ORDER BY name');
    $stmt->execute([$clientId]);
    $categories = $stmt->fetchAll();
    $hasTable   = true;
} catch (PDOException) {
    $categories = [];
    $hasTable   = false;
}

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if (!$hasTable): ?>
  <div class="flash flash-error">faq_categories テーブルが未作成です。schema.sqlを確認してください。</div>
<?php else: ?>

<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">カテゴリ追加</h2>
  <form method="post" style="display:flex;gap:12px">
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" required placeholder="カテゴリ名" style="width:240px">
    <button class="btn btn-primary" type="submit">追加</button>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>カテゴリ名</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $cat): ?>
      <tr>
        <td><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <button class="btn btn-danger btn-sm">削除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php
adminLayout('カテゴリ管理', ob_get_clean(), 'editor');
