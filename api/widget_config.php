<?php
require_once __DIR__ . '/../lib/DB.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$widgetKey = trim($_GET['key'] ?? '');
if ($widgetKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'widget_key is required']);
    exit;
}

$stmt = DB::get()->prepare('SELECT id, name FROM clients WHERE widget_key = ? LIMIT 1');
$stmt->execute([$widgetKey]);
$client = $stmt->fetch();

if (!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'invalid widget_key']);
    exit;
}

echo json_encode(['client_name' => htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8')]);
