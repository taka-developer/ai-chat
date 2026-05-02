<?php
require_once __DIR__ . '/DB.php';

class FaqSearch
{
    /**
     * キーワード配列でFAQを検索し、スコア上位3件を返す
     */
    public function search(int $clientId, array $keywords): array
    {
        $pdo = DB::get();

        $stmt = $pdo->prepare(
            'SELECT id, question, answer, keywords, priority, updated_at
               FROM faqs
              WHERE client_id = ? AND is_active = 1'
        );
        $stmt->execute([$clientId]);
        $faqs = $stmt->fetchAll();

        $scored = [];
        foreach ($faqs as $faq) {
            $score = 0;
            foreach ($keywords as $kw) {
                $kw = mb_strtolower($kw);
                if (mb_strpos(mb_strtolower($faq['keywords'] ?? ''), $kw) !== false) $score++;
                if (mb_strpos(mb_strtolower($faq['question']), $kw) !== false) $score++;
            }
            $score += (int)$faq['priority'];

            if ($score > 0) {
                $scored[] = ['faq' => $faq, 'score' => $score];
            }
        }

        // スコア降順 → priority降順 → updated_at降順
        usort($scored, function ($a, $b) {
            if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
            if ($b['faq']['priority'] !== $a['faq']['priority']) return $b['faq']['priority'] - $a['faq']['priority'];
            return strcmp($b['faq']['updated_at'], $a['faq']['updated_at']);
        });

        $top = array_slice($scored, 0, MAX_FAQ_RESULTS);
        return array_column($top, 'faq');
    }
}
