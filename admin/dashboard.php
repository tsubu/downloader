<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_setup_completed();
require_admin();

$pdo = get_db();
[$message, $messageType] = pull_flash_message();

$files = $pdo->query('SELECT * FROM files ORDER BY created_at DESC')->fetchAll();

$pageTitle = __('admin_files_title');
$activeAdminPage = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <header class="page-header">
        <div>
            <h1 class="page-header__title"><?= h(__('admin_files_title')) ?></h1>
            <p class="page-header__sub"><?= h(__('admin_logged_in_as', ['email' => $_SESSION['admin_email'] ?? ''])) ?></p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert alert--<?= h($messageType) ?>" role="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2 class="card__title"><?= h(__('admin_upload_title')) ?></h2>
        <form action="upload.php" method="post" enctype="multipart/form-data" class="form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form__group">
                <label for="file" class="form__label"><?= h(__('admin_file_label', ['types' => allowed_extensions_label()])) ?></label>
                <input type="file" id="file" name="file" class="form__input form__input--file" required
                       accept=".pdf,.xlsx,.xls,.csv,.zip,.mp4,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,application/zip,video/mp4">
                <p class="form__hint"><?= h(__('admin_upload_max_hint', ['size' => format_max_upload_size()])) ?></p>
            </div>

            <div class="form__group">
                <label for="display_name" class="form__label"><?= h(__('admin_display_name_label')) ?></label>
                <div class="form__row form__row--2col form__row--display-name">
                    <div class="form__col">
                        <input type="text" id="display_name" name="display_name" class="form__input" required maxlength="250"
                               placeholder="<?= h(__('admin_display_name_placeholder')) ?>"
                               value="<?= h(pathinfo((string) ($_POST['display_name'] ?? ''), PATHINFO_FILENAME)) ?>">
                    </div>
                    <div class="form__col form__col--extension">
                        <span id="display_extension" class="extension-label"><?= h(__('js_extension_unselected')) ?></span>
                    </div>
                </div>
                <p class="form__hint"><?= h(__('admin_extension_auto_hint')) ?></p>
            </div>

            <div class="form__group">
                <label for="expires_at" class="form__label"><?= h(__('admin_expiry_label')) ?></label>
                <div class="form__row form__row--2col">
                    <div class="form__col">
                        <input type="date" id="expires_at" name="expires_at" class="form__input"
                               min="<?= h(date('Y-m-d')) ?>"
                               value="<?= h($_POST['expires_at'] ?? '') ?>"
                               <?= empty($_POST['no_expiry']) ? 'required' : 'disabled' ?>>
                    </div>
                    <div class="form__col form__col--checkbox">
                        <label class="checkbox-label">
                            <input type="checkbox" id="no_expiry" name="no_expiry" value="1"
                                   <?= !empty($_POST['no_expiry']) ? 'checked' : '' ?>>
                            <?= h(__('admin_unlimited')) ?>
                        </label>
                    </div>
                </div>
                <p class="form__hint"><?= h(__('admin_expiry_hint')) ?></p>
            </div>

            <button type="submit" class="btn btn--primary"><?= h(__('admin_upload_button')) ?></button>
        </form>
    </section>

    <section class="card">
        <h2 class="card__title"><?= h(__('admin_uploaded_files')) ?></h2>

        <?php if (count($files) === 0): ?>
            <p class="empty-state"><?= h(__('admin_no_files')) ?></p>
        <?php else: ?>
            <div class="file-grid">
                <div class="file-grid__head">
                    <div class="file-grid__row file-grid__row--labels">
                        <div class="file-grid__cell"><?= h(__('table_display_name')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_url')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_password')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_expiry_short')) ?></div>
                        <div class="file-grid__cell file-grid__cell--action"><?= h(__('copy_button')) ?></div>
                    </div>
                    <div class="file-grid__row file-grid__row--labels file-grid__row--details">
                        <div class="file-grid__cell"><?= h(__('table_created')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_download')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_size')) ?></div>
                        <div class="file-grid__cell"><?= h(__('table_dl_count')) ?></div>
                        <div class="file-grid__cell file-grid__cell--action"><?= h(__('delete_button')) ?></div>
                    </div>
                </div>

                <?php foreach ($files as $file): ?>
                    <?php
                    $url = download_url($file['download_token']);
                    $adminDownloadUrl = 'file_download.php?id=' . (int) $file['id'];
                    $copyText = build_distribution_copy_text($file, $url);
                    $copyTargetId = 'copy-text-' . (int) $file['id'];
                    ?>
                    <div class="file-record">
                        <div class="file-grid__row">
                            <div class="file-grid__cell" data-label="<?= h(__('table_display_name')) ?>">
                                <?= h($file['display_name']) ?>
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_url')) ?>">
                                <textarea id="<?= h($copyTargetId) ?>" class="copy-source" hidden readonly><?= h($copyText) ?></textarea>
                                <input type="text" class="form__input form__input--sm url-cell__input" readonly
                                       value="<?= h($url) ?>">
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_password')) ?>">
                                <?php if (($file['download_password'] ?? '') !== ''): ?>
                                    <code class="password-cell"><?= h($file['download_password']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted"><?= h(__('password_reupload_hint')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_expiry_short')) ?>">
                                <span class="<?= h(expiry_status_class($file)) ?>"><?= h(format_expiry_date($file['expires_at'] ?? null)) ?></span>
                            </div>
                            <div class="file-grid__cell file-grid__cell--action" data-label="<?= h(__('copy_button')) ?>">
                                <button type="button" class="btn btn--ghost btn--sm copy-btn"
                                        data-copy-target="<?= h($copyTargetId) ?>"><?= h(__('copy_button')) ?></button>
                            </div>
                        </div>
                        <div class="file-grid__row file-grid__row--details">
                            <div class="file-grid__cell" data-label="<?= h(__('table_created')) ?>">
                                <?= h($file['created_at']) ?>
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_download')) ?>">
                                <div class="link-cell link-cell--inline">
                                    <a href="<?= h($url) ?>" class="link-btn" target="_blank" rel="noopener"><?= h(__('link_distribution_page')) ?></a>
                                    <a href="<?= h($adminDownloadUrl) ?>" class="link-btn link-btn--admin"><?= h(__('link_file_download')) ?></a>
                                </div>
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_size')) ?>">
                                <?= h(format_bytes((int) $file['file_size'])) ?>
                            </div>
                            <div class="file-grid__cell" data-label="<?= h(__('table_dl_count')) ?>">
                                <?= (int) $file['download_count'] ?>
                            </div>
                            <div class="file-grid__cell file-grid__cell--action" data-label="<?= h(__('delete_button')) ?>">
                                <form action="delete.php" method="post"
                                      class="inline-form confirm-delete-form"
                                      data-confirm="<?= h(__('confirm_delete_file')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                                    <button type="submit" class="btn btn--danger btn--sm"><?= h(__('delete_button')) ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
