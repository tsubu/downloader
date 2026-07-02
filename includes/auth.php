<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_secure_session();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_admin(): void
{
    start_secure_session();

    $timeout = SESSION_IDLE_TIMEOUT;
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $timeout) {
        admin_logout();
        header('Location: index.php');
        exit;
    }

    if (empty($_SESSION['admin_id'])) {
        header('Location: index.php');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function admin_login(string $email, string $password): bool
{
    $pdo = get_db();
    $rateKey = login_rate_limit_key($email);

    $stmt = $pdo->prepare('SELECT id, password_hash, locale FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        record_rate_limit_failure($pdo, 'admin_login', $rateKey);
        return false;
    }

    clear_rate_limit($pdo, 'admin_login', $rateKey);

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_email'] = $email;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    sync_admin_session_locale((string) ($admin['locale'] ?? LOCALE_AUTO));

    return true;
}

function admin_logout(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function get_all_admins(PDO $pdo): array
{
    return $pdo->query('SELECT id, email, locale, created_at FROM admins ORDER BY created_at ASC')->fetchAll();
}

function count_admins(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

function get_admin_by_id(PDO $pdo, int $adminId): ?array
{
    $stmt = $pdo->prepare('SELECT id, email, locale, created_at FROM admins WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function validate_admin_password_input(string $password, string $passwordConfirm): ?string
{
    if (strlen($password) < 8) {
        return 'error_password_min';
    }

    if ($password !== $passwordConfirm) {
        return 'error_password_mismatch';
    }

    return null;
}

function validate_admin_account_input(string $email, string $password, string $passwordConfirm): ?string
{
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'error_invalid_email';
    }

    return validate_admin_password_input($password, $passwordConfirm);
}

function create_admin_account(PDO $pdo, string $email, string $password, string $locale = LOCALE_AUTO): ?string
{
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        return 'error_account_create_failed';
    }

    $insert = $pdo->prepare('INSERT INTO admins (email, password_hash, locale) VALUES (:email, :password_hash, :locale)');
    $insert->execute([
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'locale' => normalize_admin_locale($locale),
    ]);

    return null;
}

function update_admin_password(PDO $pdo, int $adminId, string $password): bool
{
    $stmt = $pdo->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute([
        'id' => $adminId,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return $stmt->rowCount() > 0;
}

function update_admin_locale(PDO $pdo, int $adminId, string $locale): bool
{
    $admin = get_admin_by_id($pdo, $adminId);
    if (!$admin) {
        return false;
    }

    $locale = normalize_admin_locale($locale);
    $currentLocale = normalize_admin_locale((string) ($admin['locale'] ?? LOCALE_AUTO));

    if ($currentLocale === $locale) {
        if ((int) ($_SESSION['admin_id'] ?? 0) === $adminId) {
            sync_admin_session_locale($locale);
        }

        return true;
    }

    $stmt = $pdo->prepare('UPDATE admins SET locale = :locale WHERE id = :id');
    $stmt->execute([
        'id' => $adminId,
        'locale' => $locale,
    ]);

    if ((int) ($_SESSION['admin_id'] ?? 0) === $adminId) {
        sync_admin_session_locale($locale);
    }

    return $stmt->rowCount() > 0;
}

function sync_admin_session_locale(string $locale): void
{
    start_secure_session();
    $_SESSION['admin_locale'] = normalize_admin_locale($locale);

    if (is_locale_auto($_SESSION['admin_locale'])) {
        unset($_SESSION['locale']);
    } else {
        $_SESSION['locale'] = $_SESSION['admin_locale'];
    }

    reset_locale_cache();
}

function delete_admin_account(PDO $pdo, int $adminId, int $currentAdminId): ?string
{
    if ($adminId <= 0) {
        return 'error_account_not_found';
    }

    if (count_admins($pdo) <= 1) {
        return 'error_last_admin_delete';
    }

    if (!get_admin_by_id($pdo, $adminId)) {
        return 'error_account_not_found';
    }

    $stmt = $pdo->prepare('DELETE FROM admins WHERE id = :id');
    $stmt->execute(['id' => $adminId]);

    if ($stmt->rowCount() === 0) {
        return 'error_account_delete_failed';
    }

    if ($adminId === $currentAdminId) {
        admin_logout();
    }

    return null;
}

function set_flash_message(string $key, string $type = 'success', array $replace = []): void
{
    start_secure_session();
    $_SESSION['flash_message_key'] = $key;
    $_SESSION['flash_message_replace'] = $replace;
    $_SESSION['flash_type'] = $type;
}

function pull_flash_message(): array
{
    start_secure_session();
    $key = $_SESSION['flash_message_key'] ?? '';
    $replace = $_SESSION['flash_message_replace'] ?? [];
    $type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message_key'], $_SESSION['flash_message_replace'], $_SESSION['flash_type']);

    $message = $key !== '' ? __($key, is_array($replace) ? $replace : []) : '';

    return [$message, $type];
}
