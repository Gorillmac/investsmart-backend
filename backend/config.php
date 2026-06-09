<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

function define_if_missing(string $name, mixed $value): void
{
    if (!defined($name)) {
        define($name, $value);
    }
}

define_if_missing('DB_HOST', '127.0.0.1');
define_if_missing('DB_NAME', 'investsmart');
define_if_missing('DB_USER', 'root');
define_if_missing('DB_PASS', '');
define_if_missing('DB_CHARSET', 'utf8mb4');

define_if_missing('SESSION_NAME', 'investsmart_session');
define_if_missing('APP_KEY', 'investsmart-local-dev-key-change-me');

define_if_missing('APP_NAME', 'InvestSmart');
define_if_missing('MAIL_ENABLED', true);
define_if_missing('MAIL_FROM_EMAIL', 'investsmart.system@gmail.com');
define_if_missing('MAIL_FROM_NAME', 'InvestSmart');
define_if_missing('SMTP_HOST', 'smtp.gmail.com');
define_if_missing('SMTP_PORT', 587);
define_if_missing('SMTP_USERNAME', 'investsmart.system@gmail.com');
define_if_missing('SMTP_PASSWORD', '');
define_if_missing('SMTP_TIMEOUT', 20);

// Add your hosted frontend URL here when deployed, for example:
// 'https://your-investsmart-site.netlify.app'
define_if_missing('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:80',
    'http://127.0.0.1:80',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    'http://localhost:8081',
    'http://127.0.0.1:8081',
    'http://localhost:8090',
    'http://127.0.0.1:8090',
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'https://investsmart-frontend.vercel.app',
]);

// Keep false for plain localhost development. Change to true when serving the backend through an HTTPS tunnel.
define_if_missing('SESSION_SECURE_COOKIES', false);
