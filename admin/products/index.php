<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$errorMessage = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (($_POST['action'] ?? '') === 'toggle_availability') {
        try {
            $id = admin_parse_positive_int($_POST['id'] ?? null);
            if ($id === null) {
                throw new InvalidArgumentException('Produk yang dipilih tidak valid.');
            }

            $statement = $pdo->prepare(
                'UPDATE products
                 SET is_available = CASE WHEN is_available = 1 THEN 0 ELSE 1 END
                 WHERE id = :id'
            );
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }

            set_admin_flash('success', 'Status ketersediaan produk berhasil diperbarui.');
            redirect_admin('products/index.php');
        } catch (PDOException $exception) {
            error_log('Product availability update error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Status produk tidak dapat diperbarui saat ini.';
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        }
    } else {
        http_response_code(400);
        $errorMessage = 'Aksi produk tidak dikenali.';
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$categoryFilter = null;
if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
    $categoryFilter = admin_parse_positive_int($_GET['category_id']);
    if ($categoryFilter === null) {
        http_response_code(422);
        $errorMessage = 'Filter kategori tidak valid.';
    }
}

$availabilityFilter = $_GET['availability'] ?? 'all';
if (!is_string($availabilityFilter) || !in_array($availabilityFilter, ['all', 'available', 'unavailable'], true)) {
    $availabilityFilter = 'all';
    http_response_code(422);
    $errorMessage = 'Filter ketersediaan tidak valid.';
}

$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
if (!is_string($searchInput) || preg_match('/\A.{0,150}\z/us', $search) !== 1) {
    $search = '';
    http_response_code(422);
    $errorMessage = 'Pencarian maksimal 150 karakter.';
}

$conditions = [];
$parameters = [];
if ($categoryFilter !== null) {
    $conditions[] = 'p.category_id = :category_id';
    $parameters['category_id'] = $categoryFilter;
}
if ($availabilityFilter === 'available') {
    $conditions[] = 'p.is_available = 1';
} elseif ($availabilityFilter === 'unavailable') {
    $conditions[] = 'p.is_available = 0';
}
if ($search !== '') {
    $conditions[] = '(p.name LIKE :name_search OR p.description LIKE :description_search)';
    $parameters['name_search'] = '%' . $search . '%';
    $parameters['description_search'] = '%' . $search . '%';
}

$sql = 'SELECT p.id, p.category_id, p.name, p.description, p.price, p.image, p.is_available,
               c.name AS category_name,
               (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id';
if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$products = $statement->fetchAll();

$pageTitle = 'Menu Produk';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="product-search" class="form-label">Cari produk</label>
                <input
                    type="search"
                    class="form-control"
                    id="product-search"
                    name="q"
                    value="<?= sanitize($search) ?>"
                    maxlength="150"
                    placeholder="Nama atau deskripsi"
                >
            </div>
            <div class="col-12 col-md-3">
                <label for="product-category" class="form-label">Kategori</label>
                <select class="form-select" id="product-category" name="category_id">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= sanitize($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="product-availability" class="form-label">Ketersediaan</label>
                <select class="form-select" id="product-availability" name="availability">
                    <option value="all" <?= $availabilityFilter === 'all' ? 'selected' : '' ?>>Semua status</option>
                    <option value="available" <?= $availabilityFilter === 'available' ? 'selected' : '' ?>>Tersedia</option>
                    <option value="unavailable" <?= $availabilityFilter === 'unavailable' ? 'selected' : '' ?>>Tidak tersedia</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-success flex-grow-1">Filter</button>
                <a href="<?= sanitize(admin_base_url()) ?>/products/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Daftar produk</h2>
            <p class="text-secondary small mb-0"><?= count($products) ?> produk ditemukan</p>
        </div>
        <a href="<?= sanitize(admin_base_url()) ?>/products/create.php" class="btn btn-success">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah produk
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Produk</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Harga dasar</th>
                        <th scope="col">Varian</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($products === []): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-5">Produk tidak ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php $imageUrl = product_image_url(is_string($product['image']) ? $product['image'] : null); ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($imageUrl !== null): ?>
                                        <img
                                            src="<?= sanitize($imageUrl) ?>"
                                            alt=""
                                            width="52"
                                            height="52"
                                            class="rounded object-fit-cover"
                                        >
                                    <?php else: ?>
                                        <span class="rounded bg-body-secondary d-inline-flex align-items-center justify-content-center" style="width: 52px; height: 52px">
                                            <i class="bi bi-image text-secondary" aria-hidden="true"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <span class="fw-semibold d-block"><?= sanitize($product['name']) ?></span>
                                        <small class="text-secondary"><?= sanitize($product['description'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($product['category_name']) ?></td>
                            <td><?= sanitize(format_rupiah($product['price'])) ?></td>
                            <td><?= (int) $product['variant_count'] ?></td>
                            <td>
                                <span class="badge text-bg-<?= (int) $product['is_available'] === 1 ? 'success' : 'secondary' ?>">
                                    <?= (int) $product['is_available'] === 1 ? 'Tersedia' : 'Tidak tersedia' ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <a
                                        href="<?= sanitize(admin_base_url()) ?>/variants/index.php?product_id=<?= (int) $product['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                    >Varian</a>
                                    <a
                                        href="<?= sanitize(admin_base_url()) ?>/products/edit.php?id=<?= (int) $product['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >Edit</a>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_availability">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <?= (int) $product['is_available'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= sanitize(admin_base_url()) ?>/products/delete.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
