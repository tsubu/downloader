<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_setup_completed();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash_message('error_invalid_request', 'error');
    header('Location: dashboard.php');
    exit;
}

$displayName = sanitize_display_name($_POST['display_name'] ?? '');
$noExpiry = isset($_POST['no_expiry']) && $_POST['no_expiry'] === '1';
$expiryDate = trim($_POST['expires_at'] ?? '');
$file = $_FILES['file'] ?? null;

if ($displayName === '') {
    set_flash_message('error_display_name_required', 'error');
    header('Location: dashboard.php');
    exit;
}

if ($noExpiry) {
    $expiresAt = null;
} else {
    $expiresAt = parse_expiry_date($expiryDate);
    if ($expiresAt === null) {
        set_flash_message('error_expiry_invalid', 'error');
        header('Location: dashboard.php');
        exit;
    }

    if (strtotime($expiresAt) < time()) {
        set_flash_message('error_expiry_past', 'error');
        header('Location: dashboard.php');
        exit;
    }
}

if (!is_array($file)) {
    set_flash_message('error_file_required', 'error');
    header('Location: dashboard.php');
    exit;
}

$uploadError = is_allowed_upload($file);
if ($uploadError !== null) {
    set_flash_message($uploadError['key'], 'error', $uploadError['replace']);
    header('Location: dashboard.php');
    exit;
}

$uploadExt = get_file_extension($file['name']);
$displayName = build_display_name($displayName, $uploadExt);
if ($displayName === '') {
    set_flash_message('error_display_name_required', 'error');
    header('Location: dashboard.php');
    exit;
}

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0750, true);
}

$pdo = get_db();
$storedName = generate_unique_stored_name($uploadExt);
$token = generate_unique_token($pdo);
$downloadPassword = generate_download_password();
$storedPath = STORAGE_DIR . '/' . $storedName;

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
    set_flash_message('error_file_save_failed', 'error');
    header('Location: dashboard.php');
    exit;
}

chmod($storedPath, 0640);

try {
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO files (
            display_name, stored_name, download_token, password_hash, download_password,
            expires_at, mime_type, file_size
        )
        VALUES (
            :display_name, :stored_name, :download_token, :password_hash, :download_password,
            :expires_at, :mime_type, :file_size
        )
    SQL);
    $stmt->execute([
        'display_name' => $displayName,
        'stored_name' => $storedName,
        'download_token' => $token,
        'password_hash' => password_hash($downloadPassword, PASSWORD_DEFAULT),
        'download_password' => $downloadPassword,
        'expires_at' => $expiresAt,
        'mime_type' => $mimeType,
        'file_size' => (int) $file['size'],
    ]);
} catch (Throwable $e) {
    @unlink($storedPath);
    set_flash_message('error_db_insert_failed', 'error');
    header('Location: dashboard.php');
    exit;
}

set_flash_message('success_upload', 'success', [
    'url' => download_url($token),
    'password' => $downloadPassword,
    'expiry' => format_expiry_date($expiresAt),
]);
header('Location: dashboard.php');
exit;
