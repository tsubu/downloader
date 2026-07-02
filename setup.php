<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_setup_completed()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        exit(__('setup_already_completed'));
    }

    header('Location: ' . app_base_url() . '/admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('error_session_invalid');
    } elseif (admin_accounts_exist()) {
        http_response_code(403);
        exit(__('setup_already_completed'));
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $validationError = validate_admin_account_input($email, $password, $passwordConfirm);
        if ($validationError !== null) {
            $error = $validationError;
        } else {
            if (!is_dir(DATA_DIR)) {
                mkdir(DATA_DIR, 0750, true);
            }
            if (!is_dir(STORAGE_DIR)) {
                mkdir(STORAGE_DIR, 0750, true);
            }

            $pdo = get_db();
            initialize_database($pdo);

            $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash, locale) VALUES (:email, :password_hash, :locale)');
            $stmt->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'locale' => LOCALE_AUTO,
            ]);

            file_put_contents(DATA_DIR . '/setup.lock', date('c') . PHP_EOL);
            chmod(DATA_DIR . '/setup.lock', 0640);

            header('Location: ' . app_base_url() . '/admin/');
            exit;
        }
    }
}

$pageTitle = __('setup_title');
require __DIR__ . '/includes/header.php';
?>

<main class="container container--narrow">
    <section class="card">
        <h1 class="card__title"><?= h(__('setup_title')) ?></h1>
        <p class="card__lead"><?= h(__('setup_lead')) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= h(__($error)) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form__group">
                <label for="email" class="form__label"><?= h(__('setup_admin_email')) ?></label>
                <input type="email" id="email" name="email" class="form__input" required
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>

            <div class="form__group">
                <label for="password" class="form__label"><?= h(__('setup_admin_password')) ?></label>
                <input type="password" id="password" name="password" class="form__input" required minlength="8">
            </div>

            <div class="form__group">
                <label for="password_confirm" class="form__label"><?= h(__('setup_admin_password_confirm')) ?></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form__input" required minlength="8">
            </div>

            <button type="submit" class="btn btn--primary btn--block"><?= h(__('setup_submit')) ?></button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
