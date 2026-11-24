<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

function set_admin_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'info', 'warning', 'danger'], true)) {
        throw new InvalidArgumentException('Unsupported admin flash message type.');
    }

    start_secure_session();
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function render_admin_flash(): void
{
    start_secure_session();

    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    if (
        !is_array($flash)
        || !isset($flash['type'], $flash['message'])
        || !is_string($flash['type'])
        || !is_string($flash['message'])
        || !in_array($flash['type'], ['success', 'info', 'warning', 'danger'], true)
    ) {
        return;
    }
    ?>
    <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= sanitize($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
    <?php
}

function admin_parse_positive_int(mixed $value, int $max = PHP_INT_MAX): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false || $parsed < 1 || $parsed > $max) {
        return null;
    }

    return $parsed;
}

function admin_is_valid_price(mixed $value): bool
{
    return is_string($value)
        && preg_match('/\A(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?\z/', trim($value)) === 1;
}

function product_image_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
        . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
}

function product_image_url(?string $fileName): ?string
{
    if (
        $fileName === null
        || preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $fileName) !== 1
    ) {
        return null;
    }

    return BASE_URL . '/uploads/products/' . rawurlencode($fileName);
}

function remove_product_image(?string $fileName): bool
{
    if ($fileName === null || $fileName === '') {
        return true;
    }

    if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $fileName) !== 1) {
        error_log('Skipped removing a product image with an invalid stored filename.');
        return false;
    }

    $path = product_image_directory() . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($path) || unlink($path)) {
        return true;
    }

    error_log('Unable to remove a product image: ' . $path);
    return false;
}

function redirect_admin(string $path): never
{
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
        throw new InvalidArgumentException('Admin redirect path must be relative to the admin area.');
    }

    header('Location: ' . admin_base_url() . '/' . $path, true, 303);
    exit;
}
