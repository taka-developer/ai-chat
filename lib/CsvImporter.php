<?php
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Claude.php';

class CsvImporter
{
    private PDO $pdo;

    // CSVの必須ヘッダー
    private const REQUIRED = ['question', 'answer'];

    // 許容するヘッダーの表記ゆれ（日本語 → 内部キー）
    private const ALIASES = [
        'カテゴリ'   => 'category',
        'category'   => 'category',
        '質問'       => 'question',
        'question'   => 'question',
        '回答'       => 'answer',
        'answer'     => 'answer',
        'キーワード' => 'keywords',
        'keywords'   => 'keywords',
        '優先度'     => 'priority',
        'priority'   => 'priority',
    ];

    public function __construct()
    {
        $this->pdo = DB::get();
    }

    /**
     * アップロードされた CSV ファイルを FAQ としてインポートする
     *
     * @param  array  $file          $_FILES['csv'] の要素
     * @param  int    $clientId      インポート先クライアント ID
     * @param  bool   $autoKeywords  キーワードが空の行を Claude API で自動生成するか
     * @return array{imported:int, skipped:int, errors:string[]}
     */
    public function import(array $file, int $clientId, bool $autoKeywords = false): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'ファイルのアップロードに失敗しました。';
            return $result;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $result['errors'][] = 'CSV ファイルのみアップロード可能です。';
            return $result;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $result['errors'][] = 'ファイルを開けませんでした。';
            return $result;
        }

        // BOM 除去
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // ヘッダー行を読み込んで正規化
        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            $result['errors'][] = 'CSV が空です。';
            fclose($handle);
            return $result;
        }

        $headers = $this->normalizeHeaders($rawHeaders);
        $missing = array_diff(self::REQUIRED, $headers);
        if ($missing) {
            $result['errors'][] = '必須カラムがありません: ' . implode(', ', $missing);
            fclose($handle);
            return $result;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO faqs (client_id, category, question, answer, keywords, priority) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $claude = $autoKeywords ? new Claude() : null;
        $lineNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            $data = $this->mapRow($headers, $row);

            $question = trim($data['question'] ?? '');
            $answer   = trim($data['answer'] ?? '');

            if ($question === '' || $answer === '') {
                $result['skipped']++;
                $result['errors'][] = "{$lineNo} 行目: question/answer が空のためスキップ";
                continue;
            }

            $category = trim($data['category'] ?? '');
            $keywords = trim($data['keywords'] ?? '');
            $priority = (int)($data['priority'] ?? 0);

            if ($keywords === '' && $claude) {
                try {
                    $kw      = $claude->generateFaqKeywords($question, $answer);
                    $keywords = implode(',', $kw);
                } catch (Throwable) {}
            }

            $stmt->execute([$clientId, $category, $question, $answer, $keywords, $priority]);
            $result['imported']++;
        }

        fclose($handle);
        return $result;
    }

    /** ヘッダー行を内部キーに変換 */
    private function normalizeHeaders(array $raw): array
    {
        return array_map(function (string $h) {
            $h = trim($h);
            return self::ALIASES[$h] ?? strtolower($h);
        }, $raw);
    }

    /** ヘッダーと値を連想配列にマッピング */
    private function mapRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $i => $key) {
            $data[$key] = $row[$i] ?? '';
        }
        return $data;
    }
}
