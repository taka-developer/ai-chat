<?php
require_once __DIR__ . '/../lib/DB.php';

$widgetKey = htmlspecialchars(trim($_GET['key'] ?? ''), ENT_QUOTES, 'UTF-8');
if ($widgetKey === '') {
    http_response_code(400);
    exit('invalid key');
}

$stmt = DB::get()->prepare('SELECT contact_url FROM clients WHERE widget_key = ? LIMIT 1');
$stmt->execute([$widgetKey]);
$client = $stmt->fetch();
$contactUrl = $client ? ($client['contact_url'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>チャット</title>
  <link rel="stylesheet" href="assets/chat.css">
</head>
<body>
  <div id="chat-container">
    <header id="chat-header">
      <span id="chat-title">サポートチャット</span>
      <button id="close-btn" aria-label="閉じる">✕</button>
    </header>

    <div id="messages" role="log" aria-live="polite"></div>

    <div id="suggestions" aria-label="よくある質問"></div>

    <div id="input-area">
      <textarea
        id="user-input"
        placeholder="メッセージを入力…"
        rows="1"
aria-label="メッセージ入力"
      ></textarea>
      <button id="send-btn" disabled aria-label="送信">送信</button>
    </div>
    <div id="char-warning" hidden>200文字以内で入力してください</div>
  </div>

  <script>
    window.WIDGET_KEY   = <?= json_encode($widgetKey) ?>;
    window.BASE_URL     = <?= json_encode(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/')) ?>;
    window.CONTACT_URL  = <?= json_encode($contactUrl) ?>;
  </script>
  <script src="assets/chat.js"></script>
</body>
</html>
