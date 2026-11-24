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
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create' || $action === 'update') {
                $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
                if (preg_match('/\A.{1,100}\z/us', $name) !== 1) {
                    throw new InvalidArgumentException('Nama kategori wajib diisi (maksimal 100 karakter).');
                }

                if ($action === 'create') {
                    $statement = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
                    $statement->execute(['name' => $name]);
                    set_admin_flash('success', 'Kategori berhasil ditambahkan.');
                } else {
                    $id = admin_parse_positive_int($_POST['id'] ?? null);
                    if ($id === null) {
                        throw new InvalidArgumentException('Kategori yang dipilih tidak valid.');
                    }

                    $statement = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
                    $statement->execute(['name' => $name, 'id' => $id]);
                    if ($statement->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
                        $check->execute(['id' => $id]);
                        if ($check->fetchColumn() === false) {
                            throw new RuntimeException('Kategori tidak ditemukan.');
                        }
                    }
                    set_admin_flash('success', 'Kategori berhasil diperbarui.');
                }

                redirect_admin('categories/index.php');
            }

            if ($action === 'delete') {
                $id = admin_parse_positive_int($_POST['id'] ?? null);
                if ($id === null) {
                    throw new InvalidArgumentException('Kategori yang dipilih tidak valid.');
                }

                $statement = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                $statement->execute(['id' => $id]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }

                set_admin_flash('success', 'Kategori berhasil dihapus.');
                redirect_admin('categories/index.php');
            }

            http_response_code(400);
            $errorMessage = 'Aksi kategori tidak dikenali.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $action === 'delete') {
                http_response_code(409);
                $errorMessage = 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.';
            } elseif ($exception->getCode() === '23000') {
                http_response_code(409);
                $errorMessage = 'Nama kategori tersebut sudah digunakan.';
            } else {
                error_log('Category management database error: ' . $exception->getMessage());
                http_response_code(500);
                $errorMessage = 'Kategori tidak dapat disimpan saat ini.';
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        }
    }
}

$statement = $pdo->query(
    'SELECT c.id, c.name, c.created_at, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.created_at
     ORDER BY c.name'
);
$categories = $statement->fetchAll();

$pageTitle = 'Kategori';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <section class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Tambah kategori</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <label for="category-name" class="form-label">Nama kategori</label>
                    <input
                        type="text"
                        class="form-control mb-3"
                        id="category-name"
                        name="name"
                        maxlength="100"
                        required
                    >
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah kategori
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-8">
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <h2 class="h5 fw-bold mb-1">Daftar kategori</h2>
                <p class="text-secondary small mb-0"><?= count($categories) ?> kategori terdaftar</p>
            </div>
            <div class="card-body p-0 pt-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Nama</th>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($categories === []): ?>
                            <tr><td colspan="3" class="text-center text-secondary py-5">Belum ada kategori.</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= sanitize($category['name']) ?></td>
                                    <td><span class="badge text-bg-light"><?= (int) $category['product_count'] ?></span></td>
                                    <td class="text-end pe-4">
                                        <details class="d-inline-block text-start">
                                            <summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                                            <form method="post" class="mt-2" style="min-width: 16rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                                <label class="form-label small" for="category-<?= (int) $category['id'] ?>">Nama kategori</label>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm mb-2"
                                                    id="category-<?= (int) $category['id'] ?>"
                                                    name="name"
                                                    value="<?= sanitize($category['name']) ?>"
                                                    maxlength="100"
                                                    required
                                                >
                                                <button type="submit" class="btn btn-sm btn-success">Simpan</button>
                                            </form>
                                        </details>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
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
