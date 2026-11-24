<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <span class="badge rounded-pill text-bg-success-subtle text-success-emphasis mb-3">Moji Kafe Admin</span>
        <h2 class="h3 fw-bold">Selamat datang, <?= sanitize((string) $_SESSION['admin_name']) ?>.</h2>
        <p class="text-secondary mb-0">
            Gunakan navigasi untuk mengelola pesanan, reservasi, pembayaran, dan data kafe.
        </p>
    </div>
</section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
