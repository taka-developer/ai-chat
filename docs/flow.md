# 処理フロー

---

## 1. メッセージ送受信フロー（全体）

```
ユーザー入力
    │
    ▼
[chat.js] バリデーション（空・200文字超チェック）
    │
    ▼
[api/stream.php] POST受信
    │
    ├─ widget_key → クライアント特定
    ├─ バリデーション（空・200文字超）
    └─ レート制限チェック（30回/時）
         │ 超過 → 429 エラーイベント送信
         ▼
[lib/Claude.php] ① キーワード抽出
    │  ユーザー入力 → Claude API → JSON配列（3〜5語）
    │  例: ["土日", "診療", "休日"]
    ▼
[lib/FaqSearch.php] ② FAQ検索・スコアリング
    │  キーワード × keywords/question カラム LIKE検索
    │  スコア上位3件を取得
    ▼
[lib/Claude.php] ③ 回答生成
    │  システムプロンプト + ヒットFAQ + ユーザー質問 → Claude API
    │  回答テキストを擬似ストリーミングで送信
    ▼
[api/stream.php] 会話ログ保存（conversation_logs テーブル）
    │
    ▼
[chat.js] SSE受信 → チャット画面に表示
```

---

## 2. SSE（Server-Sent Events）フロー

`api/stream.php` は `text/event-stream` でレスポンスを返す。

```
event: chunk        ← 回答テキストの断片
data: "こんにちは"

event: chunk
data: "！ご質問は"

event: usage        ← トークン使用量（開発用コンソール表示）
data: "[{...}]"

event: done         ← 完了
data: ""
```

エラー発生時：

```
event: error
data: "rate_limit_exceeded"
```

---

## 3. FAQ 検索・スコアリング詳細

```php
foreach ($faqs as $faq) {
    $score = 0;
    foreach ($keywords as $kw) {
        if (str_contains($faq['keywords'], $kw)) $score++;   // +1
        if (str_contains($faq['question'], $kw)) $score++;   // +1
    }
    $score += $faq['priority'];  // priority 値を加算
}

// ソート順：スコア降順 → priority 降順 → updated_at 降順
// 上位 MAX_FAQ_RESULTS 件（デフォルト3件）を取得
```

---

## 4. Claude API 呼び出し詳細

1回のチャットで Claude API を **最大2回** 呼び出す。

| 呼び出し | メソッド | 用途 | max_tokens |
|---|---|---|---|
| `extractKeywords()` | `request()` | キーワード抽出 | 256 |
| `streamAnswer()` | `request()` | 回答生成 | 1024 |

> Windows/XAMPP では `CURLOPT_WRITEFUNCTION` が不安定なため、回答生成も通常リクエストで全文取得後に擬似ストリーミング（句読点・5文字単位で分割送信）で代替している。

---

## 5. 認証フロー（管理画面）

```
GET /ai-chat/admin/index.php
    │
    ▼
Auth::start()
    │
    ├─ セッションあり & last_activity 超過 → セッション破棄 → login.php?timeout=1
    └─ タイムアウトなし → last_activity 更新
         │
         ▼
Auth::requireAdmin()
    │
    ├─ セッション未認証 → /ai-chat/admin/login.php へリダイレクト
    └─ 認証済み
         │
         ├─ role = 'admin'  → 管理者画面（全クライアント操作可）
         └─ role = 'editor' → /ai-chat/admin/editor/ へリダイレクト
                              （自社 client_id のデータのみ操作可）
```

---

## 6. レート制限フロー

```
リクエスト受信
    │
    ▼
rate_limits テーブルを参照（IP × client_id × 直近1時間）
    │
    ├─ 30回未満 → request_count + 1 → 処理継続
    └─ 30回以上 → 429 / rate_limit_exceeded
```

期限切れレコード（1時間超）は次回リクエスト時に自動削除。

---

## 7. CSV インポートフロー

管理画面の FAQ 管理ページから CSV を一括インポートする。

```
ファイルアップロード（POST enctype="multipart/form-data"）
    │
    ▼
CsvImporter::import()
    │
    ├─ ファイルエラーチェック（UPLOAD_ERR_OK）
    ├─ 拡張子チェック（.csv のみ）
    ├─ BOM 除去（UTF-8 BOM: \xEF\xBB\xBF）
    ├─ ヘッダー行の正規化（日本語→内部キー変換）
    └─ 必須カラム確認（question / answer）
         │
         ▼
    行ループ
         │
         ├─ question / answer が空 → skipped++
         │
         └─ keywords が空 かつ autoKeywords=true
              │
              ▼
         Claude API（generateFaqKeywords）
              │
              ▼
         INSERT INTO faqs → imported++
    │
    ▼
結果を返す {imported, skipped, errors[]}
```

**対応ヘッダーエイリアス**

| CSV 列名 | 内部キー |
|---|---|
| `カテゴリ` / `category` | `category` |
| `質問` / `question` | `question` |
| `回答` / `answer` | `answer` |
| `キーワード` / `keywords` | `keywords` |
| `優先度` / `priority` | `priority` |

---

## 8. ログイン失敗ロックフロー

```
POST /ai-chat/admin/login.php
    │
    ▼
Auth::login()
    │
    ├─ ユーザーが存在しない → false（エラーなし）
    │
    ├─ login_locked_until > NOW() → false（Auth::loginError() = 'locked'）
    │
    ├─ password_verify() 失敗
    │    │
    │    ├─ login_failed_count < 5 → カウント +1 → false
    │    └─ login_failed_count >= 5 → login_locked_until = NOW()+30分 → false（'locked'）
    │
    └─ 成功 → login_failed_count=0, login_locked_until=NULL → セッション設定
```

ロック中のエラーメッセージ：「ログインに5回失敗しました。30分後に再試行してください。」

---

## 9. セッションタイムアウトフロー

```
Auth::start()（全管理ページで実行）
    │
    ├─ $_SESSION['user_id'] なし → スルー（未認証ユーザーは対象外）
    │
    └─ $_SESSION['user_id'] あり
         │
         ├─ last_activity 未設定 → last_activity = NOW() → 継続
         │
         ├─ NOW() - last_activity <= SESSION_TIMEOUT → last_activity 更新 → 継続
         │
         └─ NOW() - last_activity > SESSION_TIMEOUT
              │
              ▼
         session_destroy() → session_start()（空セッション）
              │
              ▼
         redirect: /ai-chat/admin/login.php?timeout=1
              │
              ▼（login.php）
         「セッションの有効期限が切れました。再度ログインしてください。」
```

---

## 10. FAQ キーワード自動生成フロー

FAQ 登録・保存時に `keywords` が空の場合、Claude API を呼び出してキーワードを自動生成する。

```
FAQ保存
    │
    ├─ keywords が入力済み → そのまま保存
    └─ keywords が空
         │
         ▼
    Claude API（generateFaqKeywords）
         │ question + answer → キーワード 5〜10語
         ▼
    自動生成キーワードを DB に保存
```
