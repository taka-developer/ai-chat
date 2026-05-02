<?php
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Claude.php';
require_once __DIR__ . '/../lib/FaqSearch.php';
require_once __DIR__ . '/../lib/RateLimit.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendEvent('error', 'Method not allowed');
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$widgetKey = trim($body['widget_key'] ?? '');
$message   = trim($body['message'] ?? '');
$sessionId = trim($body['session_id'] ?? '');

if ($widgetKey === '' || $message === '') {
    sendEvent('error', 'widget_key and message are required');
    exit;
}
if (mb_strlen($message) > MAX_INPUT_LENGTH) {
    sendEvent('error', 'message too long');
    exit;
}

$pdo  = DB::get();
$stmt = $pdo->prepare('SELECT * FROM clients WHERE widget_key = ? LIMIT 1');
$stmt->execute([$widgetKey]);
$client = $stmt->fetch();

if (!$client) {
    sendEvent('error', 'invalid widget_key');
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
if ((new RateLimit())->isExceeded($ip, $client['id'])) {
    sendEvent('error', 'rate_limit_exceeded');
    exit;
}

try {
    $claude    = new Claude();
    $faqSearch = new FaqSearch();

    $keywords    = $claude->extractKeywords($message);
    $matchedFaqs = $faqSearch->search($client['id'], $keywords);
    $matchedIds  = array_column($matchedFaqs, 'id');

    $fullAnswer = '';
    $claude->streamAnswer(
        $client['system_prompt'] ?? '',
        $matchedFaqs,
        $message,
        function (string $chunk) use (&$fullAnswer) {
            $fullAnswer .= $chunk;
            sendEvent('chunk', $chunk);
            ob_flush();
            flush();
        }
    );

    // 会話ログ保存
    $pdo->prepare(
        'INSERT INTO conversation_logs (client_id, session_id, user_message, bot_response, matched_faq_ids)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $client['id'],
        $sessionId ?: null,
        $message,
        $fullAnswer,
        json_encode($matchedIds),
    ]);

    sendEvent('done', '');

} catch (RuntimeException $e) {
    error_log($e->getMessage());
    sendEvent('error', 'service_unavailable');
}

function sendEvent(string $event, string $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}
