<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_setup_completed();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash_message('error_invalid_request', 'error');
    header('Location: accounts.php');
    exit;
}

$adminId = (int) ($_POST['id'] ?? 0);
$currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);
$pdo = get_db();

$error = delete_admin_account($pdo, $adminId, $currentAdminId);
if ($error !== null) {
    set_flash_message($error, 'error');
    header('Location: accounts.php');
    exit;
}

if ($adminId === $currentAdminId) {
    header('Location: index.php');
    exit;
}

set_flash_message('success_account_deleted');
header('Location: accounts.php');
exit;
