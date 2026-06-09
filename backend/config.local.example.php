<?php
declare(strict_types=1);

// Copy this file to backend/config.local.php on the machine running XAMPP.
// Do not push backend/config.local.php to GitHub because it contains the Gmail app password.

define('MAIL_ENABLED', true);

define('MAIL_FROM_EMAIL', 'investsmart.system@gmail.com');
define('MAIL_FROM_NAME', 'InvestSmart');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'investsmart.system@gmail.com');
define('SMTP_PASSWORD', 'paste_your_16_character_gmail_app_password_here');
define('SMTP_TIMEOUT', 20);
