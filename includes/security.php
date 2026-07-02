<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function send_security_headers(): void
{
    static $sent = false;
    if ($sent || headers_sent()) {
        return;
    }

    $sent = true;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_valid_download_token(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{' . TOKEN_LENGTH . '}$/i', $token);
}

function resolve_storage_path(string $storedName): ?string
{
    $basename = basename($storedName);
    if ($basename !== $storedName) {
        return null;
    }

    if (!preg_match('/^[a-f0-9]{' . STORED_NAME_LENGTH . '}\.[a-z0-9]+$/', $basename)) {
        return null;
    }

    $path = STORAGE_DIR . '/' . $basename;
    if (!is_file($path)) {
        return null;
    }

    $realStorage = realpath(STORAGE_DIR);
    $realPath = realpath($path);
    if ($realStorage === false || $realPath === false) {
        return null;
    }

    if ($realPath !== $realStorage && !str_starts_with($realPath, $realStorage . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $realPath;
}

function login_rate_limit_key(string $email): string
{
    return hash('sha256', client_ip() . '|' . strtolower(trim($email)));
}

function download_rate_limit_key(string $token): string
{
    return hash('sha256', client_ip() . '|' . strtolower($token));
}

function is_rate_limited(PDO $pdo, string $bucket, string $rateKey, int $maxAttempts, int $windowSeconds): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limits
         WHERE bucket = :bucket AND rate_key = :rate_key AND attempted_at >= :cutoff'
    );
    $stmt->execute([
        'bucket' => $bucket,
        'rate_key' => $rateKey,
        'cutoff' => $cutoff,
    ]);

    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

function record_rate_limit_failure(PDO $pdo, string $bucket, string $rateKey): void
{
    $stmt = $pdo->prepare('INSERT INTO rate_limits (bucket, rate_key) VALUES (:bucket, :rate_key)');
    $stmt->execute([
        'bucket' => $bucket,
        'rate_key' => $rateKey,
    ]);

    $cutoff = date('Y-m-d H:i:s', time() - 86400);
    $cleanup = $pdo->prepare('DELETE FROM rate_limits WHERE attempted_at < :cutoff');
    $cleanup->execute(['cutoff' => $cutoff]);
}

function clear_rate_limit(PDO $pdo, string $bucket, string $rateKey): void
{
    $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE bucket = :bucket AND rate_key = :rate_key');
    $stmt->execute([
        'bucket' => $bucket,
        'rate_key' => $rateKey,
    ]);
}

function is_public_download_error_key(string $key): bool
{
    return in_array($key, [
        'error_session_invalid',
        'error_password_required',
        'error_expired',
        'error_wrong_password',
        'error_rate_limited',
    ], true);
}
