-- 問い合わせチャットウィジェット DBスキーマ

CREATE DATABASE IF NOT EXISTS chatbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chatbot;

-- クライアントテーブル
CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  system_prompt TEXT,
  contact_url VARCHAR(500),
  widget_key VARCHAR(64) UNIQUE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FAQテーブル
CREATE TABLE faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  category VARCHAR(100),
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  keywords TEXT,
  priority INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理ユーザーテーブル
CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,        -- NULLなら管理者（STEKWIRED）、値があれば編集者
  email VARCHAR(200) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'editor') DEFAULT 'editor',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 会話ログテーブル
CREATE TABLE conversation_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  session_id VARCHAR(64),
  user_message TEXT,
  bot_response TEXT,
  matched_faq_ids TEXT,              -- マッチしたFAQのID（JSON配列）
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FAQカテゴリテーブル（編集者が管理）
CREATE TABLE faq_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_client_name (client_id, name),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- レート制限テーブル
CREATE TABLE rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45),
  client_id INT,
  request_count INT DEFAULT 1,
  window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_client (ip_address, client_id),
  INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期管理者アカウント（パスワード: admin123 → 本番環境では必ず変更）
INSERT INTO clients (name, system_prompt, widget_key) VALUES
('STEKWIREDデモ', 'あなたはSTEKWIREDのサポートアシスタントです。丁寧に日本語で回答してください。', 'demo_key_change_in_production');

INSERT INTO admin_users (client_id, email, password_hash, role) VALUES
(NULL, 'admin@stekwired.jp', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
