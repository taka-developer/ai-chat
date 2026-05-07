<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Claude.php';
require_once __DIR__ . '/../lib/CsvImporter.php';
require_once __DIR__ . '/_layout.php';
Auth::requireAdmin();

$pdo       = DB::get();
$flash     = '';
$csvResult = null;

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

    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);
        if ($id && $question && $answer) {
            if ($keywords === '') {
                try {
                    $kw = (new Claude())->generateFaqKeywords($question, $answer);
                    $keywords = implode(',', $kw);
                } catch (Throwable) {}
            }
            $pdo->prepare(
                'UPDATE faqs SET category=?, question=?, answer=?, keywords=?, priority=?, updated_at=NOW() WHERE id=?'
            )->execute([$category, $question, $answer, $keywords, $priority, $id]);
            $flash = 'success:FAQを更新しました。';
        }
    }

    if ($action === 'csv_import') {
        $clientId    = (int)($_POST['client_id'] ?? 0);
        $autoKeywords = !empty($_POST['auto_keywords']);
        if (!$clientId) {
            $flash = 'error:クライアントを選択してください。';
        } elseif (empty($_FILES['csv']['name'])) {
            $flash = 'error:CSVファイルを選択してください。';
        } else {
            $csvResult = (new CsvImporter())->import($_FILES['csv'], $clientId, $autoKeywords);
            if ($csvResult['imported'] > 0) {
                $flash = "success:{$csvResult['imported']} 件インポートしました。";
            } elseif (empty($csvResult['errors'])) {
                $flash = 'error:インポート対象がありませんでした。';
            }
        }
    }
}

$clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
$faqs    = $pdo->query(
    'SELECT f.*, c.name AS client_name FROM faqs f JOIN clients c ON c.id = f.client_id ORDER BY f.client_id, f.priority DESC, f.updated_at DESC'
)->fetchAll();

$allCategories = [];
foreach ($faqs as $f) {
    if (!empty($f['category']) && !in_array($f['category'], $allCategories)) {
        $allCategories[] = $f['category'];
    }
}
sort($allCategories);

[$flashType, $flashMsg] = $flash ? explode(':', $flash, 2) : ['', ''];

ob_start();
?>
<?php if ($flashMsg): ?>
  <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div id="csv-panel" style="display:none" class="card">
  <h2 style="font-size:16px;margin-bottom:16px;">CSVインポート</h2>
  <p style="font-size:13px;color:#64748b;margin-bottom:12px;">
    <a href="/ai-chat/admin/sample_faq.csv" download style="color:#2563eb;">サンプルCSVをダウンロード</a>
    &nbsp;|&nbsp; 対応カラム: category / question / answer / keywords / priority（日本語ヘッダーも可）
  </p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="csv_import">
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
        <label>クライアント *</label>
        <select name="client_id" required>
          <option value="">選択してください</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;flex:2;min-width:220px;">
        <label>CSVファイル *</label>
        <input type="file" name="csv" accept=".csv" required>
      </div>
      <div style="display:flex;align-items:center;gap:6px;white-space:nowrap;padding-bottom:2px;">
        <input type="checkbox" name="auto_keywords" id="auto_kw" value="1" style="width:auto;">
        <label for="auto_kw" style="margin-bottom:0;font-weight:400;">キーワード自動生成（Claude API）</label>
      </div>
      <div style="padding-bottom:2px;display:flex;gap:8px;">
        <button class="btn btn-primary" type="submit">インポート</button>
        <button type="button" class="btn btn-sm" onclick="togglePanel('csv-panel')" style="background:#94a3b8;color:#fff">キャンセル</button>
      </div>
    </div>
  </form>
  <?php if ($csvResult): ?>
    <div style="margin-top:16px;font-size:13px;">
      <span style="color:#16a34a;font-weight:600;">✓ <?= (int)$csvResult['imported'] ?> 件インポート</span>
      <?php if ($csvResult['skipped']): ?>
        &nbsp; <span style="color:#64748b;"><?= (int)$csvResult['skipped'] ?> 件スキップ</span>
      <?php endif; ?>
      <?php if ($csvResult['errors']): ?>
        <ul style="margin-top:8px;color:#dc2626;list-style:disc;padding-left:20px;">
          <?php foreach ($csvResult['errors'] as $e): ?>
            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div id="add-panel" style="display:none" class="card">
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
    <button type="button" class="btn btn-sm" onclick="togglePanel('add-panel')" style="background:#94a3b8;color:#fff;margin-left:8px">キャンセル</button>
  </form>
</div>

<style>
.faq-filter-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;}
.faq-filter-bar select,.faq-filter-bar input{font-size:13px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;}
.answer-tip{position:relative;cursor:help;}
.answer-tip::after{
  content:attr(data-tip);
  display:none;
  position:absolute;left:0;top:calc(100% + 4px);z-index:200;
  background:#1e293b;color:#f1f5f9;
  font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-word;
  padding:10px 12px;border-radius:6px;width:320px;
  box-shadow:0 4px 16px rgba(0,0,0,.35);
  pointer-events:none;
}
.answer-tip:hover::after{display:block;}
</style>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="font-size:16px;margin:0;">FAQ一覧</h2>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-sm" onclick="togglePanel('csv-panel')" style="background:#0ea5e9;color:#fff">CSVインポート</button>
      <button class="btn btn-primary btn-sm" onclick="togglePanel('add-panel')">＋ FAQ追加</button>
    </div>
  </div>
  <div class="faq-filter-bar">
    <div>
      <label style="font-size:12px;display:block;margin-bottom:4px;color:#64748b;">クライアント</label>
      <select id="filter-client" onchange="onClientChange()">
        <option value="">すべて</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size:12px;display:block;margin-bottom:4px;color:#64748b;">カテゴリ</label>
      <select id="filter-category" onchange="filterFaqs()">
        <option value="">すべて</option>
      </select>
    </div>
    <div>
      <label style="font-size:12px;display:block;margin-bottom:4px;color:#64748b;">状態</label>
      <select id="filter-status" onchange="filterFaqs()">
        <option value="">すべて</option>
        <option value="1">公開中</option>
        <option value="0">非公開</option>
      </select>
    </div>
    <button class="btn btn-sm" style="background:#94a3b8;color:#fff;margin-bottom:1px" onclick="resetFilters()">リセット</button>
    <span id="filter-count" style="font-size:12px;color:#64748b;align-self:center;margin-bottom:2px;"></span>
  </div>
  <table>
    <thead><tr><th>クライアント</th><th>カテゴリ</th><th>質問（回答はホバーで確認）</th><th>状態</th><th>優先度</th><th></th></tr></thead>
    <tbody id="faq-tbody">
    <?php foreach ($faqs as $f): ?>
      <tr data-faq-id="<?= (int)$f['id'] ?>" data-client-id="<?= (int)$f['client_id'] ?>" data-category="<?= htmlspecialchars($f['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-status="<?= (int)$f['is_active'] ?>">
        <td><?= htmlspecialchars($f['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($f['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <span class="answer-tip" data-tip="【回答】&#10;<?= htmlspecialchars($f['answer'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(mb_substr($f['question'], 0, 50), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($f['question']) > 50 ? '…' : '' ?>
          </span>
        </td>
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
        <td style="white-space:nowrap">
          <button class="btn btn-sm" style="background:#0ea5e9;color:#fff" onclick="toggleEdit(<?= (int)$f['id'] ?>)">編集</button>
          <form method="post" onsubmit="return confirm('削除しますか？')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-danger btn-sm">削除</button>
          </form>
        </td>
      </tr>
      <tr id="edit-row-<?= (int)$f['id'] ?>" class="edit-row" style="display:none">
        <td colspan="6" style="padding:0">
          <div style="background:#f8fafc;border-top:2px solid #2563eb;padding:16px">
            <form method="post">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
              <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
                <div class="form-group" style="margin-bottom:0">
                  <label>カテゴリ</label>
                  <input type="text" name="category" value="<?= htmlspecialchars($f['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0">
                  <label>優先度</label>
                  <input type="number" name="priority" value="<?= (int)$f['priority'] ?>" min="0">
                </div>
              </div>
              <div class="form-group" style="margin-top:12px">
                <label>質問 *</label>
                <textarea name="question" rows="2" required><?= htmlspecialchars($f['question'], ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
              <div class="form-group">
                <label>回答 *</label>
                <textarea name="answer" rows="3" required><?= htmlspecialchars($f['answer'], ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
              <div class="form-group">
                <label>キーワード（空欄なら自動生成）</label>
                <input type="text" name="keywords" value="<?= htmlspecialchars($f['keywords'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <button class="btn btn-primary btn-sm" type="submit">更新</button>
              <button type="button" class="btn btn-sm" onclick="toggleEdit(<?= (int)$f['id'] ?>)" style="background:#94a3b8;color:#fff;margin-left:8px">キャンセル</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
// クライアントID → カテゴリ一覧のマップ（PHPから生成）
const clientCategoryMap = <?php
  $map = [];
  foreach ($faqs as $f) {
    $cid = (int)$f['client_id'];
    $cat = $f['category'] ?? '';
    if ($cat !== '' && !in_array($cat, $map[$cid] ?? [])) {
      $map[$cid][] = $cat;
    }
  }
  foreach ($map as &$cats) sort($cats);
  echo json_encode($map);
?>;

function onClientChange() {
  const clientVal = document.getElementById('filter-client').value;
  const catSel    = document.getElementById('filter-category');
  const prev      = catSel.value;

  catSel.innerHTML = '<option value="">すべて</option>';
  const cats = clientVal ? (clientCategoryMap[clientVal] || []) : Object.values(clientCategoryMap).flat().filter((v,i,a)=>a.indexOf(v)===i).sort();
  cats.forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    if (cat === prev) opt.selected = true;
    catSel.appendChild(opt);
  });

  filterFaqs();
}

function filterFaqs() {
  const clientVal   = document.getElementById('filter-client').value;
  const categoryVal = document.getElementById('filter-category').value;
  const statusVal   = document.getElementById('filter-status').value;
  const rows = document.querySelectorAll('#faq-tbody tr[data-faq-id]');
  let visible = 0;
  rows.forEach(row => {
    const matchClient   = !clientVal   || row.dataset.clientId === clientVal;
    const matchCategory = !categoryVal || row.dataset.category === categoryVal;
    const matchStatus   = statusVal === '' || row.dataset.status === statusVal;
    const show = matchClient && matchCategory && matchStatus;
    row.style.display = show ? '' : 'none';
    const editRow = document.getElementById('edit-row-' + row.dataset.faqId);
    if (editRow && !show) editRow.style.display = 'none';
    if (show) visible++;
  });
  document.getElementById('filter-count').textContent = `${visible} 件表示中`;
}

function togglePanel(id) {
  const p = document.getElementById(id);
  p.style.display = p.style.display === 'none' ? '' : 'none';
}
function toggleEdit(id) {
  const row = document.getElementById('edit-row-' + id);
  row.style.display = row.style.display === 'none' ? '' : 'none';
}

function resetFilters() {
  document.getElementById('filter-client').value   = '';
  document.getElementById('filter-category').value = '';
  document.getElementById('filter-status').value   = '';
  onClientChange();
}

onClientChange(); // 初期表示時に全カテゴリをセットしてフィルター適用
</script>
<?php
adminLayout('FAQ管理（管理者）', ob_get_clean(), 'admin');
