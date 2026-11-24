<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$categoryQuery = $pdo->query('SELECT id, name FROM categories ORDER BY name');
$categories = $categoryQuery->fetchAll();
$selectedCategoryId = null;
$errorMessage = null;

if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
    $categoryValue = $_GET['category_id'];
    $categoryId = is_string($categoryValue)
        ? filter_var($categoryValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($categoryId === false) {
        http_response_code(400);
        $errorMessage = 'Kategori yang dipilih tidak valid.';
    } else {
        $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
        $categoryCheck->execute(['id' => $categoryId]);
        if ($categoryCheck->fetchColumn() === false) {
            http_response_code(404);
            $errorMessage = 'Kategori tidak ditemukan.';
        } else {
            $selectedCategoryId = (int) $categoryId;
        }
    }
}

$sql = 'SELECT p.id, p.category_id, p.name, p.description, p.price, p.image, p.is_available,
               c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id';
$parameters = [];
if ($selectedCategoryId !== null) {
    $sql .= ' WHERE p.category_id = :category_id';
    $parameters['category_id'] = $selectedCategoryId;
}
$sql .= ' ORDER BY p.is_available DESC, c.name, p.name';
$productQuery = $pdo->prepare($sql);
$productQuery->execute($parameters);
$products = $productQuery->fetchAll();

$variantsByProduct = [];
if ($products !== []) {
    $productIds = array_map(static fn (array $product): int => (int) $product['id'], $products);
    $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
    $variantQuery = $pdo->prepare(
        'SELECT id, product_id, name, price, is_available
         FROM product_variants
         WHERE product_id IN (' . $placeholders . ')
         ORDER BY product_id, id'
    );
    $variantQuery->execute($productIds);
    foreach ($variantQuery->fetchAll() as $variant) {
        $variantsByProduct[(int) $variant['product_id']][] = $variant;
    }
}

$pageTitle = 'Menu';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Dibuat dengan sepenuh hati</p>
        <h1 class="display-5 fw-bold mb-2">Menu <?= sanitize(APP_NAME) ?></h1>
        <p class="text-secondary mb-0">Pilih sajian dan varian favoritmu untuk mulai memesan.</p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-warning" role="alert"><?= sanitize($errorMessage) ?></div>
    <?php endif; ?>

    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Filter kategori menu">
        <a
            href="<?= sanitize(BASE_URL) ?>/menu.php"
            class="btn <?= $selectedCategoryId === null ? 'btn-success' : 'btn-outline-success' ?> rounded-pill"
            <?= $selectedCategoryId === null ? 'aria-current="page"' : '' ?>
        >Semua</a>
        <?php foreach ($categories as $category): ?>
            <?php $isActive = $selectedCategoryId === (int) $category['id']; ?>
            <a
                href="<?= sanitize(BASE_URL) ?>/menu.php?category_id=<?= (int) $category['id'] ?>"
                class="btn <?= $isActive ? 'btn-success' : 'btn-outline-success' ?> rounded-pill"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            ><?= sanitize($category['name']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($products === []): ?>
        <div class="contact-panel p-4 p-lg-5 text-center">
            <i class="bi bi-cup-hot display-5 text-success" aria-hidden="true"></i>
            <h2 class="h4 fw-bold mt-3">Menu belum tersedia</h2>
            <p class="text-secondary mb-0">Silakan pilih kategori lain atau kunjungi kembali nanti.</p>
        </div>
    <?php else: ?>
        <div class="row g-3 g-lg-4">
            <?php foreach ($products as $product): ?>
                <?php
                $productId = (int) $product['id'];
                $imageUrl = product_image_url(is_string($product['image']) ? $product['image'] : null);
                $productVariants = $variantsByProduct[$productId] ?? [];
                $availableVariants = array_values(array_filter(
                    $productVariants,
                    static fn (array $variant): bool => (int) $variant['is_available'] === 1
                ));
                $hasVariants = $productVariants !== [];
                $canAdd = (int) $product['is_available'] === 1 && (!$hasVariants || $availableVariants !== []);
                $displayPrice = $availableVariants[0]['price'] ?? $product['price'];
                ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <article
                        class="product-card bg-white"
                        data-product-card
                        data-product-id="<?= $productId ?>"
                        data-product-name="<?= sanitize($product['name']) ?>"
                        data-base-price="<?= sanitize($product['price']) ?>"
                    >
                        <?php if ($imageUrl !== null): ?>
                            <img src="<?= sanitize($imageUrl) ?>" alt="<?= sanitize($product['name']) ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-image-placeholder" aria-hidden="true">
                                <i class="bi bi-cup-hot"></i>
                            </div>
                        <?php endif; ?>
                        <div class="p-3 p-lg-4">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="category-pill badge rounded-pill"><?= sanitize($product['category_name']) ?></span>
                                <span class="badge text-bg-<?= $canAdd ? 'success' : 'secondary' ?>">
                                    <?php if ((int) $product['is_available'] !== 1): ?>
                                        Tidak tersedia
                                    <?php elseif ($hasVariants && $availableVariants === []): ?>
                                        Varian habis
                                    <?php else: ?>
                                        Tersedia
                                    <?php endif; ?>
                                </span>
                            </div>
                            <h2 class="h5 fw-bold"><?= sanitize($product['name']) ?></h2>
                            <?php if (is_string($product['description']) && $product['description'] !== ''): ?>
                                <p class="product-description small text-secondary"><?= sanitize($product['description']) ?></p>
                            <?php else: ?>
                                <p class="product-description small text-secondary">Sajian pilihan dari <?= sanitize(APP_NAME) ?>.</p>
                            <?php endif; ?>

                            <?php if ($availableVariants !== []): ?>
                                <label for="variant-<?= $productId ?>" class="form-label small fw-semibold">Pilih varian</label>
                                <select
                                    class="form-select form-select-sm mb-3"
                                    id="variant-<?= $productId ?>"
                                    data-variant-select
                                    <?= (int) $product['is_available'] !== 1 ? 'disabled' : '' ?>
                                >
                                    <?php foreach ($availableVariants as $variant): ?>
                                        <option
                                            value="<?= (int) $variant['id'] ?>"
                                            data-variant-name="<?= sanitize($variant['name']) ?>"
                                            data-price="<?= sanitize($variant['price']) ?>"
                                        ><?= sanitize($variant['name']) ?> — <?= sanitize(format_rupiah($variant['price'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($hasVariants): ?>
                                <p class="small text-secondary mb-3">Semua varian sedang tidak tersedia.</p>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="small text-secondary d-block">Harga</span>
                                    <strong class="text-success" data-current-price><?= sanitize(format_rupiah($displayPrice)) ?></strong>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-success"
                                    data-add-to-cart
                                    <?= $canAdd ? '' : 'disabled' ?>
                                >
                                    <i class="bi bi-bag-plus me-1" aria-hidden="true"></i>Tambah
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
