<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$orderCode = is_string($_GET['code'] ?? null) ? trim($_GET['code']) : '';
$accessToken = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
$errorMessage = null;
$order = null;
$items = [];
$latestPayment = null;

if (array_key_exists('code', $_GET) && $orderCode !== '') {
    if (!is_valid_order_code($orderCode)) {
        http_response_code(400);
        $errorMessage = 'Format kode pesanan tidak valid. Gunakan format ORD-YYYYMMDD-XXX.';
    } else {
        $orderQuery = $pdo->prepare(
            'SELECT o.id, o.order_code, o.access_token_hash, o.order_type, o.table_id, o.subtotal, o.total,
                    o.payment_method, o.payment_status, o.order_status, o.created_at,
                    t.table_number
             FROM orders o
             LEFT JOIN `tables` t ON t.id = o.table_id
             WHERE o.order_code = :order_code'
        );
        $orderQuery->execute(['order_code' => $orderCode]);
        $order = $orderQuery->fetch();

        if (
            $order === false
            || !verify_public_access($accessToken, $order['access_token_hash'], $order['created_at'])
        ) {
            $order = null;
            http_response_code(404);
            $errorMessage = 'Pesanan tidak ditemukan atau token akses tidak valid.';
        } else {
            $itemQuery = $pdo->prepare(
                'SELECT product_name, variant_name, price, quantity, subtotal
                 FROM order_items
                 WHERE order_id = :order_id
                 ORDER BY id'
            );
            $itemQuery->execute(['order_id' => $order['id']]);
            $items = $itemQuery->fetchAll();

            $paymentQuery = $pdo->prepare(
                'SELECT status
                 FROM payments
                 WHERE order_id = :order_id
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $paymentQuery->execute(['order_id' => $order['id']]);
            $latestPayment = $paymentQuery->fetch() ?: null;
        }
    }
}

$orderStatusLabels = [
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'ready' => 'Siap',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
$paymentStatusLabels = [
    'pending' => 'Menunggu verifikasi',
    'verified' => 'Terverifikasi',
    'rejected' => 'Ditolak',
];

$pageTitle = 'Cek Pesanan';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" aria-labelledby="check-order-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Informasi pesanan</p>
        <h1 id="check-order-heading" class="display-6 fw-bold mb-2">Cek status pesanan</h1>
        <p class="text-secondary mb-0">Masukkan kode pesanan dan token akses yang diberikan setelah checkout.</p>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-label="Form pencarian pesanan">
        <div class="card-body p-4">
            <form method="get" action="<?= sanitize(BASE_URL) ?>/check-order.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-8">
                    <label for="order-code" class="form-label">Kode pesanan</label>
                    <input
                        type="text"
                        class="form-control text-uppercase"
                        id="order-code"
                        name="code"
                        value="<?= sanitize($orderCode) ?>"
                        placeholder="ORD-YYYYMMDD-XXX"
                        pattern="ORD-[0-9]{8}-[0-9]{3}"
                        maxlength="16"
                        required
                    >
                </div>
                <div class="col-12 col-md-8">
                    <label for="order-access-token" class="form-label">Token akses</label>
                    <input
                        type="text"
                        class="form-control"
                        id="order-access-token"
                        name="token"
                        value="<?= sanitize($accessToken) ?>"
                        pattern="[a-f0-9]{64}"
                        maxlength="64"
                        autocomplete="off"
                    >
                    <div class="form-text">Wajib untuk pesanan baru. Kode lama tanpa token hanya dapat dicek selama masa transisi 7 hari.</div>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>Cari pesanan
                    </button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-warning" role="alert"><?= sanitize($errorMessage) ?></div>
    <?php elseif ($order !== null): ?>
        <?php
        $orderStatus = $orderStatusLabels[$order['order_status']] ?? 'Status pesanan diperbarui';
        $isCash = $order['payment_method'] === 'cash';
        if ($isCash) {
            $paymentStatus = 'Bayar di kasir';
        } elseif (is_array($latestPayment)) {
            $paymentStatus = $paymentStatusLabels[$latestPayment['status']] ?? 'Status pembayaran diperbarui';
        } else {
            $paymentStatus = match ($order['payment_status']) {
                'verified' => 'Terverifikasi',
                'rejected' => 'Ditolak',
                default => 'Menunggu pembayaran QRIS',
            };
        }
        $orderStatusStyle = match ($order['order_status']) {
            'completed', 'ready' => 'success',
            'cancelled' => 'danger',
            default => 'warning',
        };
        $paymentStatusStyle = match (true) {
            $isCash, $order['payment_status'] === 'verified' => 'success',
            $order['payment_status'] === 'rejected' => 'danger',
            default => 'warning',
        };
        ?>
        <section class="card border-0 shadow-sm" aria-labelledby="order-details-heading">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h2 id="order-details-heading" class="h4 fw-bold mb-1">Rincian pesanan</h2>
                        <span class="small text-secondary">Kode pesanan</span>
                        <strong class="text-success d-block fs-5"><?= sanitize($order['order_code']) ?></strong>
                        <?php if ($accessToken !== ''): ?>
                            <span class="small text-secondary d-block mt-2">Token akses — simpan secara pribadi</span>
                            <code class="user-select-all"><?= sanitize($accessToken) ?></code>
                        <?php endif; ?>
                    </div>
                    <a href="<?= sanitize(BASE_URL) ?>/payment.php?code=<?= rawurlencode($order['order_code']) ?>&amp;token=<?= rawurlencode($accessToken) ?>"
                       class="btn btn-outline-success">Lihat pembayaran</a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <span class="small text-secondary d-block">Jenis pesanan</span>
                        <strong><?= $order['order_type'] === 'dine_in' ? 'Dine-in' : 'Takeaway' ?></strong>
                    </div>
                    <?php if ($order['order_type'] === 'dine_in' && is_string($order['table_number'])): ?>
                        <div class="col-6 col-lg-3">
                            <span class="small text-secondary d-block">Meja</span>
                            <strong><?= sanitize($order['table_number']) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="col-6 col-lg-3">
                        <span class="small text-secondary d-block">Metode pembayaran</span>
                        <strong><?= $isCash ? 'Cash' : 'QRIS' ?></strong>
                    </div>
                    <div class="col-6 col-lg-3">
                        <span class="small text-secondary d-block">Waktu pesanan</span>
                        <strong><?= sanitize(date('d/m/Y H:i', strtotime($order['created_at']))) ?></strong>
                    </div>
                    <div class="col-6 col-lg-3">
                        <span class="small text-secondary d-block">Status pesanan</span>
                        <span class="badge text-bg-<?= sanitize($orderStatusStyle) ?>"><?= sanitize($orderStatus) ?></span>
                    </div>
                    <div class="col-6 col-lg-3">
                        <span class="small text-secondary d-block">Status pembayaran</span>
                        <span class="badge text-bg-<?= sanitize($paymentStatusStyle) ?>"><?= sanitize($paymentStatus) ?></span>
                    </div>
                </div>

                <h3 class="h6 fw-bold mb-3">Menu yang dipesan</h3>
                <?php if ($items === []): ?>
                    <p class="text-secondary">Rincian item pesanan tidak tersedia.</p>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-content-between gap-3 border-bottom pb-3">
                                <div>
                                    <strong><?= sanitize($item['product_name']) ?></strong>
                                    <?php if (is_string($item['variant_name']) && $item['variant_name'] !== ''): ?>
                                        <span class="small text-secondary d-block"><?= sanitize($item['variant_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="small text-secondary"><?= (int) $item['quantity'] ?> × <?= sanitize(format_rupiah($item['price'])) ?></span>
                                </div>
                                <strong class="text-nowrap"><?= sanitize(format_rupiah($item['subtotal'])) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center gap-3 mt-4">
                    <span class="fw-semibold">Total</span>
                    <strong class="h5 text-success mb-0"><?= sanitize(format_rupiah($order['total'])) ?></strong>
                </div>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
