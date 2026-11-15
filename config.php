<?php
declare(strict_types=1);

const APP_NAME = 'EventGrid 3.0';
const BASE_URL = '/event-grid-3.0';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'eventgrid_3_0';
const DB_USER = 'root';
const DB_PASS = '';
const APP_TIMEZONE = 'Asia/Kolkata';
const UPLOAD_DIR = __DIR__ . '/assets/uploads/posters';
const MAX_POSTER_BYTES = 3_000_000;
const EMAIL_ENABLED = false;
const SMTP_HOST = '';
const SMTP_USER = '';
const SMTP_PASS = '';
const SMTP_FROM = '';
const SMTP_PORT = 587;
const LLM_API_KEY = '';
const LLM_API_URL = 'https://api.openai.com/v1/chat/completions';
const LLM_MODEL = 'gpt-4o-mini';

date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}
