<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_setup_completed();
start_secure_session();

$token = trim($_GET['token'] ?? '');
$error = '';
$file = null;
$formError = '';

if ($token !== '') {
    if (!is_valid_download_token($token)) {
        $error = __('error_invalid_url');
    } else {
        $pdo = get_db();
        $file = get_file_by_token($pdo, $token);
        if (!$file) {
            $error = __('error_invalid_url');
        } elseif (is_file_expired($file)) {
            $error = __('error_expired');
        }
    }
}

$errorKey = (string) ($_GET['error'] ?? '');
if ($errorKey !== '' && is_public_download_error_key($errorKey)) {
    $formError = __($errorKey);
}

$pageTitle = __('download_title');
require __DIR__ . '/includes/header.php';
?>

<main class="container container--narrow">
    <section class="card">
        <h1 class="card__title"><?= h(__('download_title')) ?></h1>

        <?php if ($token === ''): ?>
            <p class="card__lead"><?= h(__('download_access_via_url')) ?></p>
            <div class="alert alert--info" role="alert"><?= h(__('download_token_required')) ?></div>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert--error" role="alert"><?= h($error) ?></div>
        <?php else: ?>
            <p class="card__lead"><?= h(__('download_enter_password_lead')) ?></p>

            <?php if ($formError !== ''): ?>
                <div class="alert alert--error" role="alert"><?= h($formError) ?></div>
            <?php endif; ?>

            <form action="download.php" method="post" class="form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div class="form__group">
                    <label for="password" class="form__label"><?= h(__('download_password_label')) ?></label>
                    <input type="password" id="password" name="password" class="form__input" required autocomplete="off">
                </div>

                <button type="submit" class="btn btn--primary btn--block"><?= h(__('download_button')) ?></button>
            </form>

            <p class="form__hint form__hint--center"><?= h(__('download_filename_auto_hint')) ?></p>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
