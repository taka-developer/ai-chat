<?php
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Claude.php';
require_once __DIR__ . '/../lib/FaqSearch.php';
require_once __DIR__ . '/../lib/RateLimit.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$widgetKey = trim($body['widget_key'] ?? '');
$message   = trim($body['message'] ?? '');
$sessionId = trim($body['session_id'] ?? '');

// バリデーション
if ($widgetKey === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'widget_key and message are required']);
    exit;
}
if (mb_strlen($message) > MAX_INPUT_LENGTH) {
    http_response_code(400);
    echo json_encode(['error' => 'message too long']);
    exit;
}

// クライアント取得
$pdo  = DB::get();
$stmt = $pdo->prepare('SELECT * FROM clients WHERE widget_key = ? LIMIT 1');
$stmt->execute([$widgetKey]);
$client = $stmt->fetch();

if (!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'invalid widget_key']);
    exit;
}

// レート制限
$ip = $_SERVER['REMOTE_ADDR'];
$rateLimit = new RateLimit();
if ($rateLimit->isExceeded($ip, $client['id'])) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit;
}

try {
    $claude    = new Claude();
    $faqSearch = new FaqSearch();

    // キーワード抽出
    $keywords = $claude->extractKeywords($message);

    // FAQ検索
    $matchedFaqs = $faqSearch->search($client['id'], $keywords);
    $matchedIds  = array_column($matchedFaqs, 'id');

    // 回答生成（非ストリーミング：stream.phpを使う場合はここを省略）
    $answer = '';
    $claude->streamAnswer(
        $client['system_prompt'] ?? '',
        $matchedFaqs,
        $message,
        function (string $chunk) use (&$answer) { $answer .= $chunk; }
    );

    // 会話ログ保存
    $pdo->prepare(
        'INSERT INTO conversation_logs (client_id, session_id, user_message, bot_response, matched_faq_ids)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $client['id'],
        $sessionId ?: null,
        $message,
        $answer,
        json_encode($matchedIds),
    ]);

    echo json_encode(['answer' => $answer]);

} catch (RuntimeException $e) {
    error_log($e->getMessage());
    $code = http_response_code();
    if ($code === 200) http_response_code(502);
    echo json_encode(['error' => 'Service temporarily unavailable']);
}
