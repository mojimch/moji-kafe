<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$id = admin_parse_positive_int($_GET['id'] ?? null);
if ($id === null) {
    set_admin_flash('warning', 'Produk yang dipilih tidak valid.');
    redirect_admin('products/index.php');
}

$statement = $pdo->prepare(
    'SELECT id, category_id, name, description, price, image, is_available
     FROM products
     WHERE id = :id'
);
$statement->execute(['id' => $id]);
$product = $statement->fetch();
if (!is_array($product)) {
    set_admin_flash('warning', 'Produk tidak ditemukan.');
    redirect_admin('products/index.php');
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$existingImage = is_string($product['image']) && $product['image'] !== '' ? $product['image'] : null;
$formValues = [
    'category_id' => (string) $product['category_id'],
    'name' => (string) $product['name'],
    'description' => (string) ($product['description'] ?? ''),
    'price' => (string) $product['price'],
    'is_available' => (int) $product['is_available'] === 1,
];
$errorMessage = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $postedId = admin_parse_positive_int($_POST['id'] ?? null);
        if ($postedId !== $id) {
            http_response_code(400);
            $errorMessage = 'Produk yang akan diperbarui tidak sesuai.';
        } else {
            $formValues['category_id'] = is_string($_POST['category_id'] ?? null) ? $_POST['category_id'] : '';
            $formValues['name'] = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
            $formValues['description'] = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');
            $formValues['price'] = is_string($_POST['price'] ?? null) ? trim($_POST['price']) : '';
            $formValues['is_available'] = isset($_POST['is_available']);
            $newImage = null;
            $imageToStore = isset($_POST['remove_image']) ? null : $existingImage;

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
                        $imageToStore = $newImage;
                    }
                }

                $update = $pdo->prepare(
                    'UPDATE products
                     SET category_id = :category_id, name = :name, description = :description,
                         price = :price, image = :image, is_available = :is_available
                     WHERE id = :id'
                );
                $update->execute([
                    'category_id' => $categoryId,
                    'name' => $formValues['name'],
                    'description' => $formValues['description'] === '' ? null : $formValues['description'],
                    'price' => $formValues['price'],
                    'image' => $imageToStore,
                    'is_available' => $formValues['is_available'] ? 1 : 0,
                    'id' => $id,
                ]);

                $oldImageRemoved = true;
                if ($existingImage !== null && $existingImage !== $imageToStore) {
                    $oldImageRemoved = remove_product_image($existingImage);
                }

                set_admin_flash(
                    $oldImageRemoved ? 'success' : 'warning',
                    $oldImageRemoved
                        ? 'Produk berhasil diperbarui.'
                        : 'Produk diperbarui, tetapi foto lama tidak dapat dihapus.'
                );
                redirect_admin('products/index.php');
            } catch (PDOException $exception) {
                if ($newImage !== null) {
                    remove_product_image($newImage);
                }
                error_log('Product update database error: ' . $exception->getMessage());
                http_response_code(500);
                $errorMessage = 'Produk tidak dapat diperbarui saat ini.';
            } catch (InvalidArgumentException | RuntimeException $exception) {
                if ($newImage !== null) {
                    remove_product_image($newImage);
                }
                http_response_code(422);
                $errorMessage = $exception->getMessage();
            }
        }
    }
}

$imageUrl = product_image_url($existingImage);
$pageTitle = 'Edit Produk';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<section class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Perbarui informasi produk</h2>
                <p class="text-secondary small mb-0">Perubahan akan digunakan pada katalog menu berikutnya.</p>
            </div>
            <a href="<?= sanitize(admin_base_url()) ?>/products/index.php" class="btn btn-outline-secondary">Kembali</a>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
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
                    <label for="product-image" class="form-label">Ganti foto produk</label>
                    <input
                        type="file"
                        class="form-control"
                        id="product-image"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >
                    <div class="form-text">JPG, PNG, atau WEBP; ukuran maksimal 2 MB.</div>
                </div>
                <?php if ($imageUrl !== null): ?>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= sanitize($imageUrl) ?>" alt="Foto <?= sanitize($product['name']) ?>" width="88" height="88" class="rounded object-fit-cover">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remove-image" name="remove_image" value="1">
                                <label for="remove-image" class="form-check-label">Hapus foto saat menyimpan</label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Simpan perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
