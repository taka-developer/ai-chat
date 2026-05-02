# タスク管理

> **最終更新**: 2026-05-02

---

## 現在の状態

| 項目 | 内容 |
|------|------|
| 現在のPhase | 動作確認・改善 |
| 作業中のタスク | なし |
| 次のタスク | 管理画面でFAQ登録 → チャット再テスト |
| ブロッカー | なし |

---

## 完了済み Phase

| Phase | 内容 | 完了日 |
|-------|------|-------|
| Phase 0 | ドキュメント整備 | 2026-05-02 |

---

## 現在のPhase詳細

### Phase 1: DB・設定基盤

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 1.1 | sql/schema.sql | 🚧 作業中 | |
| 1.2 | config/config.php | 📋 未着手 | |
| 1.3 | lib/DB.php | 📋 未着手 | |

---

## 次のフェーズ

### Phase 2: ライブラリ層

| # | タスク | 説明 |
|---|--------|------|
| 2.1 | lib/Claude.php | Claude API連携（キーワード抽出・回答生成） |
| 2.2 | lib/FaqSearch.php | FAQ検索・スコアリング |
| 2.3 | lib/RateLimit.php | IPベースのレート制限 |
| 2.4 | lib/Auth.php | セッションベース認証 |

### Phase 3: APIエンドポイント

| # | タスク | 説明 |
|---|--------|------|
| 3.1 | api/message.php | メッセージ送受信メインエンドポイント |
| 3.2 | api/stream.php | ストリーミングレスポンス |
| 3.3 | api/widget_config.php | widget_keyからクライアント設定を返す |

### Phase 4: フロントエンド

| # | タスク | 説明 |
|---|--------|------|
| 4.1 | public/widget.js | クライアントサイト埋め込みJS |
| 4.2 | public/chat.php | チャットiframe本体 |
| 4.3 | public/assets/chat.css | チャットUIスタイル |
| 4.4 | public/assets/chat.js | チャットUIロジック |

### Phase 5: 管理画面

| # | タスク | 説明 |
|---|--------|------|
| 5.1 | admin/login.php | ログイン画面 |
| 5.2 | admin/index.php | 管理者ダッシュボード |
| 5.3 | admin/clients.php | クライアント一覧・追加 |
| 5.4 | admin/faqs.php | FAQ管理（管理者） |
| 5.5 | admin/logs.php | 会話ログ確認 |
| 5.6 | admin/editor/index.php | 編集者ダッシュボード |
| 5.7 | admin/editor/faqs.php | FAQ管理（編集者） |
| 5.8 | admin/editor/categories.php | カテゴリ管理 |
