<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function generate_csrf_token(): string
{
    start_secure_session();

    $token = $_SESSION['csrf_token'] ?? null;
    if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function verify_csrf_token(mixed $token): bool
{
    if (!is_string($token)) {
        return false;
    }

    start_secure_session();

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    return is_string($sessionToken)
        && preg_match('/\A[a-f0-9]{64}\z/', $sessionToken) === 1
        && hash_equals($sessionToken, $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generate_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">';
}
