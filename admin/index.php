<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_setup_completed();

start_secure_session();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('error_session_invalid');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = __('error_login_required');
        } else {
            $pdo = get_db();
            $rateKey = login_rate_limit_key($email);
            if (is_rate_limited($pdo, 'admin_login', $rateKey, LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_SECONDS)) {
                $error = __('error_rate_limited');
            } elseif (!admin_login($email, $password)) {
                $error = __('error_login_failed');
            } else {
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

$pageTitle = __('admin_login_title');
require __DIR__ . '/../includes/header.php';
?>

<main class="container container--narrow">
    <section class="card">
        <h1 class="card__title"><?= h(__('admin_login_title')) ?></h1>
        <p class="card__lead"><?= h(__('admin_login_lead')) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form__group">
                <label for="email" class="form__label"><?= h(__('admin_email')) ?></label>
                <input type="email" id="email" name="email" class="form__input" required autocomplete="username"
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label for="password" class="form__label"><?= h(__('admin_password')) ?></label>
                <input type="password" id="password" name="password" class="form__input" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn--primary btn--block"><?= h(__('admin_login_button')) ?></button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
