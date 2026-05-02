<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo      = DB::get();
$clients  = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 30;
$offset   = ($page - 1) * $perPage;

$where  = $clientId ? 'WHERE l.client_id = ' . $clientId : '';
$total  = $pdo->query("SELECT COUNT(*) FROM conversation_logs l {$where}")->fetchColumn();
$pages  = (int)ceil($total / $perPage);

$logs = $pdo->query(
    "SELECT l.*, c.name AS client_name
       FROM conversation_logs l JOIN clients c ON c.id = l.client_id
     {$where}
     ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
)->fetchAll();

ob_start();
?>
<form method="get" style="margin-bottom:16px;display:flex;gap:12px;align-items:center">
  <select name="client_id" onchange="this.form.submit()">
    <option value="0">全クライアント</option>
    <?php foreach ($clients as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span style="font-size:13px;color:#64748b">全 <?= (int)$total ?> 件</span>
</form>

<div class="card">
  <table>
    <thead><tr><th>日時</th><th>クライアント</th><th>ユーザー入力</th><th>ボット回答（抜粋）</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td style="white-space:nowrap"><?= htmlspecialchars($l['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($l['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($l['user_message'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(mb_substr($l['bot_response'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($l['bot_response'] ?? '') > 80 ? '…' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?client_id=<?= $clientId ?>&page=<?= $i ?>"
       style="padding:6px 12px;border-radius:6px;text-decoration:none;font-size:13px;
              background:<?= $i === $page ? '#2563eb' : '#e2e8f0' ?>;
              color:<?= $i === $page ? '#fff' : '#1e293b' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php
adminLayout('会話ログ', ob_get_clean(), 'admin');
