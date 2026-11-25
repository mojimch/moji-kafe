<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$orderId = admin_parse_positive_int($_GET['id'] ?? null);
if ($orderId === null) {
    set_admin_flash('warning', 'Pesanan yang dipilih tidak valid.');
    redirect_admin('orders/index.php');
}

$allowedTransitions = [
    'pending' => ['processing', 'cancelled'],
    'processing' => ['ready', 'cancelled'],
    'ready' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => [],
];
$statusLabels = [
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'ready' => 'Siap',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_admin_flash('danger', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        redirect_admin('orders/detail.php?id=' . $orderId);
    }

    $nextStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    try {
        $pdo->beginTransaction();
        $statusQuery = $pdo->prepare('SELECT order_status FROM orders WHERE id = :id FOR UPDATE');
        $statusQuery->execute(['id' => $orderId]);
        $currentStatus = $statusQuery->fetchColumn();
        if (!is_string($currentStatus)) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }
        if (!in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new InvalidArgumentException('Perubahan status pesanan tersebut tidak diizinkan.');
        }

        $update = $pdo->prepare('UPDATE orders SET order_status = :status WHERE id = :id');
        $update->execute(['status' => $nextStatus, 'id' => $orderId]);
        $pdo->commit();

        set_admin_flash('success', 'Status pesanan berhasil diperbarui menjadi ' . ($statusLabels[$nextStatus] ?? $nextStatus) . '.');
        redirect_admin('orders/detail.php?id=' . $orderId);
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash('warning', $exception->getMessage());
        redirect_admin('orders/detail.php?id=' . $orderId);
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_admin_flash('warning', $exception->getMessage());
        redirect_admin('orders/index.php');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Order status update database error: ' . $exception->getMessage());
        set_admin_flash('danger', 'Status pesanan tidak dapat diperbarui saat ini.');
        redirect_admin('orders/detail.php?id=' . $orderId);
    }
}

$orderQuery = $pdo->prepare(
    'SELECT o.id, o.order_code, o.customer_name, o.customer_phone, o.order_type,
            o.table_id, o.subtotal, o.total, o.payment_method, o.payment_status,
            o.order_status, o.created_at, o.updated_at, t.table_number
     FROM orders o
     LEFT JOIN `tables` t ON t.id = o.table_id
     WHERE o.id = :id'
);
$orderQuery->execute(['id' => $orderId]);
$order = $orderQuery->fetch();
if ($order === false) {
    set_admin_flash('warning', 'Pesanan tidak ditemukan.');
    redirect_admin('orders/index.php');
}

$itemsQuery = $pdo->prepare(
    'SELECT product_name, variant_name, price, quantity, subtotal
     FROM order_items
     WHERE order_id = :order_id
     ORDER BY id'
);
$itemsQuery->execute(['order_id' => $orderId]);
$items = $itemsQuery->fetchAll();

$paymentsQuery = $pdo->prepare(
    'SELECT id, method, amount, proof_image, status, verified_at, created_at
     FROM payments
     WHERE order_id = :order_id
     ORDER BY id DESC'
);
$paymentsQuery->execute(['order_id' => $orderId]);
$payments = $paymentsQuery->fetchAll();

$pageTitle = 'Detail Pesanan';
require __DIR__ . '/../../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <p class="text-secondary small mb-1">Kode pesanan</p>
        <h2 class="h4 fw-bold mb-0"><?= sanitize($order['order_code']) ?></h2>
    </div>
    <a href="<?= sanitize(admin_base_url()) ?>/orders/index.php" class="btn btn-outline-secondary">Kembali ke daftar</a>
</div>

<div class="row g-4 align-items-start">
    <div class="col-12 col-xl-7">
        <section class="card border-0 shadow-sm mb-4" aria-labelledby="order-items-heading">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <h3 id="order-items-heading" class="h5 fw-bold mb-0">Item pesanan</h3>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">Menu</th>
                            <th scope="col">Harga</th>
                            <th scope="col">Jumlah</th>
                            <th scope="col" class="text-end pe-4">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($items === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">Rincian item tidak tersedia.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold"><?= sanitize($item['product_name']) ?></span>
                                    <?php if (is_string($item['variant_name']) && $item['variant_name'] !== ''): ?>
                                        <small class="d-block text-secondary"><?= sanitize($item['variant_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= sanitize(format_rupiah($item['price'])) ?></td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td class="text-end pe-4 text-nowrap"><?= sanitize(format_rupiah($item['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="ps-4 text-end">Total</th>
                            <th class="text-end pe-4 text-nowrap"><?= sanitize(format_rupiah($order['total'])) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <?php if ($payments !== []): ?>
            <section class="card border-0 shadow-sm" aria-labelledby="order-payments-heading">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h3 id="order-payments-heading" class="h5 fw-bold mb-0">Riwayat pembayaran</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        <?php foreach ($payments as $payment): ?>
                            <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom pb-3">
                                <div>
                                    <strong><?= $payment['method'] === 'qris' ? 'QRIS' : 'Cash' ?></strong>
                                    <span class="small text-secondary d-block">
                                        <?= sanitize(date('d/m/Y H:i', strtotime($payment['created_at']))) ?>
                                    </span>
                                    <span class="small text-secondary d-block">
                                        Status: <?= sanitize(ucfirst((string) $payment['status'])) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <strong><?= sanitize(format_rupiah($payment['amount'])) ?></strong>
                                    <?php if (is_string($payment['proof_image']) && preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $payment['proof_image']) === 1): ?>
                                        <a
                                            class="small d-block"
                                            href="<?= sanitize(BASE_URL) ?>/uploads/payments/<?= rawurlencode($payment['proof_image']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >Lihat bukti</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="col-12 col-xl-5">
        <section class="card border-0 shadow-sm mb-4" aria-labelledby="order-customer-heading">
            <div class="card-body p-4">
                <h3 id="order-customer-heading" class="h5 fw-bold mb-3">Informasi pelanggan</h3>
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary fw-normal">Nama</dt>
                    <dd class="col-7"><?= sanitize($order['customer_name']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">WhatsApp</dt>
                    <dd class="col-7"><?= sanitize($order['customer_phone']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Jenis pesanan</dt>
                    <dd class="col-7"><?= $order['order_type'] === 'dine_in' ? 'Dine-in' : 'Takeaway' ?></dd>
                    <?php if ($order['order_type'] === 'dine_in'): ?>
                        <dt class="col-5 text-secondary fw-normal">Meja</dt>
                        <dd class="col-7"><?= sanitize(is_string($order['table_number']) ? $order['table_number'] : 'Tidak tersedia') ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-secondary fw-normal">Dibuat</dt>
                    <dd class="col-7"><?= sanitize(date('d/m/Y H:i', strtotime($order['created_at']))) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Metode bayar</dt>
                    <dd class="col-7"><?= $order['payment_method'] === 'qris' ? 'QRIS' : 'Cash' ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Status bayar</dt>
                    <dd class="col-7"><?= sanitize(ucfirst((string) $order['payment_status'])) ?></dd>
                </dl>
            </div>
        </section>

        <section class="card border-0 shadow-sm" aria-labelledby="order-status-heading">
            <div class="card-body p-4">
                <h3 id="order-status-heading" class="h5 fw-bold mb-3">Status pesanan</h3>
                <p class="mb-3">
                    <span class="badge text-bg-<?= $order['order_status'] === 'cancelled' ? 'danger' : ($order['order_status'] === 'completed' ? 'success' : 'warning') ?> fs-6">
                        <?= sanitize($statusLabels[$order['order_status']] ?? ucfirst((string) $order['order_status'])) ?>
                    </span>
                </p>
                <?php $nextStatuses = $allowedTransitions[$order['order_status']] ?? []; ?>
                <?php if ($nextStatuses !== []): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <label for="order-next-status" class="form-label">Ubah status</label>
                        <div class="input-group">
                            <select class="form-select" id="order-next-status" name="status" required>
                                <?php foreach ($nextStatuses as $nextStatus): ?>
                                    <option value="<?= sanitize($nextStatus) ?>"><?= sanitize($statusLabels[$nextStatus]) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-success">Simpan</button>
                        </div>
                        <div class="form-text">Status hanya dapat dilanjutkan sesuai alur pesanan.</div>
                    </form>
                <?php else: ?>
                    <p class="text-secondary mb-0">Pesanan dengan status ini tidak dapat diubah lagi.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
