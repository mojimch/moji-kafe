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
$isDashboard = is_string($currentPath) && str_ends_with($currentPath, '/dashboard.php');
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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .admin-content {
            flex: 1 1 auto;
            min-width: 0;
        }

        .admin-sidebar {
            --bs-offcanvas-width: 17rem;
            background: var(--admin-ink);
            color: #fff;
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            width: min(17rem, 85vw);
            height: 100vh;
            visibility: hidden;
            transform: translateX(-100%);
            transition: transform .3s ease-in-out, visibility .3s ease-in-out;
        }

        .admin-sidebar.show,
        .admin-sidebar.showing {
            visibility: visible;
            transform: translateX(0);
        }

        .admin-sidebar .offcanvas-body {
            overflow-y: auto;
        }

        .admin-sidebar-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1040;
            padding: 0;
            border: 0;
            background: rgba(0, 0, 0, .5);
        }

        .admin-sidebar-backdrop[hidden] {
            display: none !important;
        }

        .admin-menu-toggle {
            display: inline-flex !important;
        }

        @media (min-width: 992px) {
            .admin-sidebar {
                position: static !important;
                flex: 0 0 17rem;
                width: 17rem !important;
                height: auto !important;
                min-height: 100vh;
                visibility: visible !important;
                transform: none !important;
                transition: none !important;
                z-index: auto;
            }

            .admin-sidebar .offcanvas-header {
                display: flex !important;
            }

            .admin-sidebar .btn-close {
                display: none !important;
            }

            .admin-menu-toggle {
                display: none !important;
            }
        }

        @media (min-width: 992px) {
            .admin-sidebar .offcanvas-body {
                min-height: 100%;
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
<div class="d-flex min-vh-100 admin-layout">
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
            <button type="button" class="btn-close btn-close-white d-lg-none admin-sidebar-close" aria-label="Tutup menu"></button>
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
    <button type="button" id="adminSidebarBackdrop" class="admin-sidebar-backdrop" aria-label="Tutup menu navigasi" hidden></button>

    <div class="flex-grow-1 min-w-0 admin-content">
        <header class="navbar bg-white border-bottom px-3 px-lg-4 py-3">
            <div class="d-flex align-items-center gap-3">
                <button
                    type="button"
                    class="btn btn-outline-secondary d-lg-none admin-menu-toggle"
                    aria-controls="adminSidebar"
                    aria-expanded="false"
                    aria-label="Buka menu navigasi"
                >
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                    <span class="ms-1">Menu</span>
                </button>
                <div>
                    <span class="text-uppercase text-secondary small fw-semibold">Panel Admin</span>
                    <h1 class="h4 mb-0"><?= sanitize($pageTitle) ?></h1>
                </div>
            </div>
            <?php if (!$isDashboard): ?>
                <a href="<?= sanitize($adminBaseUrl) ?>/dashboard.php" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-house-door me-1" aria-hidden="true"></i>
                    Dashboard
                </a>
            <?php endif; ?>
            <span class="badge rounded-pill text-bg-light border d-none d-sm-inline-flex align-items-center gap-2">
                <i class="bi bi-person-circle text-success" aria-hidden="true"></i>
                <?= sanitize($adminName) ?>
            </span>
        </header>
        <script>
            (() => {
                const sidebar = document.getElementById('adminSidebar');
                const toggle = document.querySelector('.admin-menu-toggle');
                const closeButton = document.querySelector('.admin-sidebar-close');
                const backdrop = document.getElementById('adminSidebarBackdrop');
                const mobileViewport = window.matchMedia('(max-width: 991.98px)');
                if (!sidebar || !toggle || !closeButton || !backdrop) {
                    return;
                }

                const setMenuOpen = (open) => {
                    const shouldOpen = open && mobileViewport.matches;
                    sidebar.classList.toggle('show', shouldOpen);
                    backdrop.hidden = !shouldOpen;
                    toggle.setAttribute('aria-expanded', String(shouldOpen));
                    if (shouldOpen) {
                        closeButton.focus();
                    } else if (document.activeElement === closeButton) {
                        toggle.focus();
                    }
                };

                toggle.addEventListener('click', () => setMenuOpen(!sidebar.classList.contains('show')));
                closeButton.addEventListener('click', () => setMenuOpen(false));
                backdrop.addEventListener('click', () => setMenuOpen(false));
                sidebar.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (mobileViewport.matches) {
                            setMenuOpen(false);
                        }
                    });
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && sidebar.classList.contains('show')) {
                        setMenuOpen(false);
                    }
                });
                mobileViewport.addEventListener('change', (event) => {
                    if (!event.matches) {
                        setMenuOpen(false);
                    }
                });
            })();
        </script>
        <main class="container-fluid p-3 p-lg-4">
        <?php render_admin_flash(); ?>
