# マイルストーン

## 概要

問い合わせチャットウィジェット（STEKWIREDが提供するSaaS）のフェーズ別進捗管理。

---

## 完了済み ✅

### Phase 0: ドキュメント整備
- [x] CLAUDE.md 作成（仕様書）
- [x] MILESTONE.md 作成
- [x] TASKS.md 作成
- [x] WORK_LOG.md 作成

---

## 完了済み（追加）✅

### Phase 1: DB・設定基盤
- [x] sql/schema.sql
- [x] config/config.php
- [x] lib/DB.php

### Phase 2: ライブラリ層
- [x] lib/Claude.php
- [x] lib/FaqSearch.php
- [x] lib/RateLimit.php
- [x] lib/Auth.php

### Phase 3: APIエンドポイント
- [x] api/message.php
- [x] api/stream.php
- [x] api/widget_config.php

### Phase 4: フロントエンド
- [x] public/widget.js
- [x] public/chat.php
- [x] public/assets/chat.css
- [x] public/assets/chat.js

### Phase 5: 管理画面
- [x] admin/login.php / logout.php / _layout.php
- [x] admin/index.php / clients.php / faqs.php / logs.php
- [x] admin/editor/index.php / faqs.php / categories.php

---

## 次のステップ 📋

| # | タスク | 説明 |
|---|--------|------|
| - | 環境構築 | config.php に本番値を設定 |
| - | DB構築 | schema.sql を MySQL に適用 |
| - | 動作確認 | ウィジェット埋め込み・チャット・管理画面 |

---

## 進捗サマリー

```
Phase 0   [██████████] 100% ✅
Phase 1   [██████████] 100% ✅
Phase 2   [██████████] 100% ✅
Phase 3   [██████████] 100% ✅
Phase 4   [██████████] 100% ✅
Phase 5   [██████████] 100% ✅
──────────────────────────────
全体      [██████████] 100% ✅
```

---

*最終更新: 2026-05-02*
