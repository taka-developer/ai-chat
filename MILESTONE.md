# マイルストーン

## 概要

問い合わせチャットウィジェット（STEKWIREDが提供するSaaS）のフェーズ別進捗管理。

---

## 完了済み ✅

### Phase 0: ドキュメント整備
- [x] CLAUDE.md 作成（仕様書）
- [x] MILESTONE.md 作成
- [x] TASKS.md 作成

---

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

### Phase 5: 管理画面（基本）
- [x] admin/login.php / logout.php / _layout.php
- [x] admin/index.php / clients.php / faqs.php / logs.php
- [x] admin/editor/index.php / faqs.php / categories.php

---

### ローカル環境セットアップ・動作確認
- [x] XAMPPセットアップ（シンボリックリンク・DB・schema.sql）
- [x] APIキー設定・.gitignore管理
- [x] チャットUI動作確認
- [x] Claude API連携確認（モデル名修正・SSL対応）
- [x] SSEストリーミング動作確認
- [x] コンソールログ・トークン使用量表示

---

### Phase 6: 管理画面（追加実装）
- [x] FAQ CSV一括インポート（lib/CsvImporter.php）
- [x] FAQ編集機能（インライン編集）
- [x] クライアント情報編集機能
- [x] ユーザー管理（admin/users.php）
- [x] パスワード変更（admin/profile.php）
- [x] ログイン失敗ロック（5回失敗 → 30分ロック）
- [x] セッションタイムアウト（SESSION_TIMEOUT 設定）

---

### Phase 7: ドキュメント整備
- [x] docs/technical.md — 技術仕様書（クラス・API・定数・環境構築手順）
- [x] docs/test.md — テスト仕様書（全テストケース・合否判定基準）
- [x] docs/manual_admin.md — 管理者マニュアル（STEKWIRED 運用担当者向け）
- [x] docs/manual_editor.md — 編集者マニュアル（クライアント担当者向け）

---

### Phase 8: 検証・テスト
- [x] コードレビューによる静的バグ検証
- [x] 既知バグ修正（なし）
- [ ] ブラウザでの手動テスト（docs/test.md 参照・環境で実施）

---

### Phase 9: 本番デプロイ準備
- [x] SSL 設定変更（CURLOPT_SSL_VERIFYPEER / CURLOPT_SSL_VERIFYHOST を本番用に修正）
- [x] BASE_URL 変更（public/chat.php）
- [ ] 本番サーバーへのファイル配置
- [ ] 初期パスワード変更
- [ ] 本番環境での動作確認（最低限 C-01〜C-03 / A-01 / W-01〜W-02）

---

## 進捗サマリー

```
Phase 0   [██████████] 100% ✅
Phase 1   [██████████] 100% ✅
Phase 2   [██████████] 100% ✅
Phase 3   [██████████] 100% ✅
Phase 4   [██████████] 100% ✅
Phase 5   [██████████] 100% ✅
Phase 6   [██████████] 100% ✅
Phase 7   [██████████] 100% ✅
Phase 8   [████████░░]  80% 🔄（手動テストが残り）
Phase 9   [████████░░]  80% 🔄（本番配置・確認が残り）
──────────────────────────────
全体      [█████████░]  90% 🔄（本番デプロイ前）
```

---

*最終更新: 2026-05-03*
