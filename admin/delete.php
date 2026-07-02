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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT stored_name FROM files WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$file = $stmt->fetch();

if (!$file) {
    set_flash_message('error_file_not_found', 'error');
    header('Location: dashboard.php');
    exit;
}

$storedPath = resolve_storage_path((string) $file['stored_name']);
if ($storedPath !== null) {
    unlink($storedPath);
}

$deleteStmt = $pdo->prepare('DELETE FROM files WHERE id = :id');
$deleteStmt->execute(['id' => $id]);

set_flash_message('success_file_deleted');
header('Location: dashboard.php');
exit;
