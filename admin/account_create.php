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

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$locale = LOCALE_AUTO;

$error = validate_admin_account_input($email, $password, $passwordConfirm);
if ($error !== null) {
    set_flash_message($error, 'error');
    header('Location: accounts.php');
    exit;
}

$pdo = get_db();
$error = create_admin_account($pdo, $email, $password, $locale);
if ($error !== null) {
    set_flash_message($error, 'error');
    header('Location: accounts.php');
    exit;
}

set_flash_message('success_account_added');
header('Location: accounts.php');
exit;
