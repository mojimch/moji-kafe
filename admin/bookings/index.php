<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$statusOptions = [
    'pending' => 'Menunggu',
    'confirmed' => 'Dikonfirmasi',
    'in_use' => 'Sedang digunakan',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
$allowedTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['in_use', 'cancelled'],
    'in_use' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => [],
];

$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';
if ($statusFilter !== 'all' && !array_key_exists($statusFilter, $statusOptions)) {
    $statusFilter = 'all';
}
$search = is_string($_GET['code'] ?? null) ? strtoupper(trim($_GET['code'])) : '';
if (preg_match('/\A[A-Z0-9-]{0,32}\z/', $search) !== 1) {
    $search = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : (int) $page;
$pageSize = 25;

$conditions = [];
$parameters = [];
if ($statusFilter !== 'all') {
    $conditions[] = 'b.status = :status';
    $parameters['status'] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = 'b.booking_code LIKE :booking_code';
    $parameters['booking_code'] = '%' . $search . '%';
}
$whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$redirectQuery = http_build_query([
    'status' => $statusFilter,
    'code' => $search,
    'page' => $page,
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_admin_flash('danger', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        redirect_admin('bookings/index.php?' . $redirectQuery);
    }

    $bookingId = admin_parse_positive_int($_POST['id'] ?? null);
    $nextStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    if ($bookingId === null) {
        set_admin_flash('warning', 'Reservasi yang dipilih tidak valid.');
        redirect_admin('bookings/index.php?' . $redirectQuery);
    }

    try {
        $pdo->beginTransaction();
        $statusQuery = $pdo->prepare('SELECT status FROM bookings WHERE id = :id FOR UPDATE');
        $statusQuery->execute(['id' => $bookingId]);
        $currentStatus = $statusQuery->fetchColumn();
        if (!is_string($currentStatus)) {
            throw new RuntimeException('Reservasi tidak ditemukan.');
        }
        if (!in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new InvalidArgumentException('Perubahan status reservasi tersebut tidak diizinkan.');
        }

        $update = $pdo->prepare('UPDATE bookings SET status = :status WHERE id = :id');
        $update->execute(['status' => $nextStatus, 'id' => $bookingId]);
        $pdo->commit();
        set_admin_flash('success', 'Status reservasi berhasil diperbarui menjadi ' . ($statusOptions[$nextStatus] ?? $nextStatus) . '.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash('warning', $exception->getMessage());
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash('warning', $exception->getMessage());
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Booking status update database error: ' . $exception->getMessage());
        set_admin_flash('danger', 'Status reservasi tidak dapat diperbarui saat ini.');
    }

    redirect_admin('bookings/index.php?' . $redirectQuery);
}

$countQuery = $pdo->prepare('SELECT COUNT(*) FROM bookings b' . $whereSql);
$countQuery->execute($parameters);
$totalBookings = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalBookings / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$bookingsQuery = $pdo->prepare(
    'SELECT b.id, b.booking_code, b.customer_name, b.customer_phone, b.booking_date,
            b.start_time, b.end_time, b.guest_count, b.status, b.created_at,
            t.table_number
     FROM bookings b
     INNER JOIN `tables` t ON t.id = b.table_id'
    . $whereSql .
    ' ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $name => $value) {
    $bookingsQuery->bindValue(':' . $name, $value, PDO::PARAM_STR);
}
$bookingsQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$bookingsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$bookingsQuery->execute();
$bookings = $bookingsQuery->fetchAll();

$pageTitle = 'Reservasi';
require __DIR__ . '/../../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h5 fw-bold mb-1">Daftar reservasi meja</h2>
        <p class="text-secondary small mb-0"><?= $totalBookings ?> reservasi ditemukan</p>
    </div>
</div>

<section class="card border-0 shadow-sm mb-4" aria-label="Filter reservasi">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="booking-search" class="form-label">Kode reservasi</label>
                <input
                    type="search"
                    class="form-control"
                    id="booking-search"
                    name="code"
                    value="<?= sanitize($search) ?>"
                    placeholder="BOOK-..."
                    maxlength="32"
                >
            </div>
            <div class="col-12 col-md-4">
                <label for="booking-status" class="form-label">Status</label>
                <select class="form-select" id="booking-status" name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua status</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= sanitize($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-success flex-grow-1">Filter</button>
                <a href="<?= sanitize(admin_base_url()) ?>/bookings/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Reservasi</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Meja / Tamu</th>
                    <th scope="col">Jadwal</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pe-4">Ubah status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($bookings === []): ?>
                <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada reservasi yang cocok.</td></tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $bookingStart = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['start_time']);
                    $bookingEnd = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['end_time']);
                    if ($bookingEnd <= $bookingStart) {
                        $bookingEnd = $bookingEnd->modify('+1 day');
                    }
                    $nextStatuses = $allowedTransitions[$booking['status']] ?? [];
                    $statusStyle = match ($booking['status']) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'in_use' => 'primary',
                        default => 'warning',
                    };
                    ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fw-semibold"><?= sanitize($booking['booking_code']) ?></span>
                            <small class="d-block text-secondary"><?= sanitize(date('d/m/Y H:i', strtotime($booking['created_at']))) ?></small>
                        </td>
                        <td>
                            <span class="fw-medium"><?= sanitize($booking['customer_name']) ?></span>
                            <small class="d-block text-secondary"><?= sanitize($booking['customer_phone']) ?></small>
                        </td>
                        <td>
                            <span class="d-block"><?= sanitize($booking['table_number']) ?></span>
                            <small class="text-secondary"><?= (int) $booking['guest_count'] ?> tamu</small>
                        </td>
                        <td class="text-nowrap">
                            <?= sanitize($bookingStart->format('d/m/Y H:i')) ?>
                            <small class="d-block text-secondary">s.d. <?= sanitize($bookingEnd->format('d/m/Y H:i')) ?></small>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= sanitize($statusStyle) ?>">
                                <?= sanitize($statusOptions[$booking['status']] ?? ucfirst((string) $booking['status'])) ?>
                            </span>
                        </td>
                        <td class="pe-4">
                            <?php if ($nextStatuses !== []): ?>
                                <form method="post" class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                                    <select class="form-select form-select-sm" name="status" aria-label="Status reservasi berikutnya" required>
                                        <?php foreach ($nextStatuses as $nextStatus): ?>
                                            <option value="<?= sanitize($nextStatus) ?>"><?= sanitize($statusOptions[$nextStatus]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Simpan</button>
                                </form>
                            <?php else: ?>
                                <span class="small text-secondary">Final</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-secondary">Halaman <?= $page ?> dari <?= $totalPages ?></span>
            <div class="btn-group">
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="?<?= sanitize(http_build_query(['code' => $search, 'status' => $statusFilter, 'page' => $page - 1])) ?>">Sebelumnya</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="?<?= sanitize(http_build_query(['code' => $search, 'status' => $statusFilter, 'page' => $page + 1])) ?>">Berikutnya</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
