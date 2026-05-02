<?php
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/DB.php';
require_once __DIR__ . '/../_layout.php';
Auth::requireEditor();

$pdo      = DB::get();
$clientId = Auth::clientId();

$faqs  = $pdo->prepare('SELECT COUNT(*) FROM faqs WHERE client_id = ? AND is_active = 1');
$faqs->execute([$clientId]);
$faqCount = $faqs->fetchColumn();

$logs  = $pdo->prepare('SELECT COUNT(*) FROM conversation_logs WHERE client_id = ?');
$logs->execute([$clientId]);
$logCount = $logs->fetchColumn();

$today = $pdo->prepare("SELECT COUNT(*) FROM conversation_logs WHERE client_id = ? AND DATE(created_at) = CURDATE()");
$today->execute([$clientId]);
$todayCount = $today->fetchColumn();

ob_start();
?>
<div class="stats">
  <div class="stat"><div class="stat-value"><?= (int)$faqCount ?></div><div class="stat-label">有効FAQ数</div></div>
  <div class="stat"><div class="stat-value"><?= (int)$logCount ?></div><div class="stat-label">総会話数</div></div>
  <div class="stat"><div class="stat-value"><?= (int)$todayCount ?></div><div class="stat-label">本日の会話</div></div>
</div>

<div class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">最近の会話</h2>
  <table>
    <thead><tr><th>日時</th><th>ユーザー入力</th><th>ボット回答（抜粋）</th></tr></thead>
    <tbody>
    <?php
    $stmt = $pdo->prepare(
        'SELECT created_at, user_message, bot_response FROM conversation_logs WHERE client_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $stmt->execute([$clientId]);
    foreach ($stmt->fetchAll() as $r):
    ?>
      <tr>
        <td style="white-space:nowrap"><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($r['user_message'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($r['bot_response'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($r['bot_response'] ?? '') > 80 ? '…' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
adminLayout('ダッシュボード', ob_get_clean(), 'editor');
