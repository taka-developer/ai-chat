<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::logout();
header('Location: /admin/login.php');
exit;
