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
