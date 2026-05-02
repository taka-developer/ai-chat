# 作業ログ

## フォーマット

```markdown
## YYYY-MM-DD

### 作業内容
- 実施したタスク

### 変更ファイル
- path/to/file

### 次のアクション
- 次にやるべきこと

### メモ
- 気づいたこと
```

---

## 2026-05-02

### 作業内容
- プロジェクト開始
- 管理ドキュメント作成（MILESTONE.md / TASKS.md / WORK_LOG.md）

### 変更ファイル
- MILESTONE.md（新規）
- TASKS.md（新規）
- WORK_LOG.md（新規）

### 次のアクション
- Phase 1 開始: sql/schema.sql 作成

### メモ
- CLAUDE.md の仕様書・claude_collab_skill.md のワークフローに従って進める

---

## 2026-05-02（続き）

### 作業内容
- Phase 1〜5 全ファイルを実装（全15ステップ完了）

### 変更ファイル
- sql/schema.sql（faq_categoriesテーブル追加）
- config/config.php
- lib/DB.php / lib/Claude.php / lib/FaqSearch.php / lib/RateLimit.php / lib/Auth.php
- api/message.php / api/stream.php / api/widget_config.php
- public/widget.js / public/chat.php / public/assets/chat.css / public/assets/chat.js
- admin/login.php / admin/logout.php / admin/_layout.php / admin/index.php
- admin/clients.php / admin/faqs.php / admin/logs.php
- admin/editor/index.php / admin/editor/faqs.php / admin/editor/categories.php

### 次のアクション
- config/config.php に実際のAPIキーとDB接続情報を設定
- MySQLで sql/schema.sql を実行してDB構築
- 動作確認

### メモ
- 初期管理者パスワードは `admin123`（本番前に必ず変更）
- widget.js の BASE_URL は本番ドメインに合わせて変更が必要

---

## 2026-05-02（ローカル環境セットアップ・動作確認）

### 作業内容
- XAMPPでローカル環境構築（シンボリックリンク・DB作成・schema.sqlインポート）
- APIキーをconfig.phpに設定、.gitignoreで管理対象外に変更
- config.example.phpをテンプレートとして追加
- チャットUI動作確認・各種バグ修正

### 変更ファイル
- .gitignore（新規）
- config/config.example.php（新規）
- public/chat.php（アセットパス修正・BASE_URL設定）
- public/assets/chat.js（SSEパース修正・コンソールログ追加・トークン使用量表示）
- lib/Claude.php（SSL検証無効化・トークン使用量記録・擬似ストリーミング対応）
- api/stream.php（output buffering無効化・usageイベント追加）

### 解決した問題
- CSSが読み込まれない → アセットパスを相対パスに修正
- SSEが空 → output buffering無効化・set_time_limit(120)追加
- Claude API 404 → モデル名を `claude-haiku-4-5-20251001` に修正
- キーワード抽出が空 → SSL証明書検証を無効化（ローカル開発用）
- デバッグメッセージがチャットに表示 → SSEイベント種別を正しくパース

### 次のアクション
- 管理画面にログインしてFAQを登録
- FAQありの状態でチャットをテスト
- widget.js 埋め込み動作確認

### メモ
- Windows/XAMPPではCURLOPT_WRITEFUNCTIONが不安定なため擬似ストリーミングで代替
- 本番環境では SSL_VERIFYPEER を true に戻すこと
