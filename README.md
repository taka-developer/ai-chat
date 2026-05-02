# 問い合わせチャットウィジェット

STEKWIREDがクライアントサイトに提供する問い合わせチャットボット。  
Claude API（Anthropic）を使用し、PHP + MySQL + Vanilla JS で構成した SaaS 型ウィジェット。

---

## 特徴

- クライアントサイトに **JavaScript 1行** で埋め込み可能
- FAQ をキーワード検索で絞り込み、Claude API で自然言語回答を生成
- 複数クライアントを STEKWIRED が一元管理
- 管理者（STEKWIRED）と編集者（クライアント担当者）の 2 ロール構成

---

## 技術スタック

| レイヤー | 技術 |
|---|---|
| バックエンド | PHP 8.x |
| データベース | MySQL 8.x |
| フロントエンド | Vanilla JS（フレームワーク不使用） |
| 外部 API | Anthropic Claude API（`claude-haiku-4-5-20251001`） |
| 埋め込み方式 | iframe |

---

## ディレクトリ構成

```
/
├── public/
│   ├── widget.js          # クライアントサイト埋め込みスクリプト
│   ├── chat.php           # チャット iframe 本体
│   └── assets/
│       ├── chat.css       # チャット UI スタイル
│       └── chat.js        # チャット UI ロジック
├── api/
│   ├── message.php        # メッセージ送受信エンドポイント
│   ├── stream.php         # SSE ストリーミングエンドポイント
│   └── widget_config.php  # ウィジェット設定取得
├── admin/
│   ├── login.php / logout.php
│   ├── index.php          # 管理者ダッシュボード
│   ├── clients.php        # クライアント管理（追加・編集・削除）
│   ├── faqs.php           # FAQ 管理（管理者・追加・編集・削除・CSV インポート）
│   ├── logs.php           # 会話ログ
│   ├── users.php          # ユーザー管理（編集者アカウント追加・削除）
│   ├── profile.php        # パスワード変更（管理者・編集者共通）
│   └── editor/
│       ├── index.php      # 編集者ダッシュボード
│       ├── faqs.php       # FAQ 管理（編集者・追加・編集・削除・CSV インポート）
│       └── categories.php # カテゴリ管理
├── lib/
│   ├── Claude.php         # Claude API クライアント
│   ├── CsvImporter.php    # FAQ CSV 一括インポート
│   ├── FaqSearch.php      # FAQ 検索・スコアリング
│   ├── Auth.php           # 認証
│   ├── RateLimit.php      # レート制限
│   └── DB.php             # DB 接続（PDO）
├── sql/
│   └── schema.sql         # テーブル定義・初期データ
├── config/
│   ├── config.php         # 環境設定（Git 管理外）
│   └── config.example.php # 設定テンプレート
├── tools/
│   └── reset_password.php # 管理者パスワードリセット（ローカル用）
└── docs/
    ├── spec.md            # システム仕様書
    ├── flow.md            # 処理フロー
    ├── database.md        # DB 設計
    └── admin.md           # 管理画面仕様
```

---

## セットアップ

### 1. 設定ファイルを作成

```bash
cp config/config.example.php config/config.php
```

`config/config.php` を編集して API キーと DB 接続情報を設定する。

```php
define('ANTHROPIC_API_KEY', 'sk-ant-xxxx');
define('DB_HOST', 'localhost');
define('DB_NAME', 'chatbot');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 2. データベースを構築

```sql
mysql -u root -p < sql/schema.sql
```

または phpMyAdmin から `sql/schema.sql` をインポートする。

### 3. Web サーバーに配置

ドキュメントルートにプロジェクトを配置する。  
ローカル開発では XAMPP のシンボリックリンクが便利。

```powershell
New-Item -ItemType SymbolicLink -Path "C:\xampp\htdocs\ai-chat" -Target "D:\path\to\ai-chat"
```

### 4. 管理画面にログイン

```
http://localhost/ai-chat/admin/login.php
```

| 項目 | 初期値 |
|---|---|
| メール | admin@stekwired.jp |
| パスワード | admin123 |

> **本番環境では必ずパスワードを変更すること。**

---

## クライアントへの埋め込み

管理画面でクライアントを追加すると `widget_key` が発行される。  
クライアントサイトの `</body>` 直前に以下を挿入するだけで動作する。

```html
<script src="https://chat.stekwired.jp/widget.js"
  data-key="{widget_key}"></script>
```

---

## 本番デプロイ時の注意事項

- `config/config.php` の `CURLOPT_SSL_VERIFYPEER` を `true` に戻す（現在はローカル開発用に `false`）
- `config/config.php` に `SESSION_TIMEOUT` を追加する（例: `define('SESSION_TIMEOUT', 3600);`）
- `public/chat.php` の `BASE_URL` を本番ドメインに変更
- `public/widget.js` の `BASE_URL` を本番ドメインに変更
- 初期管理者パスワードを変更する（管理画面 → アカウント → パスワード変更）
- 既存 DB の場合は `sql/schema.sql` の ALTER TABLE コメントを実行してカラムを追加する
