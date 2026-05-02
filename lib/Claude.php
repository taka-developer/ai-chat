<?php
require_once __DIR__ . '/../config/config.php';

class Claude
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    // API呼び出しごとのトークン使用量を蓄積
    private array $usageLog = [];

    public function __construct()
    {
        $this->apiKey  = ANTHROPIC_API_KEY;
        $this->model   = ANTHROPIC_MODEL;
        $this->timeout = API_TIMEOUT;
    }

    /** 今セッションの全API呼び出し使用量を返す */
    public function getUsageLog(): array
    {
        return $this->usageLog;
    }

    public function extractKeywords(string $userMessage): array
    {
        $prompt = "以下のユーザー入力から検索キーワードを3〜5個抽出してください。\n"
                . "JSONの配列のみで返してください。他の文字列は含めないこと。\n\n"
                . "入力：「{$userMessage}」";

        $response = $this->request('extractKeywords', [
            ['role' => 'user', 'content' => $prompt]
        ], 256);

        $json = trim($response);
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json);
        $json = preg_replace('/\s*```$/', '', $json);
        $json = trim($json);

        $keywords = json_decode($json, true);
        return is_array($keywords) ? $keywords : [];
    }

    public function streamAnswer(string $systemPrompt, array $faqs, string $userMessage, callable $onChunk): void
    {
        $faqText = '';
        foreach ($faqs as $faq) {
            $faqText .= "Q: {$faq['question']}\nA: {$faq['answer']}\n\n";
        }

        $content = ($systemPrompt ? $systemPrompt . "\n\n" : '')
                 . "以下のFAQを参考にユーザーの質問に自然な日本語で回答してください。\n"
                 . "FAQに記載のない内容については、詳しくは「お問い合わせください」と案内してください。\n"
                 . "ユーザーから個人情報を求めないこと。\n\n"
                 . ($faqText ? "【参考FAQ】\n{$faqText}\n" : '')
                 . "【ユーザーの質問】\n{$userMessage}";

        $fullText = $this->request('streamAnswer', [
            ['role' => 'user', 'content' => $content]
        ], 1024);

        // 擬似ストリーミング（Windows/XAMPPでWRITEFUNCTIONが不安定なため）
        $chars = mb_str_split($fullText);
        $buf   = '';
        foreach ($chars as $char) {
            $buf .= $char;
            if (mb_strlen($buf) >= 5 || in_array($char, ['。', '、', '！', '？', '．', '，', "\n"])) {
                $onChunk($buf);
                $buf = '';
                flush();
            }
        }
        if ($buf !== '') {
            $onChunk($buf);
            flush();
        }
    }

    public function generateFaqKeywords(string $question, string $answer): array
    {
        $prompt = "以下のFAQからMySQLのLIKE検索に使う検索キーワードを5〜10個生成してください。\n"
                . "JSONの配列のみで返してください。\n\n"
                . "質問：{$question}\n答：{$answer}";

        $response = $this->request('generateFaqKeywords', [
            ['role' => 'user', 'content' => $prompt]
        ], 512);

        $json     = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($response))));
        $keywords = json_decode($json, true);
        return is_array($keywords) ? $keywords : [];
    }

    private function request(string $label, array $messages, int $maxTokens = 1024): string
    {
        $body = json_encode([
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        if ($result === false || $curlErr) {
            error_log("Claude API curl error: {$curlErr}");
            throw new RuntimeException('Claude API curl error: ' . $curlErr);
        }
        if ($httpCode >= 500) {
            error_log("Claude API HTTP error: {$httpCode} body={$result}");
            throw new RuntimeException('Claude API server error');
        }
        if ($httpCode === 0) {
            throw new RuntimeException('Claude API timeout');
        }
        if ($httpCode >= 400) {
            error_log("Claude API client error: {$httpCode} body={$result}");
            throw new RuntimeException('Claude API client error: ' . $httpCode);
        }

        $data = json_decode($result, true);

        // トークン使用量を記録
        if (isset($data['usage'])) {
            $this->usageLog[] = [
                'call'         => $label,
                'model'        => $this->model,
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens'=> $data['usage']['output_tokens'] ?? 0,
            ];
        }

        return $data['content'][0]['text'] ?? '';
    }
}
