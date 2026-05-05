# Hosted Frontend With Backend Running On Your Machine

This project can work with the frontend hosted online and the PHP/MySQL backend running from your laptop or desktop.

The important rule is this:

```text
Other people cannot connect to your localhost.
```

So your backend must be exposed through a public HTTPS tunnel.

## What You Need To Install

Install these on your machine:

1. PHP 8.1 or newer
2. MySQL or MariaDB
3. A tunnel tool, choose one:
   - ngrok
   - Cloudflare Tunnel
4. A database tool, optional but useful:
   - MySQL Workbench
   - HeidiSQL
   - phpMyAdmin

You do not have to use XAMPP or Chocolatey.

## Recommended Simple Setup

Use Laragon if you want the easiest Windows setup without XAMPP.

Laragon gives you:

- PHP
- MySQL or MariaDB
- Local web server
- Database management tools

Then install either ngrok or Cloudflare Tunnel to expose your local backend.

## Backend Setup

1. Import the database:

```sql
SOURCE C:/Users/Msima/Documents/Codex/2026-05-05/can-you-code/database/schema.sql;
```

2. Update database credentials in:

```text
backend/config.php
```

3. Run the backend locally from the project root:

```bash
php -S localhost:8000
```

4. Test locally:

```text
http://localhost:8000/public/index.html
```

## Expose Your Backend Publicly

### Option A: ngrok

Run:

```bash
ngrok http 8000
```

ngrok will give you a public HTTPS URL like:

```text
https://abc123.ngrok-free.app
```

Your API URL becomes:

```text
https://abc123.ngrok-free.app/backend/api/index.php
```

### Option B: Cloudflare Tunnel

Run a tunnel to:

```text
http://localhost:8000
```

Cloudflare will give you a public HTTPS URL. Your API URL will be:

```text
https://your-tunnel-domain/backend/api/index.php
```

## Frontend Setup

Edit:

```text
public/assets/js/env.js
```

Set:

```js
window.INVESTSMART_API_BASE = "https://your-public-backend-url/backend/api/index.php";
```

Then upload only the `public` folder to your frontend host.

## Backend CORS Setup

Edit:

```text
backend/config.php
```

Add your hosted frontend URL:

```php
const ALLOWED_ORIGINS = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'https://your-frontend-site.netlify.app',
];
```

When using an HTTPS tunnel, set:

```php
const SESSION_SECURE_COOKIES = true;
```

This is required so browser login sessions work between your hosted frontend and your tunneled backend.

## Important Notes

- Your computer must stay on.
- Your backend server must stay running.
- Your tunnel must stay running.
- If your tunnel URL changes, update `public/assets/js/env.js` and redeploy the frontend.
- The hosted frontend and backend tunnel should both use HTTPS.
- Do not use `localhost` in the hosted frontend API URL.
