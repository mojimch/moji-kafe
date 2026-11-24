<?php

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = $_SERVER['HTTPS'] ?? '';
    $secure = is_string($https) && !in_array(strtolower($https), ['', '0', 'off'], true);
    $secure = $secure || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

    if (ini_set('session.use_strict_mode', '1') === false
        || ini_set('session.use_only_cookies', '1') === false) {
        throw new RuntimeException('Unable to enable secure session settings.');
    }

    if (!session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ])) {
        throw new RuntimeException('Unable to configure secure session cookies.');
    }

    if (!session_start()) {
        throw new RuntimeException('Unable to start the session.');
    }
}

function sanitize(string|int|float|bool|null $data): string
{
    return htmlspecialchars((string) $data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function product_image_url(?string $fileName): ?string
{
    if (
        $fileName === null
        || preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $fileName) !== 1
    ) {
        return null;
    }

    if (!defined('BASE_URL')) {
        throw new RuntimeException('BASE_URL must be configured before generating product image URLs.');
    }

    return BASE_URL . '/uploads/products/' . rawurlencode($fileName);
}

function format_rupiah(int|float|string $number): string
{
    if (!is_numeric($number)) {
        throw new InvalidArgumentException('The amount must be numeric.');
    }

    $amount = (float) $number;
    if (!is_finite($amount)) {
        throw new InvalidArgumentException('The amount must be finite.');
    }

    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generate_order_code(): string
{
    return sprintf('ORD-%s-%03d', date('Ymd'), random_int(0, 999));
}

function is_valid_order_code(mixed $code): bool
{
    if (
        !is_string($code)
        || preg_match('/\AORD-(\d{4})(\d{2})(\d{2})-\d{3}\z/', $code, $matches) !== 1
    ) {
        return false;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
}

function generate_booking_code(): string
{
    return sprintf('BOOK-%s-%03d', date('Ymd'), random_int(0, 999));
}

function json_response(int $status, string $message, mixed $data = null): never
{
    if ($status < 100 || $status > 599) {
        throw new InvalidArgumentException('The HTTP status code must be between 100 and 599.');
    }

    if (headers_sent()) {
        throw new RuntimeException('Cannot send a JSON response after output has started.');
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function upload_image(array $file, string $target_dir): string
{
    $maxSize = 2 * 1024 * 1024;
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new InvalidArgumentException('Data unggahan tidak valid.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara unggahan tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menyimpan file yang diunggah.',
            UPLOAD_ERR_EXTENSION => 'Ekstensi server menghentikan proses unggah.',
        ];

        throw new RuntimeException($errors[$file['error']] ?? 'Proses unggah file gagal.');
    }

    $temporaryPath = $file['tmp_name'] ?? null;
    $originalName = $file['name'] ?? null;
    if (!is_string($temporaryPath) || !is_string($originalName) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('File yang diunggah tidak valid.');
    }

    if ($target_dir === '') {
        throw new InvalidArgumentException('Folder tujuan unggahan tidak boleh kosong.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset($allowedMimeTypes[$extension])) {
        throw new InvalidArgumentException('Hanya gambar JPG, JPEG, PNG, dan WEBP yang diizinkan.');
    }

    $actualSize = filesize($temporaryPath);
    if ($actualSize === false) {
        throw new RuntimeException('Ukuran file yang diunggah tidak dapat diperiksa.');
    }

    if ($actualSize > $maxSize || (isset($file['size']) && (int) $file['size'] > $maxSize)) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 2 MB.');
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($fileInfo === false) {
        throw new RuntimeException('Tipe file yang diunggah tidak dapat diperiksa.');
    }

    $mimeType = finfo_file($fileInfo, $temporaryPath);
    finfo_close($fileInfo);

    if ($mimeType !== $allowedMimeTypes[$extension]) {
        throw new InvalidArgumentException('Konten file tidak sesuai dengan tipe gambar yang diizinkan.');
    }

    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
        throw new RuntimeException('Folder unggahan gambar tidak dapat dibuat.');
    }

    if (!is_writable($target_dir)) {
        throw new RuntimeException('Folder unggahan gambar tidak dapat ditulisi.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($target_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Gambar yang diunggah tidak dapat disimpan.');
    }

    return $fileName;
}
