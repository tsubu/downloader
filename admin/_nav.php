<?php
declare(strict_types=1);

if (!isset($activeAdminPage)) {
    $activeAdminPage = '';
}
?>
<nav class="admin-nav" aria-label="<?= h(__('nav_admin_menu')) ?>">
    <a href="dashboard.php" class="admin-nav__link<?= $activeAdminPage === 'dashboard' ? ' is-active' : '' ?>"><?= h(__('nav_files')) ?></a>
    <a href="accounts.php" class="admin-nav__link<?= $activeAdminPage === 'accounts' ? ' is-active' : '' ?>"><?= h(__('nav_accounts')) ?></a>
    <form action="logout.php" method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="btn btn--ghost"><?= h(__('nav_logout')) ?></button>
    </form>
</nav>
