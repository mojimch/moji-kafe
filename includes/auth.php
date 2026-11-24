<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/config.php';

function is_logged_in(): bool
{
    start_secure_session();

    $adminId = $_SESSION['admin_id'] ?? null;
    $adminName = $_SESSION['admin_name'] ?? null;
    $validAdminId = (is_int($adminId) && $adminId > 0)
        || (is_string($adminId) && ctype_digit($adminId) && (int) $adminId > 0);

    return $validAdminId && is_string($adminName) && trim($adminName) !== '';
}

function admin_base_url(): string
{
    $basePath = parse_url(BASE_URL, PHP_URL_PATH);
    $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
    if (str_ends_with($basePath, '/public')) {
        $basePath = substr($basePath, 0, -strlen('/public'));
    }

    return rtrim($basePath, '/') . '/admin';
}

function require_admin(): void
{
    if (is_logged_in()) {
        return;
    }

    header('Location: ' . admin_base_url() . '/login.php', true, 302);
    exit;
}
