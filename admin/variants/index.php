<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$errorMessage = null;
$selectedProductId = admin_parse_positive_int($_GET['product_id'] ?? null);
if (
    isset($_GET['product_id'])
    && $_GET['product_id'] !== ''
    && $selectedProductId === null
) {
    http_response_code(422);
    $errorMessage = 'Filter produk tidak valid.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create' || $action === 'update') {
                $productId = admin_parse_positive_int($_POST['product_id'] ?? null);
                $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
                $priceInput = $_POST['price'] ?? null;
                $isAvailable = isset($_POST['is_available']) ? 1 : 0;

                if ($productId === null) {
                    throw new InvalidArgumentException('Pilih produk yang valid.');
                }
                if (preg_match('/\A.{1,100}\z/us', $name) !== 1) {
                    throw new InvalidArgumentException('Nama varian wajib diisi (maksimal 100 karakter).');
                }
                if (!admin_is_valid_price($priceInput)) {
                    throw new InvalidArgumentException('Harga harus bernilai 0 sampai 99.999.999,99.');
                }

                $productCheck = $pdo->prepare('SELECT 1 FROM products WHERE id = :id');
                $productCheck->execute(['id' => $productId]);
                if ($productCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Produk yang dipilih tidak ditemukan.');
                }

                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO product_variants (product_id, name, price, is_available)
                         VALUES (:product_id, :name, :price, :is_available)'
                    );
                    $statement->execute([
                        'product_id' => $productId,
                        'name' => $name,
                        'price' => trim($priceInput),
                        'is_available' => $isAvailable,
                    ]);
                    set_admin_flash('success', 'Varian berhasil ditambahkan.');
                } else {
                    $id = admin_parse_positive_int($_POST['id'] ?? null);
                    if ($id === null) {
                        throw new InvalidArgumentException('Varian yang dipilih tidak valid.');
                    }

                    $statement = $pdo->prepare(
                        'UPDATE product_variants
                         SET product_id = :product_id, name = :name, price = :price, is_available = :is_available
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'product_id' => $productId,
                        'name' => $name,
                        'price' => trim($priceInput),
                        'is_available' => $isAvailable,
                        'id' => $id,
                    ]);
                    if ($statement->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT 1 FROM product_variants WHERE id = :id');
                        $check->execute(['id' => $id]);
                        if ($check->fetchColumn() === false) {
                            throw new RuntimeException('Varian tidak ditemukan.');
                        }
                    }
                    set_admin_flash('success', 'Varian berhasil diperbarui.');
                }

                redirect_admin('variants/index.php?product_id=' . $productId);
            }

            if ($action === 'delete') {
                $id = admin_parse_positive_int($_POST['id'] ?? null);
                if ($id === null) {
                    throw new InvalidArgumentException('Varian yang dipilih tidak valid.');
                }

                $statement = $pdo->prepare('DELETE FROM product_variants WHERE id = :id');
                $statement->execute(['id' => $id]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Varian tidak ditemukan.');
                }

                $productId = admin_parse_positive_int($_POST['product_id'] ?? null);
                set_admin_flash('success', 'Varian berhasil dihapus.');
                redirect_admin('variants/index.php' . ($productId === null ? '' : '?product_id=' . $productId));
            }

            http_response_code(400);
            $errorMessage = 'Aksi varian tidak dikenali.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                http_response_code(409);
                $errorMessage = 'Nama varian tersebut sudah digunakan untuk produk ini.';
            } else {
                error_log('Variant management database error: ' . $exception->getMessage());
                http_response_code(500);
                $errorMessage = 'Varian tidak dapat disimpan saat ini.';
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        }
    }
}

$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
$sql = 'SELECT v.id, v.product_id, v.name, v.price, v.is_available, p.name AS product_name
        FROM product_variants v
        INNER JOIN products p ON p.id = v.product_id';
$parameters = [];
if ($selectedProductId !== null) {
    $sql .= ' WHERE v.product_id = :product_id';
    $parameters['product_id'] = $selectedProductId;
}
$sql .= ' ORDER BY p.name, v.name';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$variants = $statement->fetchAll();

$pageTitle = 'Varian';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <section class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Tambah varian</h2>
                <?php if ($products === []): ?>
                    <p class="text-secondary mb-0">Tambahkan produk terlebih dahulu sebelum membuat varian.</p>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="variant-product" class="form-label">Produk</label>
                            <select class="form-select" id="variant-product" name="product_id" required>
                                <option value="">Pilih produk</option>
                                <?php foreach ($products as $product): ?>
                                    <option
                                        value="<?= (int) $product['id'] ?>"
                                        <?= $selectedProductId === (int) $product['id'] ? 'selected' : '' ?>
                                    ><?= sanitize($product['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="variant-name" class="form-label">Nama varian</label>
                            <input type="text" class="form-control" id="variant-name" name="name" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="variant-price" class="form-label">Harga (Rp)</label>
                            <input type="number" class="form-control" id="variant-price" name="price" min="0" max="99999999.99" step="0.01" required>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="variant-available" name="is_available" value="1" checked>
                            <label for="variant-available" class="form-check-label">Varian tersedia</label>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah varian
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-8">
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Daftar varian</h2>
                        <p class="text-secondary small mb-0"><?= count($variants) ?> varian terdaftar</p>
                    </div>
                    <form method="get" class="d-flex gap-2">
                        <label for="filter-product" class="visually-hidden">Filter produk</label>
                        <select class="form-select form-select-sm" id="filter-product" name="product_id">
                            <option value="">Semua produk</option>
                            <?php foreach ($products as $product): ?>
                                <option
                                    value="<?= (int) $product['id'] ?>"
                                    <?= $selectedProductId === (int) $product['id'] ? 'selected' : '' ?>
                                ><?= sanitize($product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0 pt-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Varian</th>
                                <th scope="col">Produk</th>
                                <th scope="col">Harga</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($variants === []): ?>
                            <tr><td colspan="5" class="text-center text-secondary py-5">Belum ada varian untuk filter ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($variants as $variant): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= sanitize($variant['name']) ?></td>
                                    <td><?= sanitize($variant['product_name']) ?></td>
                                    <td><?= sanitize(format_rupiah($variant['price'])) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= (int) $variant['is_available'] === 1 ? 'success' : 'secondary' ?>">
                                            <?= (int) $variant['is_available'] === 1 ? 'Tersedia' : 'Tidak tersedia' ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <details class="d-inline-block text-start">
                                            <summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                                            <form method="post" class="mt-2" style="min-width: 18rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int) $variant['id'] ?>">
                                                <label class="form-label small" for="variant-product-<?= (int) $variant['id'] ?>">Produk</label>
                                                <select class="form-select form-select-sm mb-2" id="variant-product-<?= (int) $variant['id'] ?>" name="product_id" required>
                                                    <?php foreach ($products as $product): ?>
                                                        <option value="<?= (int) $product['id'] ?>" <?= (int) $product['id'] === (int) $variant['product_id'] ? 'selected' : '' ?>>
                                                            <?= sanitize($product['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label class="form-label small" for="variant-name-<?= (int) $variant['id'] ?>">Nama varian</label>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm mb-2"
                                                    id="variant-name-<?= (int) $variant['id'] ?>"
                                                    name="name"
                                                    value="<?= sanitize($variant['name']) ?>"
                                                    maxlength="100"
                                                    required
                                                >
                                                <label class="form-label small" for="variant-price-<?= (int) $variant['id'] ?>">Harga (Rp)</label>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm mb-2"
                                                    id="variant-price-<?= (int) $variant['id'] ?>"
                                                    name="price"
                                                    value="<?= sanitize($variant['price']) ?>"
                                                    min="0"
                                                    max="99999999.99"
                                                    step="0.01"
                                                    required
                                                >
                                                <div class="form-check mb-2">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        id="variant-available-<?= (int) $variant['id'] ?>"
                                                        name="is_available"
                                                        value="1"
                                                        <?= (int) $variant['is_available'] === 1 ? 'checked' : '' ?>
                                                    >
                                                    <label class="form-check-label small" for="variant-available-<?= (int) $variant['id'] ?>">Tersedia</label>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-success">Simpan</button>
                                            </form>
                                        </details>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $variant['id'] ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) $variant['product_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
