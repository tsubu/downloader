<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_setup_completed();
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    redirect_with_error('', 'error_session_invalid');
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';

if ($token === '' || $password === '') {
    redirect_with_error($token, 'error_password_required');
}

if (!is_valid_download_token($token)) {
    http_response_code(404);
    exit('File not found.');
}

$pdo = get_db();
$file = get_file_by_token($pdo, $token);

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

if (is_file_expired($file)) {
    redirect_with_error($token, 'error_expired');
}

$rateKey = download_rate_limit_key($token);
if (is_rate_limited($pdo, 'download_password', $rateKey, DOWNLOAD_PASSWORD_MAX_ATTEMPTS, DOWNLOAD_PASSWORD_LOCKOUT_SECONDS)) {
    redirect_with_error($token, 'error_rate_limited');
}

if (!verify_download_password($password, $file)) {
    record_rate_limit_failure($pdo, 'download_password', $rateKey);
    redirect_with_error($token, 'error_wrong_password');
}

clear_rate_limit($pdo, 'download_password', $rateKey);
increment_download_count($pdo, (int) $file['id']);
send_file_download($file);

function redirect_with_error(string $token, string $errorKey): void
{
    $params = ['error' => $errorKey];
    if ($token !== '' && is_valid_download_token($token)) {
        $params['token'] = $token;
    }
    header('Location: index.php?' . http_build_query($params));
    exit;
}
