<?php
define('ANTHROPIC_API_KEY', 'sk-ant-xxxx');
define('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20251001');

define('DB_HOST', 'localhost');
define('DB_NAME', 'chatbot');
define('DB_USER', 'root');
define('DB_PASS', '');

define('RATE_LIMIT_MAX', 30);       // 1時間あたりの最大リクエスト数
define('RATE_LIMIT_WINDOW', 3600);  // ウィンドウ秒数
define('API_TIMEOUT', 15);          // Claude APIタイムアウト秒数
define('MAX_INPUT_LENGTH', 200);    // ユーザー入力の最大文字数
define('MAX_FAQ_RESULTS', 3);       // Claudeに渡す最大FAQ件数
