<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('STORAGE_DIR', APP_ROOT . '/storage');
define('DB_PATH', DATA_DIR . '/app.db');
define('LOCALE_DIR', APP_ROOT . '/lang');
define('DEFAULT_LOCALE', 'en');
define('LOCALE_AUTO', 'auto');

define('ALLOWED_EXTENSIONS', ['pdf', 'xlsx', 'xls', 'csv', 'zip', 'mp4']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'application/csv',
    'application/zip',
    'application/x-zip-compressed',
    'application/x-zip',
    'video/mp4',
    'video/x-m4v',
    'application/mp4',
]);

define('TOKEN_LENGTH', 32);
define('STORED_NAME_LENGTH', 40);
define('DOWNLOAD_PASSWORD_LENGTH', 10);

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900);
define('DOWNLOAD_PASSWORD_MAX_ATTEMPTS', 5);
define('DOWNLOAD_PASSWORD_LOCKOUT_SECONDS', 900);
define('SESSION_IDLE_TIMEOUT', 1800);

// サーバー側のアップロード上限（例: '64M', '128M'）。'0' = 未設定。
define('SERVER_MAX_UPLOAD_SIZE', '0');

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function app_base_url(): string
{
    $scheme = is_https_request() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(dirname($script), '/\\');
    if (str_ends_with($base, '/admin')) {
        $base = dirname($base);
    }
    if ($base === '/' || $base === '\\') {
        $base = '';
    }
    return $scheme . '://' . $host . $base;
}

function download_url(string $token): string
{
    return app_base_url() . '/?token=' . urlencode($token);
}

function require_setup_completed(): void
{
    if (!is_setup_completed()) {
        header('Location: ' . app_base_url() . '/setup.php');
        exit;
    }
}
