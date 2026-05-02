# CHANGELOG

---

## [0.3.0] - 2026-05-02

### Added

- **FAQ CSV インポート機能** — `lib/CsvImporter.php` を新規作成。管理者・編集者の FAQ 管理ページから CSV ファイルを一括インポート可能
  - 日本語ヘッダー対応（`カテゴリ` / `質問` / `回答` / `キーワード` / `優先度`）
  - UTF-8 BOM 自動除去
  - キーワード列が空の行は Claude API で自動生成（オプション）
  - インポート結果（成功件数・スキップ件数・行別エラー）をページに表示
- **サンプル CSV テンプレート** — `admin/sample_faq.csv` を追加（FAQ 管理ページからダウンロード可能）
- **管理ツール** — `tools/reset_password.php`（管理者パスワードのリセット用スクリプト）
- **仕様書** — `docs/spec.md` を追加

### Fixed

- **管理画面のリダイレクト・ナビリンクが 404 になる** — XAMPP サブディレクトリ配置（`/ai-chat/`）に合わせてパスを全修正
  - `admin/login.php` / `admin/logout.php` のリダイレクト先
  - `lib/Auth.php` の `requireAdmin()` / `requireEditor()` のリダイレクト先
  - `admin/_layout.php` のナビゲーションリンク・ログアウトリンク

---

## [0.2.0] - 2026-05-02

### Fixed

- **Claude API 404 エラー** — モデル名を `claude-sonnet-4-5-20251001`（廃止）から `claude-haiku-4-5-20251001` に変更
- **SSE が空になる問題** — `stream.php` 先頭で output buffering を無効化（`ob_end_clean` + `set_time_limit(120)`）
- **チャット画面の CSS が読み込まれない** — `chat.php` のアセットパスを絶対パスから相対パスに修正
- **キーワード抽出が空になる** — Windows/XAMPP 環境で curl の SSL 証明書検証が失敗していたため `CURLOPT_SSL_VERIFYPEER` を無効化（ローカル開発用）
- **デバッグメッセージがチャットに表示される** — `chat.js` の SSE パースロジックを修正し、イベント種別（`chunk` / `error` / `usage` / `done`）を正しく識別するよう変更

### Changed

- **擬似ストリーミング対応** — Windows/XAMPP 環境で `CURLOPT_WRITEFUNCTION` が不安定なため、回答生成を全文取得後に句読点・5文字単位で分割送信する擬似ストリーミングに変更（`Claude::streamAnswer()`）
- **BASE_URL の設定** — `chat.php` に `window.BASE_URL = '/ai-chat'` を追加してサブディレクトリ配置に対応

### Added

- **コンソールログ** — `chat.js` に処理の各ステップ（送信・ストリーミング開始/完了・イベント受信・エラー）のログを追加
- **トークン使用量の記録と表示** — `Claude::request()` で API レスポンスの `usage` を記録し、`stream.php` が `usage` SSE イベントで送信。`chat.js` がコンソールにグループ表示（API 呼び出しごと＋合計）
- **Claude API 呼び出しのラベル化** — `request()` に `$label` 引数を追加し、使用量ログにどのメソッドからの呼び出しか記録

---

## [0.1.0] - 2026-05-02

### Added

- プロジェクト初期実装（全 Phase 完了）
- **DB 基盤** — `sql/schema.sql`（clients / faqs / faq_categories / admin_users / conversation_logs / rate_limits）
- **設定ファイル** — `config/config.php`（Git 管理外）・`config/config.example.php`（テンプレート）・`.gitignore`
- **ライブラリ層**
  - `lib/DB.php` — PDO シングルトン接続
  - `lib/Claude.php` — Claude API クライアント（キーワード抽出・回答生成・FAQ キーワード自動生成）
  - `lib/FaqSearch.php` — FAQ キーワード検索・スコアリング（上位 3 件取得）
  - `lib/RateLimit.php` — IP × クライアント単位の 30 回/時レート制限
  - `lib/Auth.php` — セッションベース認証（管理者 / 編集者ロール）
- **API エンドポイント**
  - `api/stream.php` — SSE ストリーミングレスポンス（メインエンドポイント）
  - `api/message.php` — 非ストリーミング版メッセージ送受信
  - `api/widget_config.php` — widget_key からクライアント名を返す
- **チャット UI**
  - `public/widget.js` — クライアントサイト埋め込みボタン + iframe
  - `public/chat.php` — チャット iframe 本体
  - `public/assets/chat.css` — チャット UI スタイル
  - `public/assets/chat.js` — SSE 受信・メッセージ表示・サジェスト・フィードバック
- **管理画面（管理者）**
  - `admin/login.php` / `admin/logout.php`
  - `admin/_layout.php` — 共通レイアウト
  - `admin/index.php` — ダッシュボード
  - `admin/clients.php` — クライアント一覧・追加・削除
  - `admin/faqs.php` — FAQ 管理（全クライアント横断）
  - `admin/logs.php` — 会話ログ（クライアントフィルタ・ページネーション付き）
- **管理画面（編集者）**
  - `admin/editor/index.php` — ダッシュボード
  - `admin/editor/faqs.php` — 自社 FAQ 管理
  - `admin/editor/categories.php` — カテゴリ管理
- **プロジェクト管理ドキュメント** — `MILESTONE.md` / `TASKS.md`
