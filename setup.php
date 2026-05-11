<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/config.php';

function render_setup_page(string $message, bool $success = true): void
{
    $title = $success ? 'InvestSmart Setup Complete' : 'InvestSmart Setup Failed';
    $color = $success ? '#1f8f5f' : '#bf3b3b';
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    body { margin: 0; font-family: Segoe UI, Arial, sans-serif; background: #f5f7fb; color: #18212f; }
    .wrap { max-width: 780px; margin: 56px auto; background: #fff; border: 1px solid #dfe5ec; border-radius: 10px; padding: 32px; box-shadow: 0 16px 40px rgba(24,33,47,.08); }
    h1 { margin: 0 0 14px; color: {$color}; }
    p { line-height: 1.6; margin: 0 0 12px; }
    code { background: #f5f7fb; padding: 2px 6px; border-radius: 6px; }
    pre { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow: auto; white-space: pre-wrap; }
    .actions { margin-top: 22px; display: flex; gap: 12px; flex-wrap: wrap; }
    a { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 8px; text-decoration: none; font-weight: 700; }
    .primary { background: #0f8b8d; color: #fff; }
    .secondary { background: #e8edf3; color: #18212f; }
  </style>
</head>
<body>
  <main class="wrap">
    <h1>{$title}</h1>
    {$message}
  </main>
</body>
</html>
HTML;
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $schemaPath = __DIR__ . '/database/schema.sql';
    if (!is_file($schemaPath)) {
        render_setup_page('<p>The schema file <code>database/schema.sql</code> was not found.</p>', false);
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        render_setup_page('<p>The schema file could not be read.</p>', false);
    }

    $pdo->exec($sql);

    render_setup_page(
        '<p>The InvestSmart database, tables, seed banks, and admin account were created successfully.</p>
        <p>You can now open the application at <code>http://localhost/investsmart/public/index.html</code>.</p>
        <div class="actions">
          <a class="primary" href="public/index.html">Open InvestSmart</a>
          <a class="secondary" href="http://localhost/phpmyadmin/" target="_blank" rel="noreferrer">Open phpMyAdmin</a>
        </div>
        <p style="margin-top:20px;">For safety, remove or disable <code>setup.php</code> before production deployment.</p>'
    );
} catch (Throwable $exception) {
    $error = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    render_setup_page("<p>Database setup failed.</p><pre>{$error}</pre>", false);
}
