<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_setup_completed();
require_admin();

$pdo = get_db();
[$message, $messageType] = pull_flash_message();
$admins = get_all_admins($pdo);
$currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);
$localeOptions = admin_locale_options();

$pageTitle = __('admin_accounts_title');
$activeAdminPage = 'accounts';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <header class="page-header">
        <div>
            <h1 class="page-header__title"><?= h(__('admin_accounts_title')) ?></h1>
            <p class="page-header__sub"><?= h(__('admin_logged_in_as', ['email' => $_SESSION['admin_email'] ?? ''])) ?></p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert alert--<?= h($messageType) ?>" role="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2 class="card__title"><?= h(__('admin_add_account')) ?></h2>
        <form action="account_create.php" method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form__group">
                <label for="email" class="form__label"><?= h(__('admin_email')) ?></label>
                <input type="email" id="email" name="email" class="form__input" required autocomplete="username"
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>

            <div class="form__row form__row--2col">
                <div class="form__group">
                    <label for="password" class="form__label"><?= h(__('admin_password')) ?></label>
                    <input type="password" id="password" name="password" class="form__input" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form__group">
                    <label for="password_confirm" class="form__label"><?= h(__('setup_admin_password_confirm')) ?></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form__input" required minlength="8" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn--primary"><?= h(__('admin_add_account_button')) ?></button>
        </form>
    </section>

    <section class="card">
        <h2 class="card__title"><?= h(__('admin_registered')) ?></h2>

        <?php if (count($admins) === 0): ?>
            <p class="empty-state"><?= h(__('admin_no_accounts')) ?></p>
        <?php else: ?>
            <?php foreach ($admins as $admin): ?>
                <?php $formId = 'account-update-' . (int) $admin['id']; ?>
                <form id="<?= h($formId) ?>" action="account_update.php" method="post" class="account-row-form-hidden">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                </form>
            <?php endforeach; ?>

            <div class="account-grid">
                <div class="account-grid__head">
                    <div class="account-grid__row account-grid__row--labels">
                        <div class="account-grid__cell"><?= h(__('table_email')) ?></div>
                        <div class="account-grid__cell"><?= h(__('table_created')) ?></div>
                        <div class="account-grid__cell account-grid__cell--action"><?= h(__('delete_button')) ?></div>
                    </div>
                    <div class="account-grid__row account-grid__row--labels">
                        <div class="account-grid__cell"><?= h(__('table_password_change')) ?></div>
                        <div class="account-grid__cell"><?= h(__('table_language')) ?></div>
                        <div class="account-grid__cell account-grid__cell--action"><?= h(__('change_button')) ?></div>
                    </div>
                </div>

                <?php foreach ($admins as $admin): ?>
                    <?php
                    $isCurrent = (int) $admin['id'] === $currentAdminId;
                    $adminLocale = normalize_admin_locale((string) ($admin['locale'] ?? LOCALE_AUTO));
                    $formId = 'account-update-' . (int) $admin['id'];
                    ?>
                    <div class="account-record">
                        <div class="account-grid__row">
                            <div class="account-grid__cell" data-label="<?= h(__('table_email')) ?>">
                                <?= h($admin['email']) ?>
                                <?php if ($isCurrent): ?>
                                    <span class="badge badge--self"><?= h(__('badge_self')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="account-grid__cell" data-label="<?= h(__('table_created')) ?>">
                                <?= h($admin['created_at']) ?>
                            </div>
                            <div class="account-grid__cell account-grid__cell--action" data-label="<?= h(__('delete_button')) ?>">
                                <?php if (count($admins) <= 1): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <form action="account_delete.php" method="post"
                                          class="inline-form confirm-delete-form"
                                          data-confirm="<?= h(__('confirm_delete_account')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--sm"><?= h(__('delete_button')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="account-grid__row">
                            <div class="account-grid__cell" data-label="<?= h(__('table_password_change')) ?>">
                                <div class="account-password-fields">
                                    <input type="password" form="<?= h($formId) ?>" name="password"
                                           class="form__input form__input--sm"
                                           placeholder="<?= h(__('placeholder_new_password')) ?>" minlength="8"
                                           autocomplete="new-password">
                                    <input type="password" form="<?= h($formId) ?>" name="password_confirm"
                                           class="form__input form__input--sm"
                                           placeholder="<?= h(__('placeholder_confirm')) ?>" minlength="8"
                                           autocomplete="new-password">
                                </div>
                            </div>
                            <div class="account-grid__cell" data-label="<?= h(__('table_language')) ?>">
                                <select id="<?= h($formId) ?>-locale" form="<?= h($formId) ?>" name="locale"
                                        class="form__input form__input--sm" required>
                                    <?php foreach ($localeOptions as $localeCode): ?>
                                        <option value="<?= h($localeCode) ?>" <?= $localeCode === $adminLocale ? 'selected' : '' ?>>
                                            <?= h(locale_display_name($localeCode)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="account-grid__cell account-grid__cell--action" data-label="<?= h(__('change_button')) ?>">
                                <button type="submit" form="<?= h($formId) ?>" class="btn btn--primary btn--sm">
                                    <?= h(__('change_button')) ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
