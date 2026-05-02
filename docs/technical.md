# 技術仕様書

別の開発者がコードを読まずにシステムを把握・拡張できることを目的としたリファレンス。

---

## システムアーキテクチャ概要

```
クライアントサイト
    │
    │  <script src="https://chat.stekwired.jp/widget.js" data-key="xxx">
    ▼
[public/widget.js]
    │  iframe を生成・DOM に挿入
    ▼
[public/chat.php]  ← iframe 本体（チャット UI）
    │  SSE fetch
    ▼
[api/stream.php]   ← メインエンドポイント
    ├─ [lib/Claude.php]      キーワード抽出
    ├─ [lib/FaqSearch.php]   FAQ 検索・スコアリング
    ├─ [lib/Claude.php]      回答生成（擬似ストリーミング）
    └─ [lib/DB.php]          会話ログ保存（MySQL）

管理者ブラウザ
    ▼
[admin/*.php / admin/editor/*.php]
    ├─ [lib/Auth.php]        認証・セッション管理
    ├─ [lib/DB.php]          DB 操作
    └─ [lib/CsvImporter.php] CSV 一括インポート
```

---

## ディレクトリ構成と各ファイルの役割

```
/
├── public/
│   ├── widget.js          クライアントサイト埋め込みスクリプト（起動ボタン + iframe 生成）
│   ├── chat.php           チャット iframe 本体（HTML/CSS/JS を出力）
│   └── assets/
│       ├── chat.css       チャット UI スタイル
│       └── chat.js        チャット UI ロジック（SSE 受信・メッセージ表示・フィードバック）
├── api/
│   ├── stream.php         SSE ストリーミングエンドポイント（メイン）
│   ├── message.php        非ストリーミング版メッセージエンドポイント（代替用）
│   └── widget_config.php  widget_key からクライアント名を返す
├── admin/
│   ├── _layout.php        管理画面共通レイアウト（ヘッダー・ナビ）
│   ├── login.php          ログインフォーム
│   ├── logout.php         セッション破棄 → login.php へリダイレクト
│   ├── index.php          管理者ダッシュボード
│   ├── clients.php        クライアント管理（CRUD）
│   ├── faqs.php           FAQ 管理・全クライアント横断（CRUD + CSV インポート）
│   ├── logs.php           会話ログ（フィルタ・ページネーション）
│   ├── users.php          ユーザー管理（admin_users の CRUD）
│   ├── profile.php        パスワード変更（管理者・編集者共通）
│   ├── sample_faq.csv     CSV インポート用テンプレート
│   └── editor/
│       ├── index.php      編集者ダッシュボード
│       ├── faqs.php       FAQ 管理（自社のみ）
│       └── categories.php カテゴリ管理
├── api/                   （上記）
├── lib/
│   ├── DB.php             PDO シングルトン接続
│   ├── Claude.php         Claude API クライアント
│   ├── FaqSearch.php      FAQ 検索・スコアリング
│   ├── CsvImporter.php    FAQ CSV 一括インポート
│   ├── Auth.php           セッションベース認証
│   └── RateLimit.php      レート制限（IP × クライアント）
├── config/
│   ├── config.php         環境設定（Git 管理外）
│   └── config.example.php 設定テンプレート
├── sql/
│   └── schema.sql         テーブル定義・初期データ・マイグレーションコメント
└── tools/
    └── reset_password.php 管理者パスワードリセット（ローカル作業用）
```

---

## クラス仕様

### `Claude` — `lib/Claude.php`

Claude API（Anthropic）への HTTP リクエストをラップするクライアント。  
コンストラクタで `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` / `API_TIMEOUT` を読み込む。

#### メソッド一覧

| メソッド | 引数 | 戻り値 | 説明 |
|---|---|---|---|
| `extractKeywords(string $userMessage)` | ユーザー入力文 | `string[]` | ユーザー入力から検索キーワードを 3〜5 個抽出。JSON 配列を返す Claude API レスポンスをパース |
| `streamAnswer(string $systemPrompt, array $faqs, string $userMessage, callable $onChunk)` | システムプロンプト・FAQ 配列・ユーザー入力・コールバック | `void` | FAQ + ユーザー質問を Claude API に送信し、回答を擬似ストリーミングで分割配信。`$onChunk(string $chunk)` を句読点・5文字単位で呼び出す |
| `generateFaqKeywords(string $question, string $answer)` | 質問文・回答文 | `string[]` | FAQ の Q/A から MySQL LIKE 検索用キーワードを 5〜10 個生成 |
| `getUsageLog()` | — | `array[]` | このインスタンスで実行した全 API 呼び出しのトークン使用量を返す。各要素は `call / model / input_tokens / output_tokens` を持つ |

#### `request()` — プライベートメソッド

| パラメータ | 説明 |
|---|---|
| `string $label` | 使用量ログ用のラベル（メソッド名）|
| `array $messages` | Anthropic Messages API の `messages` 配列 |
| `int $maxTokens` | `max_tokens` の値 |

- `CURLOPT_SSL_VERIFYPEER`: ローカル開発では `false`。**本番では `true` に戻すこと**
- HTTP 4xx でも `RuntimeException` を投げる（特に 401 は API キー不正）

---

### `FaqSearch` — `lib/FaqSearch.php`

FAQ の全文検索とスコアリングを行う。

#### `search(int $clientId, array $keywords): array`

1. `faqs` テーブルから `client_id = $clientId AND is_active = 1` の全件を取得
2. キーワードごとに以下をスコア加算：
   - `keywords` カラムに含まれる場合 +1
   - `question` カラムに含まれる場合 +1
3. `priority` カラムの値をスコアに加算
4. スコア > 0 のものだけを残し、スコア降順 → priority 降順 → updated_at 降順でソート
5. 上位 `MAX_FAQ_RESULTS` 件（デフォルト 3）の FAQ 連想配列を返す

**スコア計算式**:  
`score = キーワードヒット数（keywords列）+ キーワードヒット数（question列）+ priority`

---

### `CsvImporter` — `lib/CsvImporter.php`

管理画面からアップロードされた CSV を FAQ として一括インポートする。

#### `import(array $file, int $clientId, bool $autoKeywords = false): array`

| 引数 | 型 | 説明 |
|---|---|---|
| `$file` | `array` | `$_FILES['csv']` の要素 |
| `$clientId` | `int` | インポート先クライアント ID |
| `$autoKeywords` | `bool` | キーワード列が空の行を Claude API で自動生成するか |

**戻り値**: `['imported' => int, 'skipped' => int, 'errors' => string[]]`

**処理フロー**:
1. ファイルエラー確認（`UPLOAD_ERR_OK`）
2. 拡張子確認（`.csv` のみ）
3. UTF-8 BOM 除去（`\xEF\xBB\xBF`）
4. ヘッダー行を正規化（エイリアス変換）
5. 必須カラム確認（`question` / `answer`）
6. 行ループ → `question` / `answer` が空ならスキップ → INSERT

**対応ヘッダーエイリアス**:

| CSV 列名 | 内部キー |
|---|---|
| `カテゴリ` / `category` | `category` |
| `質問` / `question` | `question` |
| `回答` / `answer` | `answer` |
| `キーワード` / `keywords` | `keywords` |
| `優先度` / `priority` | `priority` |

---

### `Auth` — `lib/Auth.php`

セッションベース認証。全メソッドが静的メソッド。

#### セッション変数一覧

| キー | 型 | 内容 |
|---|---|---|
| `user_id` | `int` | ログインユーザーの ID |
| `user_email` | `string` | ログインユーザーのメールアドレス |
| `role` | `string` | `'admin'` または `'editor'` |
| `client_id` | `int\|null` | 編集者の所属クライアント ID（管理者は NULL）|
| `last_activity` | `int` | 最終アクセス時刻（Unixタイムスタンプ）|

#### メソッド一覧

| メソッド | 説明 |
|---|---|
| `start()` | セッションを開始。ログイン済みの場合はセッションタイムアウトチェックを行い、超過時はセッション破棄 → `login.php?timeout=1` へリダイレクト |
| `login(string $email, string $password): bool` | ログイン処理。ロック確認 → パスワード照合 → 失敗カウント管理 → セッション設定。成功で `true`、失敗で `false` |
| `loginError(): string` | 直前の `login()` 失敗理由を返す。`'locked'` または `''`（通常の認証失敗） |
| `logout(): void` | セッションを破棄 |
| `requireAdmin(): void` | `role = 'admin'` でなければ `login.php` へリダイレクトして終了 |
| `requireEditor(): void` | 未認証（`admin` / `editor` どちらでもない）なら `login.php` へリダイレクトして終了 |
| `clientId(): ?int` | セッションの `client_id` を返す（未設定時は `null`）|
| `role(): string` | セッションの `role` を返す |
| `userId(): int` | セッションの `user_id` を返す |
| `userEmail(): string` | セッションの `user_email` を返す |

**ログイン失敗ロック仕様**: 連続 5 回失敗で 30 分ロック。`admin_users.login_locked_until` に解除日時を記録。

---

### `RateLimit` — `lib/RateLimit.php`

IP アドレス × クライアント ID の組み合わせでリクエスト数を管理する。

#### `isExceeded(string $ip, int $clientId): bool`

1. `window_start < (現在時刻 - RATE_LIMIT_WINDOW)` のレコードを削除
2. 直近ウィンドウ内の `rate_limits` レコードを参照
3. `request_count >= RATE_LIMIT_MAX` なら `true` を返し処理中断
4. それ以外はカウントを +1 して `false` を返す

**仕様**: `RATE_LIMIT_MAX`（デフォルト 30）回 / `RATE_LIMIT_WINDOW`（デフォルト 3600 秒）

---

### `DB` — `lib/DB.php`

PDO シングルトン。

#### `get(): PDO`

接続済み PDO インスタンスを返す（初回呼び出し時のみ接続）。

```php
$pdo = DB::get();
$stmt = $pdo->prepare('SELECT * FROM faqs WHERE client_id = ?');
$stmt->execute([$clientId]);
```

**PDO 設定**:
- `ERRMODE_EXCEPTION` — SQL エラーで例外をスロー
- `FETCH_ASSOC` — フェッチ結果は連想配列
- `EMULATE_PREPARES = false` — 真のプリペアドステートメント

---

## API エンドポイント仕様

### `POST /api/stream.php` — SSE ストリーミング

チャットのメインエンドポイント。Server-Sent Events で回答を逐次配信する。

**リクエスト**

```json
{
  "widget_key": "埋め込みキー（必須）",
  "message": "ユーザー入力（必須・200文字以内）",
  "session_id": "セッション識別子（省略可）"
}
```

**SSE イベント一覧**

| イベント名 | data の内容 | 説明 |
|---|---|---|
| `chunk` | 回答テキストの断片（string） | 擬似ストリーミングの各チャンク |
| `usage` | API 使用量の JSON 配列 | 開発用コンソール表示用 |
| `done` | `""` | 全チャンク送信完了 |
| `error` | エラーコード（string） | エラー発生時 |

**エラーコード一覧**

| コード | 原因 |
|---|---|
| `widget_key and message are required` | リクエストパラメータ不足 |
| `message too long` | 200 文字超 |
| `invalid widget_key` | 存在しない widget_key |
| `rate_limit_exceeded` | レート制限超過 |
| `service_unavailable` | Claude API / DB エラー |

---

### `POST /api/message.php` — 非ストリーミング

SSE が使えない環境向けの代替エンドポイント。全文を JSON で返す。

**リクエスト**: `stream.php` と同形式

**レスポンス（成功）**

```json
{ "answer": "回答テキスト" }
```

**レスポンス（失敗）**

```json
{ "error": "エラーメッセージ" }
```

HTTP ステータス: 400（バリデーション）/ 404（widget_key 不正）/ 429（レート制限）/ 502（Claude API エラー）

---

### `GET /api/widget_config.php` — クライアント情報取得

`widget.js` が iframe ロード前にクライアント名を取得するために使用。

**クエリパラメータ**: `key=<widget_key>`

**レスポンス（成功）**

```json
{ "client_name": "株式会社サンプル" }
```

**レスポンス（失敗）**: HTTP 400 / 404 + `{ "error": "..." }`

---

## `config/config.php` 定数一覧

| 定数名 | 型 | デフォルト値 | 説明 |
|---|---|---|---|
| `ANTHROPIC_API_KEY` | string | `'sk-ant-xxxx'` | Anthropic API キー |
| `ANTHROPIC_MODEL` | string | `'claude-haiku-4-5-20251001'` | 使用するモデル ID |
| `DB_HOST` | string | `'localhost'` | MySQL ホスト |
| `DB_NAME` | string | `'chatbot'` | データベース名 |
| `DB_USER` | string | `'root'` | MySQL ユーザー名 |
| `DB_PASS` | string | `''` | MySQL パスワード |
| `RATE_LIMIT_MAX` | int | `30` | レート制限：ウィンドウ内の最大リクエスト数 |
| `RATE_LIMIT_WINDOW` | int | `3600` | レート制限：ウィンドウ幅（秒） |
| `API_TIMEOUT` | int | `15` | Claude API タイムアウト（秒） |
| `MAX_INPUT_LENGTH` | int | `200` | ユーザー入力の最大文字数 |
| `MAX_FAQ_RESULTS` | int | `3` | FAQ 検索で Claude に渡す上位件数 |
| `SESSION_TIMEOUT` | int | `3600` | 管理画面のセッションタイムアウト（秒） |

---

## ローカル環境構築手順（Windows / XAMPP）

### 1. XAMPP のインストール

XAMPP（https://www.apachefriends.org/）をインストールし、Apache + MySQL を起動する。

### 2. プロジェクトの配置

シンボリックリンクを使って XAMPP のドキュメントルートにプロジェクトを配置する：

```powershell
New-Item -ItemType SymbolicLink `
  -Path "C:\xampp\htdocs\ai-chat" `
  -Target "D:\project\taka-developer\AI-Chat\ai-chat"
```

### 3. 設定ファイルの作成

```powershell
Copy-Item config\config.example.php config\config.php
```

`config/config.php` を編集して Anthropic API キーと DB 接続情報を設定する。

### 4. データベースの作成

phpMyAdmin（http://localhost/phpmyadmin）から：

1. 「新規作成」で `chatbot` データベースを作成（照合順序: `utf8mb4_unicode_ci`）
2. `chatbot` を選択 → 「インポート」タブ → `sql/schema.sql` を選択して実行

または MySQL CLI から：

```bash
mysql -u root -p < sql/schema.sql
```

### 5. 動作確認

- チャット UI: http://localhost/ai-chat/public/chat.php?key=demo_key_change_in_production
- 管理画面: http://localhost/ai-chat/admin/login.php
  - 初期メール: `admin@stekwired.jp` / 初期パスワード: `admin123`

---

## 本番環境の要件

| 項目 | 要件 |
|---|---|
| PHP | 8.1 以上 |
| MySQL | 8.0 以上 |
| PHP 拡張 | `pdo_mysql`、`mbstring`、`curl`、`json`、`fileinfo` |
| Web サーバー | Apache 2.4 以上（または Nginx） |
| SSL | HTTPS 必須（API キー保護のため） |

---

## 既知の制約・注意事項

### 擬似ストリーミング

`Claude::streamAnswer()` は本来 SSE 対応の curl ストリーミングで実装する設計だが、Windows / XAMPP 環境では `CURLOPT_WRITEFUNCTION` が不安定なため、全文取得後に句読点・5文字単位で分割配信する擬似ストリーミングを採用している。

本番の Linux 環境では本物のストリーミングへの移行も可能だが、現状の擬似実装でも UX 上の問題は少ない。

### SSL 証明書の検証

`lib/Claude.php` の `request()` メソッドで `CURLOPT_SSL_VERIFYPEER => false` / `CURLOPT_SSL_VERIFYHOST => false` に設定している（ローカル開発用）。**本番デプロイ前に `true` / `2` に戻すこと。**

### セッションタイムアウト定数

`SESSION_TIMEOUT` は `config/config.php` で定義する。未定義の場合は `Auth::start()` 内でデフォルト値 3600 秒が使用される。

### サブディレクトリ配置

現在のコードはサブディレクトリ `/ai-chat/` 配置を前提としており、リダイレクト先・資産パスに `/ai-chat/` プレフィックスが含まれている。本番でルートに配置する場合はこれらを変更する必要がある。詳細は `README.md` の本番デプロイ時の注意事項を参照。
