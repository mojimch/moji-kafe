<?php

declare(strict_types=1);

$pageTitle = 'Keranjang';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" data-cart-page aria-labelledby="cart-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Pesananmu</p>
        <h1 id="cart-heading" class="display-6 fw-bold mb-2">Keranjang</h1>
        <p class="text-secondary mb-0">Periksa pilihan menu dan jumlahnya sebelum melanjutkan.</p>
    </div>

    <div data-cart-items class="d-grid gap-3" aria-live="polite"></div>

    <div data-cart-empty class="contact-panel p-4 p-lg-5 text-center" hidden>
        <i class="bi bi-bag display-5 text-success" aria-hidden="true"></i>
        <h2 class="h4 fw-bold mt-3">Keranjang masih kosong</h2>
        <p class="text-secondary">Pilih menu favoritmu untuk mulai membuat pesanan.</p>
        <a href="<?= sanitize(BASE_URL) ?>/menu.php" class="btn btn-success">Lihat menu</a>
    </div>

    <section data-cart-summary class="contact-panel p-4 mt-4" aria-label="Ringkasan keranjang" hidden>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="text-secondary small d-block">Estimasi subtotal</span>
                <strong class="h4 text-success mb-0" data-cart-total>Rp 0</strong>
                <p class="small text-secondary mb-0">Harga dan ketersediaan diperiksa ulang saat pesanan dibuat.</p>
            </div>
            <a href="<?= sanitize(BASE_URL) ?>/checkout.php" class="btn btn-success btn-lg" data-cart-checkout>
                Lanjut ke checkout <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </a>
        </div>
    </section>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
