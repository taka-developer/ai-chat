<?php
function adminLayout(string $title, string $body, string $role = 'admin'): void
{
    $navAdmin = '
      <a href="/admin/index.php">ダッシュボード</a>
      <a href="/admin/clients.php">クライアント</a>
      <a href="/admin/faqs.php">FAQ</a>
      <a href="/admin/logs.php">ログ</a>';
    $navEditor = '
      <a href="/admin/editor/index.php">ダッシュボード</a>
      <a href="/admin/editor/faqs.php">FAQ管理</a>
      <a href="/admin/editor/categories.php">カテゴリ</a>';
    $nav = $role === 'admin' ? $navAdmin : $navEditor;
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} — 管理画面</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
    header { background: #1e293b; color: #fff; padding: 0 24px; display: flex; align-items: center; gap: 32px; height: 56px; }
    header .brand { font-weight: 700; font-size: 16px; }
    header nav a { color: #cbd5e1; text-decoration: none; font-size: 14px; padding: 0 8px; }
    header nav a:hover { color: #fff; }
    header .logout { margin-left: auto; }
    header .logout a { color: #94a3b8; font-size: 13px; text-decoration: none; }
    main { max-width: 1100px; margin: 32px auto; padding: 0 24px; }
    h1 { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
    .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 600; }
    td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
    tr:last-child td { border-bottom: none; }
    .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-danger  { background: #ef4444; color: #fff; }
    .btn-danger:hover  { background: #dc2626; }
    .btn-sm { padding: 5px 10px; font-size: 12px; }
    input, textarea, select { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 14px; width: 100%; font-family: inherit; }
    input:focus, textarea:focus, select:focus { outline: none; border-color: #2563eb; }
    label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .form-group { margin-bottom: 16px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .badge-green { background: #dcfce7; color: #16a34a; }
    .badge-gray  { background: #f1f5f9; color: #64748b; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .stat-value { font-size: 32px; font-weight: 700; color: #2563eb; }
    .stat-label { font-size: 13px; color: #64748b; margin-top: 4px; }
    .flash { padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .flash-success { background: #dcfce7; color: #15803d; }
    .flash-error   { background: #fee2e2; color: #dc2626; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px; font-family: monospace; }
  </style>
</head>
<body>
  <header>
    <span class="brand">STEKWIRED Admin</span>
    <nav>{$nav}</nav>
    <div class="logout"><a href="/admin/logout.php">ログアウト</a></div>
  </header>
  <main>
    <h1>{$title}</h1>
    {$body}
  </main>
</body>
</html>
HTML;
}
