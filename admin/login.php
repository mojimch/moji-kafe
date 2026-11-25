<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (is_logged_in()) {
    header('Location: ' . admin_base_url() . '/dashboard.php', true, 302);
    exit;
}

$errorMessage = null;
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.';
    } else {
        $usernameInput = $_POST['username'] ?? null;
        $passwordInput = $_POST['password'] ?? null;
        $username = is_string($usernameInput) ? trim($usernameInput) : '';
        $password = is_string($passwordInput) ? $passwordInput : '';

        if (
            preg_match('/\A.{1,50}\z/us', $username) !== 1
            || $password === ''
            || strlen($password) > 1024
        ) {
            http_response_code(422);
            $errorMessage = 'Username atau password tidak valid.';
        } else {
            try {
                require_once __DIR__ . '/../config/database.php';
                $statement = $pdo->prepare(
                    'SELECT id, name, password FROM admins WHERE username = :username LIMIT 1'
                );
                $statement->execute(['username' => $username]);
                $admin = $statement->fetch();

                $isDefaultSeedPassword = APP_ENV === 'production' && $password === 'admin123';
                if (
                    is_array($admin)
                    && password_verify($password, $admin['password'])
                    && !$isDefaultSeedPassword
                ) {
                    if (!session_regenerate_id(true)) {
                        throw new RuntimeException('Unable to regenerate the admin session ID.');
                    }

                    unset($_SESSION['csrf_token']);
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_name'] = (string) $admin['name'];

                    header('Location: ' . admin_base_url() . '/dashboard.php', true, 302);
                    exit;
                }

                http_response_code(401);
                $errorMessage = 'Username atau password salah.';
            } catch (PDOException $exception) {
                error_log('Admin login database error: ' . $exception->getMessage());
                http_response_code(500);
                $errorMessage = 'Tidak dapat memverifikasi akun saat ini. Periksa koneksi database.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17251f">
    <title>Login Admin | <?= sanitize(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 15%, rgba(226, 190, 133, .2), transparent 30rem),
                linear-gradient(135deg, #f8f6f1, #edf2ee);
        }

        .login-card {
            width: min(100%, 440px);
            border: 1px solid rgba(23, 37, 31, .08);
            border-radius: 1.25rem;
            box-shadow: 0 1.5rem 4rem rgba(23, 37, 31, .12);
        }

        .brand-mark {
            width: 3.25rem;
            height: 3.25rem;
            color: #fff;
            background: #254b3b;
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <div class="login-card bg-white mx-auto p-4 p-sm-5">
            <div class="text-center mb-4">
                <div class="brand-mark rounded-4 d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-cup-hot-fill fs-4" aria-hidden="true"></i>
                </div>
                <p class="text-uppercase small fw-semibold text-success mb-1">Area Admin</p>
                <h1 class="h3 fw-bold mb-2">Selamat datang</h1>
                <p class="text-secondary mb-0">Masuk untuk mengelola <?= sanitize(APP_NAME) ?>.</p>
            </div>

            <?php if ($errorMessage !== null): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill mt-1" aria-hidden="true"></i>
                    <span><?= sanitize($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label fw-medium">Username</label>
                    <input
                        type="text"
                        class="form-control form-control-lg"
                        id="username"
                        name="username"
                        value="<?= sanitize($username) ?>"
                        autocomplete="username"
                        maxlength="50"
                        required
                        autofocus
                    >
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-medium">Password</label>
                    <input
                        type="password"
                        class="form-control form-control-lg"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        maxlength="1024"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                    Masuk
                </button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0">
                <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                Akses khusus pengelola kafe
            </p>
        </div>
    </main>
</body>
</html>
