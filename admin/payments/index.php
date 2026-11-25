<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$statusOptions = [
    'pending' => 'Menunggu verifikasi',
    'verified' => 'Terverifikasi',
    'rejected' => 'Ditolak',
];
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'pending';
if ($statusFilter !== 'all' && !array_key_exists($statusFilter, $statusOptions)) {
    $statusFilter = 'pending';
}
$search = is_string($_GET['code'] ?? null) ? strtoupper(trim($_GET['code'])) : '';
if (preg_match('/\A[A-Z0-9-]{0,32}\z/', $search) !== 1) {
    $search = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : (int) $page;
$pageSize = 20;
$redirectQuery = http_build_query(['status' => $statusFilter, 'code' => $search, 'page' => $page]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_admin_flash('danger', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        redirect_admin('payments/index.php?' . $redirectQuery);
    }

    $paymentId = admin_parse_positive_int($_POST['payment_id'] ?? null);
    $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';
    if ($paymentId === null || !in_array($decision, ['verified', 'rejected'], true)) {
        set_admin_flash('warning', 'Aksi verifikasi pembayaran tidak valid.');
        redirect_admin('payments/index.php?' . $redirectQuery);
    }

    try {
        $pdo->beginTransaction();
        $paymentQuery = $pdo->prepare(
            'SELECT p.id, p.order_id, p.method, p.proof_image, p.status,
                    o.payment_method, o.payment_status
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             WHERE p.id = :id
             FOR UPDATE'
        );
        $paymentQuery->execute(['id' => $paymentId]);
        $payment = $paymentQuery->fetch();
        if ($payment === false) {
            throw new RuntimeException('Bukti pembayaran tidak ditemukan.');
        }
        if ($payment['method'] !== 'qris' || $payment['payment_method'] !== 'qris') {
            throw new InvalidArgumentException('Hanya pembayaran QRIS yang dapat diverifikasi di halaman ini.');
        }
        if ($payment['status'] !== 'pending') {
            throw new InvalidArgumentException('Bukti pembayaran ini sudah diproses sebelumnya.');
        }
        if (
            !is_string($payment['proof_image'])
            || preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $payment['proof_image']) !== 1
        ) {
            throw new InvalidArgumentException('File bukti pembayaran tidak valid sehingga keputusan tidak dapat disimpan.');
        }

        $proofDirectory = realpath(__DIR__ . '/../../public/uploads/payments');
        $proofPath = is_string($proofDirectory)
            ? realpath($proofDirectory . DIRECTORY_SEPARATOR . $payment['proof_image'])
            : false;
        if (
            !is_string($proofDirectory)
            || !is_string($proofPath)
            || !str_starts_with($proofPath, $proofDirectory . DIRECTORY_SEPARATOR)
            || !is_file($proofPath)
            || !is_readable($proofPath)
        ) {
            throw new InvalidArgumentException('File bukti pembayaran tidak ditemukan atau tidak dapat dibaca.');
        }

        $adminId = admin_parse_positive_int($_SESSION['admin_id'] ?? null);
        if ($adminId === null) {
            throw new RuntimeException('Sesi admin tidak valid. Silakan masuk kembali.');
        }

        $paymentUpdate = $pdo->prepare(
            'UPDATE payments
             SET status = :status, verified_by = :verified_by, verified_at = NOW()
             WHERE id = :id AND status = :pending_status'
        );
        $paymentUpdate->execute([
            'status' => $decision,
            'verified_by' => $adminId,
            'id' => $paymentId,
            'pending_status' => 'pending',
        ]);
        if ($paymentUpdate->rowCount() !== 1) {
            throw new InvalidArgumentException('Bukti pembayaran sudah diproses oleh admin lain.');
        }

        $orderUpdate = $pdo->prepare(
            'UPDATE orders SET payment_status = :payment_status WHERE id = :id'
        );
        $orderUpdate->execute([
            'payment_status' => $decision,
            'id' => $payment['order_id'],
        ]);
        $pdo->commit();

        set_admin_flash(
            'success',
            $decision === 'verified'
                ? 'Pembayaran berhasil disetujui dan status pesanan diperbarui.'
                : 'Pembayaran ditolak dan pelanggan dapat mengirim ulang bukti yang benar.'
        );
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
        error_log('Payment verification database error: ' . $exception->getMessage());
        set_admin_flash('danger', 'Status pembayaran tidak dapat diperbarui saat ini.');
    }

    redirect_admin('payments/index.php?' . $redirectQuery);
}

$conditions = ['p.method = :method', 'p.proof_image IS NOT NULL'];
$parameters = ['method' => 'qris'];
if ($statusFilter !== 'all') {
    $conditions[] = 'p.status = :status';
    $parameters['status'] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = 'o.order_code LIKE :order_code';
    $parameters['order_code'] = '%' . $search . '%';
}
$whereSql = ' WHERE ' . implode(' AND ', $conditions);

$countQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id'
    . $whereSql
);
$countQuery->execute($parameters);
$totalPayments = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalPayments / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$paymentsQuery = $pdo->prepare(
    'SELECT p.id, p.order_id, p.amount, p.proof_image, p.status,
            p.verified_at, p.created_at, o.order_code, o.customer_name,
            o.customer_phone, o.payment_status
     FROM payments p
     INNER JOIN orders o ON o.id = p.order_id'
    . $whereSql .
    ' ORDER BY CASE WHEN p.status = \'pending\' THEN 0 ELSE 1 END, p.created_at DESC, p.id DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $name => $value) {
    $paymentsQuery->bindValue(':' . $name, $value, PDO::PARAM_STR);
}
$paymentsQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$paymentsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$paymentsQuery->execute();
$payments = $paymentsQuery->fetchAll();

$paymentDirectory = realpath(__DIR__ . '/../../public/uploads/payments');
$pageTitle = 'Pembayaran QRIS';
require __DIR__ . '/../../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h5 fw-bold mb-1">Verifikasi pembayaran QRIS</h2>
        <p class="text-secondary small mb-0"><?= $totalPayments ?> bukti pembayaran ditemukan</p>
    </div>
</div>

<section class="card border-0 shadow-sm mb-4" aria-label="Filter pembayaran">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="payment-search" class="form-label">Kode pesanan</label>
                <input
                    type="search"
                    class="form-control"
                    id="payment-search"
                    name="code"
                    value="<?= sanitize($search) ?>"
                    placeholder="ORD-..."
                    maxlength="32"
                >
            </div>
            <div class="col-12 col-md-4">
                <label for="payment-status" class="form-label">Status pembayaran</label>
                <select class="form-select" id="payment-status" name="status">
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
                <a href="<?= sanitize(admin_base_url()) ?>/payments/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Pesanan</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Jumlah</th>
                    <th scope="col">Bukti pembayaran</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pe-4">Keputusan</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($payments === []): ?>
                <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada bukti pembayaran yang cocok.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $proofFileName = $payment['proof_image'];
                    $proofPath = is_string($paymentDirectory) && is_string($proofFileName)
                        && preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $proofFileName) === 1
                        ? realpath($paymentDirectory . DIRECTORY_SEPARATOR . $proofFileName)
                        : false;
                    $proofIsReadable = is_string($paymentDirectory)
                        && is_string($proofPath)
                        && str_starts_with($proofPath, $paymentDirectory . DIRECTORY_SEPARATOR)
                        && is_file($proofPath)
                        && is_readable($proofPath);
                    $statusStyle = match ($payment['status']) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    };
                    ?>
                    <tr>
                        <td class="ps-4">
                            <a class="fw-semibold text-decoration-none" href="<?= sanitize(admin_base_url()) ?>/orders/detail.php?id=<?= (int) $payment['order_id'] ?>">
                                <?= sanitize($payment['order_code']) ?>
                            </a>
                            <small class="d-block text-secondary"><?= sanitize(date('d/m/Y H:i', strtotime($payment['created_at']))) ?></small>
                        </td>
                        <td>
                            <span class="fw-medium"><?= sanitize($payment['customer_name']) ?></span>
                            <small class="d-block text-secondary"><?= sanitize($payment['customer_phone']) ?></small>
                        </td>
                        <td class="text-nowrap fw-semibold"><?= sanitize(format_rupiah($payment['amount'])) ?></td>
                        <td>
                            <?php if ($proofIsReadable): ?>
                                <?php $proofUrl = BASE_URL . '/uploads/payments/' . rawurlencode($proofFileName); ?>
                                <a
                                    href="<?= sanitize($proofUrl) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#proof-modal-<?= (int) $payment['id'] ?>"
                                    class="d-inline-flex flex-column align-items-start gap-1 text-decoration-none"
                                >
                                    <img src="<?= sanitize($proofUrl) ?>" alt="Pratinjau bukti <?= sanitize($payment['order_code']) ?>"
                                         class="rounded border" style="width: 4.5rem; height: 3.5rem; object-fit: cover">
                                    <span class="small">Lihat bukti</span>
                                </a>
                                <div class="modal fade" id="proof-modal-<?= (int) $payment['id'] ?>" tabindex="-1"
                                     aria-labelledby="proof-modal-label-<?= (int) $payment['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h2 class="modal-title fs-5" id="proof-modal-label-<?= (int) $payment['id'] ?>">
                                                    Bukti <?= sanitize($payment['order_code']) ?>
                                                </h2>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <img src="<?= sanitize($proofUrl) ?>" alt="Bukti pembayaran <?= sanitize($payment['order_code']) ?>" class="img-fluid rounded">
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= sanitize($proofUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">Buka gambar asli</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="small text-danger">File bukti tidak tersedia.</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= sanitize($statusStyle) ?>">
                                <?= sanitize($statusOptions[$payment['status']] ?? ucfirst((string) $payment['status'])) ?>
                            </span>
                            <?php if (is_string($payment['verified_at'])): ?>
                                <small class="d-block text-secondary"><?= sanitize(date('d/m/Y H:i', strtotime($payment['verified_at']))) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4">
                            <?php if ($payment['status'] === 'pending' && $proofIsReadable): ?>
                                <form method="post" class="d-flex flex-wrap gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                    <button type="submit" name="decision" value="verified" class="btn btn-sm btn-success">
                                        Setujui
                                    </button>
                                    <button type="submit" name="decision" value="rejected" class="btn btn-sm btn-outline-danger">
                                        Tolak
                                    </button>
                                </form>
                            <?php elseif ($payment['status'] === 'pending'): ?>
                                <span class="small text-secondary">Perbaiki file sebelum verifikasi.</span>
                            <?php else: ?>
                                <span class="small text-secondary">Sudah diproses</span>
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
