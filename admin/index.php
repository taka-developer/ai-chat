<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo = DB::get();
$clients = $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$faqs    = $pdo->query('SELECT COUNT(*) FROM faqs WHERE is_active = 1')->fetchColumn();
$logs    = $pdo->query('SELECT COUNT(*) FROM conversation_logs')->fetchColumn();
$today   = $pdo->query("SELECT COUNT(*) FROM conversation_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

ob_start();
?>
<div class="stats">
  <div class="stat"><div class="stat-value"><?= (int)$clients ?></div><div class="stat-label">クライアント数</div></div>
  <div class="stat"><div class="stat-value"><?= (int)$faqs ?></div><div class="stat-label">有効FAQ数</div></div>
  <div class="stat"><div class="stat-value"><?= (int)$logs ?></div><div class="stat-label">総会話数</div></div>
  <div class="stat"><div class="stat-value"><?= (int)$today ?></div><div class="stat-label">本日の会話</div></div>
</div>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">最近の会話ログ</h2>
  <table>
    <thead><tr><th>日時</th><th>クライアント</th><th>ユーザー入力</th></tr></thead>
    <tbody>
    <?php
    $rows = $pdo->query(
        'SELECT l.created_at, c.name, l.user_message
           FROM conversation_logs l
           JOIN clients c ON c.id = l.client_id
          ORDER BY l.created_at DESC LIMIT 10'
    )->fetchAll();
    foreach ($rows as $r):
    ?>
      <tr>
        <td><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($r['user_message'], 0, 60), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($r['user_message']) > 60 ? '…' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
adminLayout('ダッシュボード', ob_get_clean(), 'admin');
