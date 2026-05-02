<?php
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/../config/config.php';

class RateLimit
{
    /**
     * リクエストを記録し、制限超過なら true を返す
     */
    public function isExceeded(string $ip, int $clientId): bool
    {
        $pdo = DB::get();
        $windowStart = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);

        // 期限切れレコードを削除
        $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')
            ->execute([$windowStart]);

        // 現在のカウントを取得
        $stmt = $pdo->prepare(
            'SELECT id, request_count FROM rate_limits
              WHERE ip_address = ? AND client_id = ? AND window_start >= ?
              LIMIT 1'
        );
        $stmt->execute([$ip, $clientId, $windowStart]);
        $row = $stmt->fetch();

        if ($row) {
            if ($row['request_count'] >= RATE_LIMIT_MAX) return true;
            $pdo->prepare('UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?')
                ->execute([$row['id']]);
        } else {
            $pdo->prepare('INSERT INTO rate_limits (ip_address, client_id, request_count) VALUES (?, ?, 1)')
                ->execute([$ip, $clientId]);
        }

        return false;
    }
}
