# InvestSmart Email OTP Setup

This project sends OTP codes to the user's email address through Gmail SMTP.

## Gmail Account

System sender email:

```text
investsmart.system@gmail.com
```

Use a Gmail **App Password**, not the normal Gmail password.

## Local Setup

1. Copy the example config:

```bash
copy backend\config.local.example.php backend\config.local.php
```

2. Open `backend/config.local.php`.

3. Replace:

```php
define('SMTP_PASSWORD', 'paste_your_16_character_gmail_app_password_here');
```

with the Gmail App Password.

4. Confirm:

```php
define('MAIL_ENABLED', true);
```

5. Start XAMPP Apache and MySQL.

6. Sign in from the frontend. The OTP should be sent to the user's email address.

## Important

Do not push `backend/config.local.php` to GitHub. It is ignored by `.gitignore`.
