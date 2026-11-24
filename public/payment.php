<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

start_secure_session();

$orderCode = is_string($_GET['code'] ?? null) ? trim($_GET['code']) : '';
$errorMessage = null;
$uploadErrorMessage = null;
$successMessage = null;
$order = null;
$items = [];
$latestPayment = null;
$qrisImageUrl = null;
$qrisImageMessage = null;

$uploadFlash = $_SESSION['payment_upload_flash'] ?? null;
if (
    is_array($uploadFlash)
    && ($uploadFlash['order_code'] ?? null) === $orderCode
    && is_string($uploadFlash['type'] ?? null)
    && is_string($uploadFlash['message'] ?? null)
) {
    if ($uploadFlash['type'] === 'success') {
        $successMessage = $uploadFlash['message'];
    } elseif ($uploadFlash['type'] === 'danger') {
        $uploadErrorMessage = $uploadFlash['message'];
    }
    unset($_SESSION['payment_upload_flash']);
}

if (!is_valid_order_code($orderCode)) {
    http_response_code(400);
    $errorMessage = 'Kode pesanan tidak valid.';
} else {
    $orderQuery = $pdo->prepare(
        'SELECT o.id, o.order_code, o.order_type, o.table_id, o.subtotal, o.total,
                o.payment_method, o.payment_status, o.order_status, o.created_at,
                t.table_number
         FROM orders o
         LEFT JOIN `tables` t ON t.id = o.table_id
         WHERE o.order_code = :order_code'
    );
    $orderQuery->execute(['order_code' => $orderCode]);
    $order = $orderQuery->fetch();

    if ($order === false) {
        $order = null;
        http_response_code(404);
        $errorMessage = 'Pesanan tidak ditemukan. Periksa kembali kode pesananmu.';
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

        if ($order['payment_method'] === 'qris') {
            $configuredPath = CAFE_QRIS_IMAGE_PATH;
            if ($configuredPath === '') {
                $qrisImageMessage = 'Gambar QRIS belum dikonfigurasi. Hubungi pengelola kafe sebelum melakukan pembayaran.';
            } else {
                $pathSegments = explode('/', $configuredPath);
                $validPath = preg_match('/\A[a-zA-Z0-9._\/-]+\z/', $configuredPath) === 1
                    && $configuredPath[0] !== '/'
                    && !in_array('', $pathSegments, true)
                    && !in_array('.', $pathSegments, true)
                    && !in_array('..', $pathSegments, true);

                $publicRoot = realpath(__DIR__);
                $configuredFile = $validPath && is_string($publicRoot)
                    ? realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $configuredPath))
                    : false;
                $extension = is_string($configuredFile)
                    ? strtolower(pathinfo($configuredFile, PATHINFO_EXTENSION))
                    : '';
                $allowedMimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ];
                $isWithinPublicRoot = is_string($publicRoot)
                    && is_string($configuredFile)
                    && str_starts_with($configuredFile, $publicRoot . DIRECTORY_SEPARATOR);

                if (
                    !$isWithinPublicRoot
                    || !is_file($configuredFile)
                    || !is_readable($configuredFile)
                    || !isset($allowedMimeTypes[$extension])
                ) {
                    error_log('The configured cafe QRIS image path is invalid or unavailable.');
                    $qrisImageMessage = 'Gambar QRIS belum tersedia atau konfigurasi tidak valid. Hubungi pengelola kafe.';
                } else {
                    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = $fileInfo === false ? false : finfo_file($fileInfo, $configuredFile);
                    if ($fileInfo !== false) {
                        finfo_close($fileInfo);
                    }

                    if ($mimeType !== $allowedMimeTypes[$extension]) {
                        error_log('The configured cafe QRIS image MIME type does not match its extension.');
                        $qrisImageMessage = 'Gambar QRIS tidak dapat ditampilkan. Hubungi pengelola kafe.';
                    } else {
                        $qrisImageUrl = BASE_URL . '/'
                            . implode('/', array_map('rawurlencode', $pathSegments));
                    }
                }
            }
        }
    }
}

$csrfField = csrf_field();
$paymentStatusLabels = [
    'pending' => 'Menunggu verifikasi',
    'verified' => 'Terverifikasi',
    'rejected' => 'Ditolak',
];
$orderStatusLabels = [
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'ready' => 'Siap',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

$pageTitle = 'Pembayaran';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" aria-labelledby="payment-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Pesananmu</p>
        <h1 id="payment-heading" class="display-6 fw-bold mb-2">Pembayaran</h1>
        <p class="text-secondary mb-0">Simpan kode pesanan untuk memeriksa status kapan saja.</p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
        <a href="<?= sanitize(BASE_URL) ?>/check-order.php" class="btn btn-outline-success">Cek pesanan</a>
    <?php elseif ($order !== null): ?>
        <?php
        $isCash = $order['payment_method'] === 'cash';
        $paymentStatus = $isCash
            ? 'Bayar di kasir'
            : ($latestPayment !== null
                ? ($paymentStatusLabels[$latestPayment['status']] ?? 'Status pembayaran diperbarui')
                : match ($order['payment_status']) {
                    'verified' => 'Terverifikasi',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu pembayaran QRIS',
                });
        $orderStatus = $orderStatusLabels[$order['order_status']] ?? 'Status pesanan diperbarui';
        $latestPaymentStatus = is_array($latestPayment) ? $latestPayment['status'] : null;
        $canUploadProof = !$isCash
            && $order['order_status'] !== 'cancelled'
            && $order['payment_status'] !== 'verified'
            && $latestPaymentStatus !== 'pending'
            && $latestPaymentStatus !== 'verified';
        ?>
        <?php if ($successMessage !== null): ?>
            <div class="alert alert-success" role="status"><?= sanitize($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($uploadErrorMessage !== null): ?>
            <div class="alert alert-danger" role="alert"><?= sanitize($uploadErrorMessage) ?></div>
        <?php endif; ?>

        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <section class="card border-0 shadow-sm" aria-labelledby="order-summary-heading">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <h2 id="order-summary-heading" class="h5 fw-bold mb-1">Rincian pesanan</h2>
                                <p class="small text-secondary mb-0">Kode pesanan</p>
                                <strong class="text-success fs-5"><?= sanitize($order['order_code']) ?></strong>
                            </div>
                            <a href="<?= sanitize(BASE_URL) ?>/check-order.php?code=<?= rawurlencode($order['order_code']) ?>"
                               class="btn btn-sm btn-outline-success">Cek status</a>
                        </div>

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

                        <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
                            <span class="fw-semibold">Total pembayaran</span>
                            <strong class="h5 text-success mb-0"><?= sanitize(format_rupiah($order['total'])) ?></strong>
                        </div>
                        <div class="row g-3 mt-2 small">
                            <div class="col-6">
                                <span class="text-secondary d-block">Jenis pesanan</span>
                                <strong><?= $order['order_type'] === 'dine_in' ? 'Dine-in' : 'Takeaway' ?></strong>
                            </div>
                            <?php if ($order['order_type'] === 'dine_in' && is_string($order['table_number'])): ?>
                                <div class="col-6">
                                    <span class="text-secondary d-block">Meja</span>
                                    <strong><?= sanitize($order['table_number']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="col-6">
                                <span class="text-secondary d-block">Status pesanan</span>
                                <strong><?= sanitize($orderStatus) ?></strong>
                            </div>
                            <div class="col-6">
                                <span class="text-secondary d-block">Status pembayaran</span>
                                <strong><?= sanitize($paymentStatus) ?></strong>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <?php if ($isCash): ?>
                    <section class="contact-panel p-4" aria-labelledby="cash-heading">
                        <i class="bi bi-cash-coin display-5 text-success" aria-hidden="true"></i>
                        <h2 id="cash-heading" class="h5 fw-bold mt-3">Pembayaran Cash</h2>
                        <p class="text-secondary mb-0">Silakan lakukan pembayaran langsung di kasir saat mengambil atau menikmati pesanan.</p>
                    </section>
                <?php else: ?>
                    <section class="card border-0 shadow-sm" aria-labelledby="qris-heading">
                        <div class="card-body p-4">
                            <h2 id="qris-heading" class="h5 fw-bold">Pembayaran QRIS</h2>
                            <p class="text-secondary">Bayar tepat sebesar total pesanan berikut.</p>
                            <p class="h4 text-success fw-bold"><?= sanitize(format_rupiah($order['total'])) ?></p>

                            <?php if ($qrisImageUrl !== null): ?>
                                <img
                                    src="<?= sanitize($qrisImageUrl) ?>"
                                    alt="Kode QRIS resmi <?= sanitize(APP_NAME) ?>"
                                    class="img-fluid rounded border mb-3"
                                    style="max-width: 24rem"
                                >
                                <?php if ($latestPaymentStatus === 'pending'): ?>
                                    <div class="alert alert-info mb-0" role="status">
                                        Bukti pembayaran sudah diterima dan sedang menunggu verifikasi.
                                    </div>
                                <?php elseif ($latestPaymentStatus === 'verified' || $order['payment_status'] === 'verified'): ?>
                                    <div class="alert alert-success mb-0" role="status">Pembayaran telah terverifikasi.</div>
                                <?php elseif ($latestPaymentStatus === 'rejected' || $order['payment_status'] === 'rejected'): ?>
                                    <div class="alert alert-warning" role="alert">
                                        Bukti pembayaran sebelumnya ditolak. Periksa kembali pembayaranmu sebelum mengunggah bukti baru.
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['order_status'] === 'cancelled'): ?>
                                    <div class="alert alert-secondary mt-3 mb-0" role="status">
                                        Pesanan ini dibatalkan sehingga bukti pembayaran tidak dapat diunggah.
                                    </div>
                                <?php elseif ($canUploadProof): ?>
                                    <form method="post" action="<?= sanitize(BASE_URL) ?>/upload-proof.php" enctype="multipart/form-data" class="mt-3">
                                        <?= $csrfField ?>
                                        <input type="hidden" name="order_code" value="<?= sanitize($order['order_code']) ?>">
                                        <div class="mb-3">
                                            <label for="payment-proof" class="form-label">Bukti pembayaran</label>
                                            <input
                                                type="file"
                                                class="form-control"
                                                id="payment-proof"
                                                name="proof_image"
                                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                required
                                            >
                                            <div class="form-text">JPG, PNG, atau WEBP; ukuran maksimal 2 MB.</div>
                                        </div>
                                        <button type="submit" class="btn btn-success w-100">
                                            Kirim bukti pembayaran
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0" role="alert"><?= sanitize($qrisImageMessage ?? 'Gambar QRIS tidak tersedia.') ?></div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
