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
$locale = (string) ($_POST['locale'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

$pdo = get_db();

if ($adminId <= 0 || !get_admin_by_id($pdo, $adminId)) {
    set_flash_message('error_account_not_found', 'error');
    header('Location: accounts.php');
    exit;
}

if (!update_admin_locale($pdo, $adminId, $locale)) {
    set_flash_message('error_invalid_request', 'error');
    header('Location: accounts.php');
    exit;
}

if ($password !== '' || $passwordConfirm !== '') {
    $error = validate_admin_password_input($password, $passwordConfirm);
    if ($error !== null) {
        set_flash_message($error, 'error');
        header('Location: accounts.php');
        exit;
    }

    if (!update_admin_password($pdo, $adminId, $password)) {
        set_flash_message('error_password_change_failed', 'error');
        header('Location: accounts.php');
        exit;
    }
}

set_flash_message('success_account_updated');
header('Location: accounts.php');
exit;
