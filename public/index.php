<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$featuredQuery = $pdo->query(
    'SELECT p.id, p.name, p.description, p.price, p.image, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.is_available = 1
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 3'
);
$featuredProducts = $featuredQuery->fetchAll();

$phoneHref = preg_replace('/[^0-9+]/', '', CAFE_PHONE);
$phoneIsValid = is_string($phoneHref) && preg_match('/\A\+?[0-9]{5,15}\z/', $phoneHref) === 1;
$pageTitle = 'Beranda';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5">
    <div class="hero-panel p-4 p-md-5">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-12 col-lg-7">
                <div class="hero-content">
                    <p class="hero-kicker text-uppercase small fw-semibold mb-3">Tempat singgah, rasa yang berkesan</p>
                    <h1 class="hero-title fw-bold mb-4">Nikmati momen hangat di <?= sanitize(APP_NAME) ?>.</h1>
                    <p class="lead text-white-50 mb-4">
                        Temukan sajian favorit, pilih untuk dinikmati di tempat atau dibawa pulang, lalu pesan dengan mudah.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= sanitize(BASE_URL) ?>/menu.php" class="btn btn-warning btn-lg fw-semibold">
                            Lihat menu <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                        <a href="<?= sanitize(BASE_URL) ?>/menu.php" class="btn btn-outline-light btn-lg">Pesan sekarang</a>
                        <a href="<?= sanitize(BASE_URL) ?>/booking.php" class="btn btn-light btn-lg text-success fw-semibold">
                            Reservasi meja
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="hero-illustration" aria-hidden="true">
                    <i class="bi bi-cup-hot-fill"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-4 py-lg-5" aria-labelledby="featured-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="section-kicker text-uppercase small fw-semibold mb-2">Pilihan untukmu</p>
            <h2 id="featured-heading" class="h2 fw-bold mb-0">Rekomendasi menu</h2>
        </div>
        <a href="<?= sanitize(BASE_URL) ?>/menu.php" class="link-success fw-semibold text-decoration-none">
            Jelajahi semua menu <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>

    <?php if ($featuredProducts === []): ?>
        <div class="contact-panel p-4 text-secondary">
            Menu rekomendasi akan tampil di sini setelah produk tersedia.
        </div>
    <?php else: ?>
        <div class="row g-3 g-lg-4">
            <?php foreach ($featuredProducts as $product): ?>
                <?php $imageUrl = product_image_url(is_string($product['image']) ? $product['image'] : null); ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <article class="product-card bg-white">
                        <?php if ($imageUrl !== null): ?>
                            <img src="<?= sanitize($imageUrl) ?>" alt="<?= sanitize($product['name']) ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-image-placeholder" aria-hidden="true">
                                <i class="bi bi-cup-hot"></i>
                            </div>
                        <?php endif; ?>
                        <div class="p-3 p-lg-4">
                            <span class="category-pill badge rounded-pill mb-2"><?= sanitize($product['category_name']) ?></span>
                            <h3 class="h5 fw-bold"><?= sanitize($product['name']) ?></h3>
                            <p class="text-secondary small"><?= sanitize($product['description'] ?? '') ?></p>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <strong class="text-success"><?= sanitize(format_rupiah($product['price'])) ?></strong>
                                <a href="<?= sanitize(BASE_URL) ?>/menu.php" class="btn btn-sm btn-outline-success">Pilih menu</a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="container py-4 py-lg-5" aria-labelledby="visit-heading">
    <div class="contact-panel p-4 p-lg-5">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <p class="section-kicker text-uppercase small fw-semibold mb-2">Kunjungi kami</p>
                <h2 id="visit-heading" class="h3 fw-bold mb-0">Sampai jumpa di kafe.</h2>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="d-flex gap-3">
                    <span class="contact-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="h6 fw-bold">Lokasi</h3>
                        <p class="text-secondary small mb-0"><?= sanitize(CAFE_ADDRESS) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <div class="d-flex gap-3">
                    <span class="contact-icon"><i class="bi bi-clock" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="h6 fw-bold">Jam operasional</h3>
                        <p class="text-secondary small mb-0"><?= sanitize(CAFE_OPENING_HOURS) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div class="d-flex gap-3">
                    <span class="contact-icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="h6 fw-bold">Kontak</h3>
                        <?php if ($phoneIsValid): ?>
                            <a class="small link-success" href="tel:<?= sanitize($phoneHref) ?>"><?= sanitize(CAFE_PHONE) ?></a>
                        <?php else: ?>
                            <p class="text-secondary small mb-0">Informasi kontak belum diatur.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
