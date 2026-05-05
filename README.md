# InvestSmart

InvestSmart is a 3-tier financial management web app built with separate frontend assets, a PHP backend API, and a MySQL database.

## Structure

- `public/index.html` - login and registration page
- `public/dashboard.html`, `profile.html`, `my-finances.html`, `investment-calculator.html`, `my-plans.html`, `my-report.html` - regular user pages
- `public/admin-dashboard.html`, `admin-users.html`, `admin-providers.html`, `admin-reports.html` - admin pages
- `public/assets/css/styles.css` - responsive UI styling
- `public/assets/js/api.js` - shared frontend API/helpers
- `public/assets/js/auth.js` - login and registration behavior
- `public/assets/js/user.js` - regular user dashboard, profile, finances, calculator, plans, and report
- `public/assets/js/admin.js` - admin dashboard, users, banks, and reports
- `backend/api/index.php` - JSON API endpoints
- `backend/*.php` - database, auth, helpers, and recommendation logic
- `database/schema.sql` - MySQL schema and seed data

## Setup

1. Create the database and seed data:

```sql
SOURCE C:/Users/Msima/Documents/Codex/2026-05-05/can-you-code/database/schema.sql;
```

2. Update database credentials if needed in:

```text
backend/config.php
```

3. Serve the project from the workspace root:

```bash
php -S localhost:8000
```

4. Open:

```text
http://localhost:8000/public/index.html
```

## Hosted Frontend With Local Backend

If the frontend is hosted online but the PHP backend runs from your machine, `localhost` will not work for other people. You need to expose your local PHP backend with a public HTTPS tunnel such as ngrok or Cloudflare Tunnel.

Backend steps:

1. Run the PHP backend locally from the project root.
2. Expose it using a secure tunnel.
3. Add your hosted frontend URL to `ALLOWED_ORIGINS` in `backend/config.php`.
4. Set `SESSION_SECURE_COOKIES` to `true` in `backend/config.php` when using an HTTPS tunnel.

Frontend steps:

1. Set the API base URL to the public tunnel URL plus `/backend/api/index.php`.
2. You can edit `public/assets/js/env.js`, for example:

```js
window.INVESTSMART_API_BASE = "https://your-tunnel-url.ngrok-free.app/backend/api/index.php";
```

The frontend and backend must both use HTTPS when the frontend is hosted online, otherwise browsers can block the requests as mixed content.

## Demo Admin

- Email: `admin@investsmart.local`
- Password: `password`

## Notes

New normal users are forced into `My Finances` after login until they save gross salary, deductions, monthly expenses, and current savings. Net salary is calculated dynamically in the frontend and persisted by the backend.

The investment calculator follows the PDF guidance: it scores stored banks by risk tolerance, time horizon, liquidity preference, monthly contribution compatibility, and age profile, then returns the best bank recommendation with website and contact details. The user can save that recommendation into `My Plans`.
