<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::logout();
header('Location: /ai-chat/admin/login.php');
exit;
