<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    exit('Metode logout tidak diizinkan.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
}

$_SESSION = [];
$cookieParameters = session_get_cookie_params();

if (ini_get('session.use_cookies')) {
    $cookieCleared = setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookieParameters['path'] !== '' ? $cookieParameters['path'] : '/',
        'domain' => $cookieParameters['domain'],
        'secure' => $cookieParameters['secure'],
        'httponly' => $cookieParameters['httponly'],
        'samesite' => $cookieParameters['samesite'] !== '' ? $cookieParameters['samesite'] : 'Lax',
    ]);

    if (!$cookieCleared) {
        throw new RuntimeException('Unable to clear the admin session cookie.');
    }
}

if (!session_destroy()) {
    throw new RuntimeException('Unable to destroy the admin session.');
}

header('Location: ' . admin_base_url() . '/login.php', true, 302);
exit;
