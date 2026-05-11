# InvestSmart

InvestSmart is a 3-tier financial recommendation and financial management web application built with:

- Frontend: HTML, CSS, and JavaScript
- Backend: PHP
- Database: MySQL / MariaDB

The system includes:

- one shared login page for both users and admins
- financial profile management
- investment bank recommendation logic
- saved plans and printable reports
- admin analytics and exports
- audit trail logging

## Project Structure

- `public/` - frontend pages and assets
- `backend/` - PHP API, authentication, helpers, audit logic, and recommendation service
- `database/schema.sql` - database schema and seed data
- `setup.php` - one-time database installer
- `docs/` - ERD files and report material

## Main Frontend Pages

- `public/index.html`
- `public/dashboard.html`
- `public/profile.html`
- `public/my-finances.html`
- `public/investment-calculator.html`
- `public/my-plans.html`
- `public/my-report.html`
- `public/admin-dashboard.html`
- `public/admin-users.html`
- `public/admin-providers.html`
- `public/admin-reports.html`

## Main Backend Files

- `backend/api/index.php`
- `backend/Auth.php`
- `backend/Audit.php`
- `backend/Database.php`
- `backend/RecommendationService.php`
- `backend/helpers.php`
- `backend/config.php`

## Local Setup With XAMPP

### 1. Clone the repositories

Backend repository into:

```text
C:\xampp\htdocs\investsmart
```

Frontend repository into:

```text
C:\xampp\htdocs\investsmart\public
```

### 2. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the database using the one-time setup file

Open this in the browser:

```text
http://localhost/investsmart/setup.php
```

This will:

- create the `investsmart` database
- create all required tables
- insert seed banks
- insert the admin account

### 4. Open the application

Open:

```text
http://localhost/investsmart/public/index.html
```

### 5. Local API test

Open:

```text
http://localhost/investsmart/backend/api/index.php?action=me
```

Expected response:

```json
{"ok":true,"user":null}
```

## Database Configuration

Database settings are defined in:

```text
backend/config.php
```

Default local XAMPP values:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'investsmart';
const DB_USER = 'root';
const DB_PASS = '';
```

For local XAMPP use, set:

```php
const SESSION_SECURE_COOKIES = false;
```

For HTTPS tunnels such as ngrok, set:

```php
const SESSION_SECURE_COOKIES = true;
```

## Admin Demo Account

- Email: `admin@investsmart.local`
- Password: `password`

## Hosted Frontend With Local Backend

If the frontend is hosted online but the backend runs on your local XAMPP machine, expose the backend with ngrok.

### Steps

1. Start Apache and MySQL in XAMPP
2. Run:

```bash
ngrok http 80
```

3. Copy the HTTPS ngrok URL
4. Update:

```text
public/assets/js/env.js
```

Example:

```js
window.INVESTSMART_API_BASE = "https://your-ngrok-url.ngrok-free.dev/investsmart/backend/api/index.php";
```

5. Add the hosted frontend URL to:

```php
const ALLOWED_ORIGINS = [
    'https://your-frontend-domain.vercel.app',
];
```

## ERD and Documentation

Included in `docs/`:

- `investsmart-erd.drawio.xml`
- `investsmart-erd.png`
- `investsmart-erd.svg`
- `ERD_WRITEUP.md`

## Notes

- Normal users must save financial data before using the calculator, plans, and reports.
- The calculator recommends the best bank using stored bank criteria such as risk, liquidity, horizon, and monthly contribution support.
- Admin users manage banks, users, analytics, reports, and exports.
