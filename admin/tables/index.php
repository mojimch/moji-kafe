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
                $tableNumber = trim(is_string($_POST['table_number'] ?? null) ? $_POST['table_number'] : '');
                $capacity = admin_parse_positive_int($_POST['capacity'] ?? null, 255);
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if (preg_match('/\A[\pL\pN][\pL\pN ._-]{0,19}\z/u', $tableNumber) !== 1) {
                    throw new InvalidArgumentException('Nomor meja wajib diisi (maksimal 20 karakter).');
                }
                if ($capacity === null) {
                    throw new InvalidArgumentException('Kapasitas harus berupa bilangan antara 1 dan 255.');
                }

                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO `tables` (table_number, capacity, is_active)
                         VALUES (:table_number, :capacity, :is_active)'
                    );
                    $statement->execute([
                        'table_number' => $tableNumber,
                        'capacity' => $capacity,
                        'is_active' => $isActive,
                    ]);
                    set_admin_flash('success', 'Meja berhasil ditambahkan.');
                } else {
                    $id = admin_parse_positive_int($_POST['id'] ?? null);
                    if ($id === null) {
                        throw new InvalidArgumentException('Meja yang dipilih tidak valid.');
                    }

                    $statement = $pdo->prepare(
                        'UPDATE `tables`
                         SET table_number = :table_number, capacity = :capacity, is_active = :is_active
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'table_number' => $tableNumber,
                        'capacity' => $capacity,
                        'is_active' => $isActive,
                        'id' => $id,
                    ]);

                    if ($statement->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT 1 FROM `tables` WHERE id = :id');
                        $check->execute(['id' => $id]);
                        if ($check->fetchColumn() === false) {
                            throw new RuntimeException('Meja tidak ditemukan.');
                        }
                    }

                    set_admin_flash('success', 'Data meja berhasil diperbarui.');
                }

                redirect_admin('tables/index.php');
            }

            if ($action === 'delete') {
                $id = admin_parse_positive_int($_POST['id'] ?? null);
                if ($id === null) {
                    throw new InvalidArgumentException('Meja yang dipilih tidak valid.');
                }

                $statement = $pdo->prepare('DELETE FROM `tables` WHERE id = :id');
                $statement->execute(['id' => $id]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Meja tidak ditemukan.');
                }

                set_admin_flash('success', 'Meja berhasil dihapus.');
                redirect_admin('tables/index.php');
            }

            http_response_code(400);
            $errorMessage = 'Aksi meja tidak dikenali.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $action === 'delete') {
                http_response_code(409);
                $errorMessage = 'Meja tidak dapat dihapus karena masih memiliki reservasi.';
            } elseif ($exception->getCode() === '23000') {
                http_response_code(409);
                $errorMessage = 'Nomor meja tersebut sudah digunakan.';
            } else {
                error_log('Table management database error: ' . $exception->getMessage());
                http_response_code(500);
                $errorMessage = 'Data meja tidak dapat disimpan saat ini.';
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        }
    }
}

$statement = $pdo->query(
    'SELECT t.id, t.table_number, t.capacity, t.is_active, t.created_at,
            COUNT(b.id) AS booking_count
     FROM `tables` t
     LEFT JOIN bookings b ON b.table_id = t.id
     GROUP BY t.id, t.table_number, t.capacity, t.is_active, t.created_at
     ORDER BY t.table_number'
);
$tables = $statement->fetchAll();

$pageTitle = 'Meja';
require __DIR__ . '/../../includes/admin_header.php';
?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <section class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Tambah meja</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label for="table-number" class="form-label">Nomor meja</label>
                        <input type="text" class="form-control" id="table-number" name="table_number" maxlength="20" required>
                    </div>
                    <div class="mb-3">
                        <label for="table-capacity" class="form-label">Kapasitas</label>
                        <input type="number" class="form-control" id="table-capacity" name="capacity" min="1" max="255" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="table-active" name="is_active" value="1" checked>
                        <label for="table-active" class="form-check-label">Meja aktif</label>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah meja
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-8">
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <h2 class="h5 fw-bold mb-1">Daftar meja</h2>
                <p class="text-secondary small mb-0"><?= count($tables) ?> meja terdaftar</p>
            </div>
            <div class="card-body p-0 pt-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Meja</th>
                                <th scope="col">Kapasitas</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($tables === []): ?>
                            <tr><td colspan="4" class="text-center text-secondary py-5">Belum ada meja.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-semibold"><?= sanitize($table['table_number']) ?></span>
                                        <small class="d-block text-secondary"><?= (int) $table['booking_count'] ?> reservasi</small>
                                    </td>
                                    <td><?= (int) $table['capacity'] ?> orang</td>
                                    <td>
                                        <span class="badge text-bg-<?= (int) $table['is_active'] === 1 ? 'success' : 'secondary' ?>">
                                            <?= (int) $table['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <details class="d-inline-block text-start">
                                            <summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                                            <form method="post" class="mt-2" style="min-width: 16rem">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int) $table['id'] ?>">
                                                <label class="form-label small" for="table-number-<?= (int) $table['id'] ?>">Nomor meja</label>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm mb-2"
                                                    id="table-number-<?= (int) $table['id'] ?>"
                                                    name="table_number"
                                                    value="<?= sanitize($table['table_number']) ?>"
                                                    maxlength="20"
                                                    required
                                                >
                                                <label class="form-label small" for="table-capacity-<?= (int) $table['id'] ?>">Kapasitas</label>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm mb-2"
                                                    id="table-capacity-<?= (int) $table['id'] ?>"
                                                    name="capacity"
                                                    value="<?= (int) $table['capacity'] ?>"
                                                    min="1"
                                                    max="255"
                                                    required
                                                >
                                                <div class="form-check mb-2">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        id="table-active-<?= (int) $table['id'] ?>"
                                                        name="is_active"
                                                        value="1"
                                                        <?= (int) $table['is_active'] === 1 ? 'checked' : '' ?>
                                                    >
                                                    <label class="form-check-label small" for="table-active-<?= (int) $table['id'] ?>">Aktif</label>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-success">Simpan</button>
                                            </form>
                                        </details>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $table['id'] ?>">
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
