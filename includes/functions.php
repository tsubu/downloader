<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/security.php';

send_security_headers();
init_locale();

function random_string(int $length): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

function sanitize_display_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
    return mb_substr($name, 0, 255);
}

function get_file_extension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function is_zip_file(string $path): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $header = fread($handle, 4);
    fclose($handle);

    return in_array($header, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
}

function is_mp4_file(string $path): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }

    fseek($handle, 4);
    $brand = fread($handle, 4);
    fclose($handle);

    return $brand === 'ftyp';
}

function parse_ini_size(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }

    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int) $value,
    };
}

function get_server_upload_limit(): int
{
    if (!defined('SERVER_MAX_UPLOAD_SIZE')) {
        return 0;
    }

    $limit = parse_ini_size((string) SERVER_MAX_UPLOAD_SIZE);
    if ($limit <= 0 || $limit >= PHP_INT_MAX) {
        return 0;
    }

    return $limit;
}

function get_upload_size_limits(): array
{
    $limits = [
        parse_ini_size((string) ini_get('upload_max_filesize')),
        parse_ini_size((string) ini_get('post_max_size')),
    ];

    $serverLimit = get_server_upload_limit();
    if ($serverLimit > 0) {
        $limits[] = $serverLimit;
    }

    return $limits;
}

function get_max_upload_size(): int
{
    static $size = null;
    if ($size !== null) {
        return $size;
    }

    $size = min(get_upload_size_limits());

    return $size;
}

function format_max_upload_size(): string
{
    $size = get_max_upload_size();
    if ($size >= PHP_INT_MAX) {
        return __('no_limit');
    }

    if ($size >= 1048576 && $size % 1048576 === 0) {
        return (int) ($size / 1048576) . ' MB';
    }

    return format_bytes($size);
}

function allowed_extensions_label(): string
{
    return 'PDF / Excel / CSV / Zip / MP4';
}

function upload_error_message(int $errorCode): array
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => [
            'key' => 'error_file_size_exceeded',
            'replace' => ['size' => format_max_upload_size()],
        ],
        UPLOAD_ERR_PARTIAL => ['key' => 'error_upload_partial', 'replace' => []],
        UPLOAD_ERR_NO_FILE => ['key' => 'error_upload_no_file', 'replace' => []],
        default => ['key' => 'error_upload_failed', 'replace' => []],
    };
}

function build_display_name(string $displayNameBase, string $fileExtension): string
{
    $baseName = sanitize_display_name($displayNameBase);
    $baseName = pathinfo($baseName, PATHINFO_FILENAME);
    $baseName = sanitize_display_name($baseName);
    $fileExtension = strtolower($fileExtension);

    if ($baseName === '' || $fileExtension === '') {
        return '';
    }

    return $baseName . '.' . $fileExtension;
}

function is_allowed_upload(array $file): ?array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return upload_error_message($errorCode);
    }

    $maxUploadSize = get_max_upload_size();
    if (($file['size'] ?? 0) > $maxUploadSize) {
        return [
            'key' => 'error_file_size_exceeded',
            'replace' => ['size' => format_max_upload_size()],
        ];
    }

    $originalName = $file['name'] ?? '';
    $ext = get_file_extension($originalName);
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [
            'key' => 'error_file_type_not_allowed',
            'replace' => ['types' => allowed_extensions_label()],
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        if ($ext === 'zip' && is_zip_file($file['tmp_name'])) {
            return null;
        }
        if ($ext === 'mp4' && is_mp4_file($file['tmp_name'])) {
            return null;
        }
        return ['key' => 'error_file_content_mismatch', 'replace' => []];
    }

    return null;
}

function generate_unique_stored_name(string $extension): string
{
    do {
        $name = random_string(STORED_NAME_LENGTH) . '.' . $extension;
        $path = STORAGE_DIR . '/' . $name;
    } while (file_exists($path));

    return $name;
}

function generate_unique_token(PDO $pdo): string
{
    do {
        $token = random_string(TOKEN_LENGTH);
        $stmt = $pdo->prepare('SELECT id FROM files WHERE download_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
    } while ($stmt->fetch());

    return $token;
}

function generate_download_password(int $length = DOWNLOAD_PASSWORD_LENGTH): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $maxIndex = strlen($chars) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $maxIndex)];
    }

    return $password;
}

function parse_expiry_date(string $date): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59');
    if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
        return null;
    }

    return $dateTime->format('Y-m-d H:i:s');
}

function is_file_expired(array $file): bool
{
    $expiresAt = $file['expires_at'] ?? null;
    if ($expiresAt === null || $expiresAt === '') {
        return false;
    }

    $expiresTimestamp = strtotime((string) $expiresAt);
    if ($expiresTimestamp === false) {
        return false;
    }

    return $expiresTimestamp < time();
}

function format_expiry_date(?string $expiresAt): string
{
    if ($expiresAt === null || $expiresAt === '') {
        return __('expiry_unlimited');
    }

    return substr($expiresAt, 0, 10) . ' 23:59:59';
}

function format_expiry_date_copy(?string $expiresAt): string
{
    if ($expiresAt === null || $expiresAt === '') {
        return __('expiry_unlimited');
    }

    $datePart = substr($expiresAt, 0, 10);
    $dateTime = DateTime::createFromFormat('Y-m-d', $datePart, new DateTimeZone('Asia/Tokyo'));
    if (!$dateTime) {
        return __('expiry_unlimited');
    }

    if (current_locale() === 'ja') {
        return $dateTime->format('Y年m月d日');
    }

    return $dateTime->format('F j, Y');
}

function build_distribution_copy_text(array $file, string $url): string
{
    $displayName = $file['display_name'];
    $expiryLabel = format_expiry_date_copy($file['expires_at'] ?? null);
    $password = ($file['download_password'] ?? '') !== ''
        ? (string) $file['download_password']
        : __('password_unknown');

    return __(
        'copy_distribution_text',
        [
            'name' => $displayName,
            'expiry' => $expiryLabel,
            'size' => format_bytes((int) ($file['file_size'] ?? 0)),
            'url' => $url,
            'password' => $password,
        ]
    );
}

function expiry_status_class(array $file): string
{
    if (($file['expires_at'] ?? '') === '') {
        return '';
    }

    return is_file_expired($file) ? 'text-expired' : 'text-active';
}

function send_file_download(array $file): void
{
    $storedPath = resolve_storage_path((string) $file['stored_name']);
    if ($storedPath === null) {
        http_response_code(404);
        exit('File not found.');
    }

    $displayName = $file['display_name'];
    $mimeType = $file['mime_type'] ?: 'application/octet-stream';
    $fileSize = filesize($storedPath);
    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $displayName) ?: 'download';

    header('Content-Type: ' . $mimeType);
    header(
        'Content-Disposition: attachment; filename="' . str_replace('"', '', $asciiName)
        . '"; filename*=UTF-8\'\'' . rawurlencode($displayName)
    );
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    readfile($storedPath);
    exit;
}

function get_file_by_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM files WHERE download_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function increment_download_count(PDO $pdo, int $fileId): void
{
    $stmt = $pdo->prepare('UPDATE files SET download_count = download_count + 1 WHERE id = :id');
    $stmt->execute(['id' => $fileId]);
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verify_download_password(string $input, array $file): bool
{
    if (($file['download_password'] ?? '') !== '') {
        return hash_equals((string) $file['download_password'], $input);
    }

    return password_verify($input, $file['password_hash'] ?? '');
}
