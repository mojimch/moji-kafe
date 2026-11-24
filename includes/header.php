<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : APP_NAME;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$publicNavigation = [
    ['label' => 'Beranda', 'path' => 'index.php', 'icon' => 'bi-house-door'],
    ['label' => 'Menu', 'path' => 'menu.php', 'icon' => 'bi-cup-straw'],
    ['label' => 'Reservasi Meja', 'path' => 'booking.php', 'icon' => 'bi-calendar2-check'],
    ['label' => 'Cek Pesanan', 'path' => 'check-order.php', 'icon' => 'bi-receipt'],
    ['label' => 'Cek Reservasi', 'path' => 'check-booking.php', 'icon' => 'bi-search'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#244b3b">
    <title><?= sanitize($pageTitle) ?> | <?= sanitize(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= sanitize(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand d-inline-flex align-items-center gap-2 fw-bold" href="<?= sanitize(BASE_URL) ?>/index.php">
                <span class="brand-mark rounded-3 d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-cup-hot-fill" aria-hidden="true"></i>
                </span>
                <span><?= sanitize(APP_NAME) ?></span>
            </a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#customerNavigation"
                aria-controls="customerNavigation"
                aria-expanded="false"
                aria-label="Buka navigasi"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="customerNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($publicNavigation as $item): ?>
                        <?php $isActive = is_string($currentPath) && str_ends_with($currentPath, '/' . $item['path']); ?>
                        <li class="nav-item">
                            <a
                                class="nav-link<?= $isActive ? ' active fw-semibold' : '' ?>"
                                href="<?= sanitize(BASE_URL) ?>/<?= sanitize($item['path']) ?>"
                                <?= $isActive ? 'aria-current="page"' : '' ?>
                            >
                                <i class="bi <?= sanitize($item['icon']) ?> me-1" aria-hidden="true"></i>
                                <?= sanitize($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-success btn-sm position-relative" href="<?= sanitize(BASE_URL) ?>/cart.php">
                            <i class="bi bi-bag me-1" aria-hidden="true"></i>
                            Keranjang
                            <span
                                id="cart-count"
                                class="badge rounded-pill text-bg-warning ms-1"
                                aria-label="Jumlah item dalam keranjang"
                            >0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main id="main-content">
    <div id="app-feedback" class="container pt-3 d-none" role="status" aria-live="polite"></div>
