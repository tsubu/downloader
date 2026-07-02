<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrate_database($pdo);

    return $pdo;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
    $stmt->execute(['table' => $table]);

    return $stmt->fetch() !== false;
}

function migrate_database(PDO $pdo): void
{
    if (table_exists($pdo, 'files')) {
        $columns = $pdo->query('PRAGMA table_info(files)')->fetchAll();
        $columnNames = array_column($columns, 'name');

        if (!in_array('download_password', $columnNames, true)) {
            $pdo->exec('ALTER TABLE files ADD COLUMN download_password TEXT NOT NULL DEFAULT ""');
        }

        if (!in_array('expires_at', $columnNames, true)) {
            $pdo->exec('ALTER TABLE files ADD COLUMN expires_at TEXT DEFAULT NULL');
        }
    }

    if (table_exists($pdo, 'admins')) {
        $adminColumns = $pdo->query('PRAGMA table_info(admins)')->fetchAll();
        $adminColumnNames = array_column($adminColumns, 'name');

        if (!in_array('locale', $adminColumnNames, true)) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN locale TEXT NOT NULL DEFAULT 'en'");
        }
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bucket TEXT NOT NULL,
            rate_key TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        );

        CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits(bucket, rate_key, attempted_at);
    SQL);
}

function admin_accounts_exist(): bool
{
    if (!is_file(DB_PATH)) {
        return false;
    }

    $pdo = get_db();
    $table = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admins'")->fetch();
    if ($table === false) {
        return false;
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function is_setup_completed(): bool
{
    if (!is_file(DB_PATH)) {
        return false;
    }

    if (is_file(DATA_DIR . '/setup.lock')) {
        return true;
    }

    return admin_accounts_exist();
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            locale TEXT NOT NULL DEFAULT 'en',
            created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            display_name TEXT NOT NULL,
            stored_name TEXT NOT NULL UNIQUE,
            download_token TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            download_password TEXT NOT NULL DEFAULT '',
            expires_at TEXT DEFAULT NULL,
            mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
            file_size INTEGER NOT NULL DEFAULT 0,
            download_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        );

        CREATE INDEX IF NOT EXISTS idx_files_token ON files(download_token);
    SQL);
}
