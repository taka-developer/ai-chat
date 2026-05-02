# データベース設計

データベース名：`chatbot`  
文字コード：`utf8mb4` / 照合順序：`utf8mb4_unicode_ci`

---

## テーブル一覧

| テーブル名 | 用途 |
|---|---|
| `clients` | クライアント情報 |
| `faqs` | FAQ データ |
| `faq_categories` | FAQ カテゴリ（編集者が管理） |
| `admin_users` | 管理ユーザー |
| `conversation_logs` | 会話ログ |
| `rate_limits` | レート制限カウンター |

---

## clients

クライアント（導入企業）ごとの設定を管理する。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | クライアント ID |
| name | VARCHAR(200) NOT NULL | 会社名 |
| system_prompt | TEXT | ボットの口調・制約をClaudeに渡すプロンプト |
| contact_url | VARCHAR(500) | フォールバック時の問い合わせ先 URL |
| widget_key | VARCHAR(64) UNIQUE NOT NULL | 埋め込み用キー（`bin2hex(random_bytes(32))` で生成） |
| created_at | DATETIME | 登録日時 |

---

## faqs

FAQ 本体。クライアントごとに管理される。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | FAQ ID |
| client_id | INT FK → clients.id | 所属クライアント |
| category | VARCHAR(100) | カテゴリ名（例：料金・アクセス） |
| question | TEXT NOT NULL | 質問文 |
| answer | TEXT NOT NULL | 回答文 |
| keywords | TEXT | 検索用キーワード（カンマ区切り）。空の場合は Claude API が自動生成 |
| priority | INT DEFAULT 0 | 優先度。高いほど検索上位に表示される |
| is_active | TINYINT(1) DEFAULT 1 | 公開フラグ（1: 公開 / 0: 非公開） |
| created_at | DATETIME | 作成日時 |
| updated_at | DATETIME | 更新日時（ON UPDATE CURRENT_TIMESTAMP） |

**インデックス**：`client_id`（外部キー）

**スコアリング優先順位**：スコア降順 → `priority` 降順 → `updated_at` 降順

---

## faq_categories

編集者がカテゴリを管理するためのテーブル。  
`faqs.category` に入力候補を提供する。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | カテゴリ ID |
| client_id | INT FK → clients.id | 所属クライアント |
| name | VARCHAR(100) NOT NULL | カテゴリ名 |

**ユニーク制約**：`(client_id, name)`

---

## admin_users

管理画面のユーザーアカウント。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | ユーザー ID |
| client_id | INT DEFAULT NULL | NULL = 管理者（STEKWIRED）/ 値あり = 編集者（クライアント） |
| email | VARCHAR(200) UNIQUE NOT NULL | メールアドレス（ログイン ID） |
| password_hash | VARCHAR(255) NOT NULL | `password_hash()` でハッシュ化 |
| role | ENUM('admin','editor') DEFAULT 'editor' | ロール |
| created_at | DATETIME | 登録日時 |

**ロール別アクセス制御**

| role | client_id | アクセス範囲 |
|---|---|---|
| admin | NULL | 全クライアントのデータ |
| editor | クライアント ID | 自社 client_id のデータのみ |

---

## conversation_logs

チャットの会話履歴を保存する。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | ログ ID |
| client_id | INT FK → clients.id | 発生クライアント |
| session_id | VARCHAR(64) | ブラウザセッション ID |
| user_message | TEXT | ユーザーの入力文 |
| bot_response | TEXT | ボットの回答文（全文） |
| matched_faq_ids | TEXT | ヒットした FAQ の ID（JSON 配列例：`[1, 3]`） |
| created_at | DATETIME | 発生日時 |

---

## rate_limits

IP アドレスごとのリクエスト数を管理し、レート制限を実施する。

| カラム | 型 | 説明 |
|---|---|---|
| id | INT AUTO_INCREMENT PK | レコード ID |
| ip_address | VARCHAR(45) | クライアント IP（IPv6 対応） |
| client_id | INT | 対象クライアント ID |
| request_count | INT DEFAULT 1 | ウィンドウ内のリクエスト数 |
| window_start | DATETIME | カウント開始日時 |

**インデックス**：`(ip_address, client_id)`、`window_start`

**レート制限仕様**：30 回 / 1 時間。期限切れレコードは次回リクエスト時に自動削除。

---

## ER 図（簡略）

```
clients ─────┬──── faqs
             │       └── faq_categories
             ├──── admin_users
             └──── conversation_logs

rate_limits（clients との外部キーなし・独立管理）
```

---

## 初期データ

`schema.sql` に含まれるシードデータ。

```sql
-- デモクライアント
INSERT INTO clients (name, system_prompt, widget_key) VALUES
('STEKWIREDデモ', 'あなたはSTEKWIREDのサポートアシスタントです。', 'demo_key_change_in_production');

-- 初期管理者（パスワード: admin123）
INSERT INTO admin_users (client_id, email, password_hash, role) VALUES
(NULL, 'admin@stekwired.jp', '$2y$10$...', 'admin');
```

> **本番環境では必ずパスワードを変更すること。**
