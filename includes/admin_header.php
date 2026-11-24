<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/csrf.php';

require_admin();

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Dashboard';
$adminBaseUrl = admin_base_url();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminName = (string) ($_SESSION['admin_name'] ?? '');
$navigationItems = [
    ['label' => 'Dashboard', 'path' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
    ['label' => 'Pesanan', 'path' => 'orders/index.php', 'icon' => 'bi-receipt'],
    ['label' => 'Reservasi', 'path' => 'bookings/index.php', 'icon' => 'bi-calendar2-check'],
    ['label' => 'Pembayaran QRIS', 'path' => 'payments/index.php', 'icon' => 'bi-qr-code'],
    ['label' => 'Menu Produk', 'path' => 'products/index.php', 'icon' => 'bi-cup-straw'],
    ['label' => 'Kategori', 'path' => 'categories/index.php', 'icon' => 'bi-grid'],
    ['label' => 'Varian', 'path' => 'variants/index.php', 'icon' => 'bi-sliders'],
    ['label' => 'Meja', 'path' => 'tables/index.php', 'icon' => 'bi-table'],
    ['label' => 'Laporan', 'path' => 'reports/index.php', 'icon' => 'bi-bar-chart-line'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17251f">
    <title><?= sanitize($pageTitle) ?> | <?= sanitize(APP_NAME) ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-ink: #17251f;
            --admin-green: #254b3b;
        }

        body {
            min-height: 100vh;
            background: #f5f7f5;
        }

        .admin-sidebar {
            --bs-offcanvas-width: 17rem;
            background: var(--admin-ink);
            color: #fff;
        }

        @media (min-width: 992px) {
            .admin-sidebar {
                width: 17rem !important;
            }
        }

        .admin-sidebar .offcanvas-body {
            min-height: 100%;
        }

        .admin-brand-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--admin-green);
        }

        .admin-nav-link {
            color: #d7e1da;
            border-radius: .65rem;
        }

        .admin-nav-link:hover,
        .admin-nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .12);
        }

        .admin-nav-link.active {
            box-shadow: inset 3px 0 #d9b780;
        }
    </style>
</head>
<body>
<div class="d-flex min-vh-100">
    <aside
        class="admin-sidebar offcanvas offcanvas-lg offcanvas-start flex-shrink-0"
        tabindex="-1"
        id="adminSidebar"
        aria-labelledby="adminSidebarLabel"
    >
        <div class="offcanvas-header border-bottom border-secondary-subtle">
            <a href="<?= sanitize($adminBaseUrl) ?>/dashboard.php" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <span class="admin-brand-icon rounded-3 d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-cup-hot-fill" aria-hidden="true"></i>
                </span>
                <span id="adminSidebarLabel" class="fw-semibold"><?= sanitize(APP_NAME) ?></span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
        </div>
        <div class="offcanvas-body p-3 d-flex flex-column">
            <p class="text-uppercase small fw-semibold text-secondary mb-2 px-2">Menu Utama</p>
            <nav class="nav nav-pills flex-column gap-1" aria-label="Navigasi admin">
                <?php foreach ($navigationItems as $item): ?>
                    <?php
                    $itemPath = $adminBaseUrl . '/' . $item['path'];
                    $isActive = is_string($currentPath) && str_ends_with($currentPath, '/' . $item['path']);
                    ?>
                    <a
                        href="<?= sanitize($itemPath) ?>"
                        class="admin-nav-link nav-link<?= $isActive ? ' active' : '' ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <i class="bi <?= sanitize($item['icon']) ?> me-2" aria-hidden="true"></i>
                        <?= sanitize($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto pt-4">
                <div class="border-top border-secondary-subtle pt-3 mb-3">
                    <span class="d-block text-white fw-medium"><?= sanitize($adminName) ?></span>
                    <span class="small text-secondary">Administrator</span>
                </div>
                <form method="post" action="<?= sanitize($adminBaseUrl) ?>/logout.php">
                    <?= csrf_field() ?>
                    <button type="submit" class="admin-nav-link nav-link text-start w-100">
                        <i class="bi bi-box-arrow-left me-2" aria-hidden="true"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex-grow-1 min-w-0">
        <header class="navbar bg-white border-bottom px-3 px-lg-4 py-3">
            <div class="d-flex align-items-center gap-3">
                <button
                    type="button"
                    class="btn btn-outline-secondary d-lg-none"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#adminSidebar"
                    aria-controls="adminSidebar"
                    aria-label="Buka menu navigasi"
                >
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
                <div>
                    <span class="text-uppercase text-secondary small fw-semibold">Panel Admin</span>
                    <h1 class="h4 mb-0"><?= sanitize($pageTitle) ?></h1>
                </div>
            </div>
            <span class="badge rounded-pill text-bg-light border d-none d-sm-inline-flex align-items-center gap-2">
                <i class="bi bi-person-circle text-success" aria-hidden="true"></i>
                <?= sanitize($adminName) ?>
            </span>
        </header>
        <main class="container-fluid p-3 p-lg-4">
        <?php render_admin_flash(); ?>
