<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'investsmart';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

const SESSION_NAME = 'investsmart_session';
const APP_KEY = 'investsmart-local-dev-key-change-me';

// Add your hosted frontend URL here when deployed, for example:
// 'https://your-investsmart-site.netlify.app'
const ALLOWED_ORIGINS = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'https://investsmart-frontend.vercel.app',
];

// Use true when the backend is exposed through an HTTPS tunnel such as ngrok or Cloudflare Tunnel.
const SESSION_SECURE_COOKIES = true;
