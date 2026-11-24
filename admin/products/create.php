<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$errorMessage = null;
$formValues = [
    'category_id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'is_available' => true,
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $formValues['category_id'] = is_string($_POST['category_id'] ?? null) ? $_POST['category_id'] : '';
        $formValues['name'] = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $formValues['description'] = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');
        $formValues['price'] = is_string($_POST['price'] ?? null) ? trim($_POST['price']) : '';
        $formValues['is_available'] = isset($_POST['is_available']);
        $newImage = null;

        try {
            $categoryId = admin_parse_positive_int($formValues['category_id']);
            if ($categoryId === null) {
                throw new InvalidArgumentException('Pilih kategori yang valid.');
            }
            if (preg_match('/\A.{1,150}\z/us', $formValues['name']) !== 1) {
                throw new InvalidArgumentException('Nama produk wajib diisi (maksimal 150 karakter).');
            }
            if (!admin_is_valid_price($formValues['price'])) {
                throw new InvalidArgumentException('Harga harus bernilai 0 sampai 99.999.999,99.');
            }
            if (strlen($formValues['description']) > 65535 || preg_match('//u', $formValues['description']) !== 1) {
                throw new InvalidArgumentException('Deskripsi terlalu panjang atau bukan teks UTF-8 yang valid.');
            }

            $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
            $categoryCheck->execute(['id' => $categoryId]);
            if ($categoryCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Kategori yang dipilih tidak ditemukan.');
            }

            if (isset($_FILES['image'])) {
                if (!is_array($_FILES['image'])) {
                    throw new InvalidArgumentException('Data gambar tidak valid.');
                }
                if (($_FILES['image']['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                    $newImage = upload_image($_FILES['image'], product_image_directory());
                }
            }

            $statement = $pdo->prepare(
                'INSERT INTO products
                    (category_id, name, description, price, image, is_available)
                 VALUES
                    (:category_id, :name, :description, :price, :image, :is_available)'
            );
            $statement->execute([
                'category_id' => $categoryId,
                'name' => $formValues['name'],
                'description' => $formValues['description'] === '' ? null : $formValues['description'],
                'price' => $formValues['price'],
                'image' => $newImage,
                'is_available' => $formValues['is_available'] ? 1 : 0,
            ]);

            set_admin_flash('success', 'Produk berhasil ditambahkan.');
            redirect_admin('products/index.php');
        } catch (PDOException $exception) {
            if ($newImage !== null && !remove_product_image($newImage)) {
                error_log('Unable to clean up a product image after a failed insert.');
            }
            error_log('Product creation database error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Produk tidak dapat disimpan saat ini.';
        } catch (InvalidArgumentException | RuntimeException $exception) {
            if ($newImage !== null && !remove_product_image($newImage)) {
                error_log('Unable to clean up a product image after a failed insert.');
            }
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        }
    }
}

$pageTitle = 'Tambah Produk';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<section class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Informasi produk</h2>
                <p class="text-secondary small mb-0">Isi detail menu yang akan ditampilkan kepada pelanggan.</p>
            </div>
            <a href="<?= sanitize(admin_base_url()) ?>/products/index.php" class="btn btn-outline-secondary">Kembali</a>
        </div>

        <?php if ($categories === []): ?>
            <div class="alert alert-warning mb-0" role="alert">
                Buat kategori terlebih dahulu sebelum menambahkan produk.
                <a href="<?= sanitize(admin_base_url()) ?>/categories/index.php" class="alert-link">Kelola kategori</a>
            </div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="product-name" class="form-label">Nama produk</label>
                        <input
                            type="text"
                            class="form-control"
                            id="product-name"
                            name="name"
                            value="<?= sanitize($formValues['name']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="product-category" class="form-label">Kategori</label>
                        <select class="form-select" id="product-category" name="category_id" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= $formValues['category_id'] === (string) $category['id'] ? 'selected' : '' ?>
                                ><?= sanitize($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="product-description" class="form-label">Deskripsi</label>
                        <textarea
                            class="form-control"
                            id="product-description"
                            name="description"
                            rows="4"
                            maxlength="65535"
                        ><?= sanitize($formValues['description']) ?></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="product-price" class="form-label">Harga dasar (Rp)</label>
                        <input
                            type="number"
                            class="form-control"
                            id="product-price"
                            name="price"
                            value="<?= sanitize($formValues['price']) ?>"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                        >
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="product-image" class="form-label">Foto produk</label>
                        <input
                            type="file"
                            class="form-control"
                            id="product-image"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >
                        <div class="form-text">JPG, PNG, atau WEBP; ukuran maksimal 2 MB.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="product-available"
                                name="is_available"
                                value="1"
                                <?= $formValues['is_available'] ? 'checked' : '' ?>
                            >
                            <label for="product-available" class="form-check-label">Produk tersedia</label>
                        </div>
                    </div>
                    <div class="col-12 pt-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Simpan produk
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
