<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$periodOptions = [
    'daily' => 'Harian',
    'weekly' => 'Mingguan',
    'monthly' => 'Bulanan',
];
$period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'daily';
if (!array_key_exists($period, $periodOptions)) {
    $period = 'daily';
}

$dateInput = is_string($_GET['date'] ?? null) ? trim($_GET['date']) : date('Y-m-d');
$referenceDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
$dateErrors = DateTimeImmutable::getLastErrors();
if (
    $referenceDate === false
    || $referenceDate->format('Y-m-d') !== $dateInput
    || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
) {
    $referenceDate = new DateTimeImmutable('today');
    $dateInput = $referenceDate->format('Y-m-d');
}

if ($period === 'weekly') {
    $periodStart = $referenceDate->modify('-' . ((int) $referenceDate->format('N') - 1) . ' days');
    $periodEnd = $periodStart->modify('+1 week');
} elseif ($period === 'monthly') {
    $periodStart = $referenceDate->modify('first day of this month');
    $periodEnd = $periodStart->modify('+1 month');
} else {
    $periodStart = $referenceDate;
    $periodEnd = $periodStart->modify('+1 day');
}
$rangeParameters = [
    'start' => $periodStart->format('Y-m-d 00:00:00'),
    'end' => $periodEnd->format('Y-m-d 00:00:00'),
];
$salesPredicate = "(o.order_status = 'completed' AND (o.payment_method = 'cash' OR (o.payment_method = 'qris' AND o.payment_status = 'verified')))";

$summaryQuery = $pdo->prepare(
    'SELECT COUNT(*) AS order_count, COALESCE(SUM(o.total), 0) AS revenue
     FROM orders o
     WHERE o.created_at >= :start AND o.created_at < :end
       AND ' . $salesPredicate
);
$summaryQuery->execute($rangeParameters);
$summary = $summaryQuery->fetch();

$bestSellersQuery = $pdo->prepare(
    'SELECT oi.product_name, oi.variant_name,
            SUM(oi.quantity) AS quantity_sold,
            SUM(oi.subtotal) AS sales_total
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at >= :start AND o.created_at < :end
       AND ' . $salesPredicate . '
     GROUP BY oi.product_name, oi.variant_name
     ORDER BY quantity_sold DESC, sales_total DESC, oi.product_name
     LIMIT 10'
);
$bestSellersQuery->execute($rangeParameters);
$bestSellers = $bestSellersQuery->fetchAll();

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : (int) $page;
$pageSize = 25;
$countQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM orders o
     WHERE o.created_at >= :start AND o.created_at < :end
       AND ' . $salesPredicate
);
$countQuery->execute($rangeParameters);
$totalRows = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$ordersQuery = $pdo->prepare(
    'SELECT o.id, o.order_code, o.customer_name, o.payment_method, o.total, o.created_at,
            COALESCE(items.item_count, 0) AS item_count
     FROM orders o
     LEFT JOIN (
         SELECT order_id, SUM(quantity) AS item_count
         FROM order_items
         GROUP BY order_id
     ) items ON items.order_id = o.id
     WHERE o.created_at >= :start AND o.created_at < :end
       AND ' . $salesPredicate . '
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT :limit OFFSET :offset'
);
$ordersQuery->bindValue(':start', $rangeParameters['start'], PDO::PARAM_STR);
$ordersQuery->bindValue(':end', $rangeParameters['end'], PDO::PARAM_STR);
$ordersQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$ordersQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersQuery->execute();
$orders = $ordersQuery->fetchAll();

$rangeLabel = match ($period) {
    'weekly' => $periodStart->format('d/m/Y') . ' – ' . $periodEnd->modify('-1 day')->format('d/m/Y'),
    'monthly' => $periodStart->format('m/Y'),
    default => $periodStart->format('d/m/Y'),
};
$queryParameters = [
    'period' => $period,
    'date' => $dateInput,
];

$pageTitle = 'Laporan Penjualan';
require __DIR__ . '/../../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <p class="text-uppercase small fw-semibold text-success mb-1">Ringkasan transaksi selesai</p>
        <h2 class="h4 fw-bold mb-1">Laporan penjualan</h2>
        <p class="text-secondary mb-0">Periode laporan: <?= sanitize($rangeLabel) ?></p>
    </div>
    <a href="<?= sanitize(admin_base_url()) ?>/dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke dashboard
    </a>
</div>

<section class="card border-0 shadow-sm mb-4" aria-label="Filter laporan penjualan">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="report-period" class="form-label">Rentang laporan</label>
                <select id="report-period" name="period" class="form-select">
                    <?php foreach ($periodOptions as $value => $label): ?>
                        <option value="<?= sanitize($value) ?>" <?= $period === $value ? 'selected' : '' ?>>
                            <?= sanitize($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label for="report-date" class="form-label">Tanggal acuan</label>
                <input type="date" id="report-date" name="date" value="<?= sanitize($dateInput) ?>" class="form-control" required>
                <div class="form-text">Laporan mingguan dimulai Senin; laporan bulanan mengikuti bulan tanggal ini.</div>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-success">Tampilkan</button>
            </div>
        </form>
    </div>
</section>

<section class="row g-3 mb-4" aria-label="Ringkasan laporan">
    <div class="col-12 col-md-6">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <span class="text-secondary small">Total pesanan selesai</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= (int) $summary['order_count'] ?></p>
            </div>
        </article>
    </div>
    <div class="col-12 col-md-6">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <span class="text-secondary small">Total pendapatan terkonfirmasi</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= sanitize(format_rupiah($summary['revenue'])) ?></p>
            </div>
        </article>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4" aria-labelledby="best-sellers-heading">
    <div class="card-header bg-white border-0 p-3 p-lg-4">
        <h2 id="best-sellers-heading" class="h5 fw-bold mb-1">Produk terlaris</h2>
        <p class="text-secondary small mb-0">Diurutkan berdasarkan jumlah item pada pesanan selesai.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Produk</th>
                    <th scope="col">Varian</th>
                    <th scope="col">Terjual</th>
                    <th scope="col" class="text-end pe-4">Nilai penjualan</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($bestSellers === []): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada penjualan pada periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($bestSellers as $product): ?>
                    <tr>
                        <td class="ps-4 fw-medium"><?= sanitize($product['product_name']) ?></td>
                        <td><?= sanitize(is_string($product['variant_name']) && $product['variant_name'] !== '' ? $product['variant_name'] : '—') ?></td>
                        <td><?= (int) $product['quantity_sold'] ?></td>
                        <td class="text-end pe-4 text-nowrap"><?= sanitize(format_rupiah($product['sales_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card border-0 shadow-sm" aria-labelledby="sales-details-heading">
    <div class="card-header bg-white border-0 p-3 p-lg-4">
        <h2 id="sales-details-heading" class="h5 fw-bold mb-1">Rincian penjualan</h2>
        <p class="text-secondary small mb-0"><?= $totalRows ?> pesanan selesai pada periode ini.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Kode pesanan</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Item</th>
                    <th scope="col">Pembayaran</th>
                    <th scope="col">Total</th>
                    <th scope="col" class="pe-4">Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders === []): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pesanan selesai pada periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="ps-4">
                            <a class="fw-semibold text-decoration-none"
                               href="<?= sanitize(admin_base_url()) ?>/orders/detail.php?id=<?= (int) $order['id'] ?>">
                                <?= sanitize($order['order_code']) ?>
                            </a>
                        </td>
                        <td><?= sanitize($order['customer_name']) ?></td>
                        <td><?= (int) $order['item_count'] ?></td>
                        <td><?= $order['payment_method'] === 'qris' ? 'QRIS' : 'Cash' ?></td>
                        <td class="text-nowrap fw-semibold"><?= sanitize(format_rupiah($order['total'])) ?></td>
                        <td class="pe-4 text-nowrap"><?= sanitize(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
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
                       href="?<?= sanitize(http_build_query($queryParameters + ['page' => $page - 1])) ?>">Sebelumnya</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="?<?= sanitize(http_build_query($queryParameters + ['page' => $page + 1])) ?>">Berikutnya</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/admin_footer.php'; ?>
