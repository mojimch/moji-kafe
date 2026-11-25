<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$salesPredicate = "(order_status = 'completed' AND (payment_method = 'cash' OR (payment_method = 'qris' AND payment_status = 'verified')))";
$todayOrderQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
);
$todayOrderQuery->execute();
$todayOrderCount = (int) $todayOrderQuery->fetchColumn();

$todayRevenueQuery = $pdo->prepare(
    'SELECT COALESCE(SUM(total), 0)
     FROM orders
     WHERE created_at >= CURDATE()
       AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
       AND ' . $salesPredicate
);
$todayRevenueQuery->execute();
$todayRevenue = $todayRevenueQuery->fetchColumn();

$activeBookingQuery = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings WHERE status IN ('pending', 'confirmed', 'in_use')"
);
$activeBookingQuery->execute();
$activeBookingCount = (int) $activeBookingQuery->fetchColumn();

$availableTableQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM `tables` t
     WHERE t.is_active = 1
       AND NOT EXISTS (
           SELECT 1
           FROM bookings b
           WHERE b.table_id = t.id
             AND b.status IN ('pending', 'confirmed', 'in_use')
             AND TIMESTAMP(b.booking_date, b.start_time) <= NOW()
             AND TIMESTAMP(
                 CASE
                     WHEN b.end_time <= b.start_time THEN DATE_ADD(b.booking_date, INTERVAL 1 DAY)
                     ELSE b.booking_date
                 END,
                 b.end_time
             ) > NOW()
       )"
);
$availableTableQuery->execute();
$availableTableCount = (int) $availableTableQuery->fetchColumn();

$unavailableProductQuery = $pdo->prepare('SELECT COUNT(*) FROM products WHERE is_available = 0');
$unavailableProductQuery->execute();
$unavailableProductCount = (int) $unavailableProductQuery->fetchColumn();

$recentOrdersQuery = $pdo->prepare(
    'SELECT id, order_code, customer_name, total, payment_method, payment_status, order_status, created_at
     FROM orders
     ORDER BY created_at DESC, id DESC
     LIMIT 5'
);
$recentOrdersQuery->execute();
$recentOrders = $recentOrdersQuery->fetchAll();

$today = new DateTimeImmutable('today');
$currentMonday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
$currentMonth = $today->modify('first day of this month');
$chartPeriods = [
    'daily' => [
        'start' => $today->modify('-13 days'),
        'end' => $today->modify('+1 day'),
        'count' => 14,
        'unit' => 'day',
        'bucket' => 'DATE(created_at)',
        'date_format' => 'd/m',
    ],
    'weekly' => [
        'start' => $currentMonday->modify('-7 weeks'),
        'end' => $currentMonday->modify('+1 week'),
        'count' => 8,
        'unit' => 'week',
        'bucket' => 'DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY)',
        'date_format' => 'd/m',
    ],
    'monthly' => [
        'start' => $currentMonth->modify('-11 months'),
        'end' => $currentMonth->modify('+1 month'),
        'count' => 12,
        'unit' => 'month',
        'bucket' => "DATE_FORMAT(created_at, '%Y-%m')",
        'date_format' => 'm/Y',
    ],
];
$chartData = [];
foreach ($chartPeriods as $periodName => $period) {
    $seriesQuery = $pdo->prepare(
        'SELECT ' . $period['bucket'] . ' AS bucket,
                SUM(CASE WHEN ' . $salesPredicate . ' THEN 1 ELSE 0 END) AS order_count,
                COALESCE(SUM(CASE WHEN ' . $salesPredicate . ' THEN total ELSE 0 END), 0) AS revenue
         FROM orders
         WHERE created_at >= :start AND created_at < :end
         GROUP BY bucket
         ORDER BY bucket'
    );
    $seriesQuery->execute([
        'start' => $period['start']->format('Y-m-d 00:00:00'),
        'end' => $period['end']->format('Y-m-d 00:00:00'),
    ]);
    $rowsByBucket = [];
    foreach ($seriesQuery->fetchAll() as $row) {
        $rowsByBucket[(string) $row['bucket']] = $row;
    }

    $labels = [];
    $orderCounts = [];
    $revenues = [];
    for ($index = 0; $index < $period['count']; $index++) {
        $bucketDate = $period['start']->modify('+' . $index . ' ' . $period['unit']);
        $bucketKey = $periodName === 'monthly'
            ? $bucketDate->format('Y-m')
            : $bucketDate->format('Y-m-d');
        $bucketRow = $rowsByBucket[$bucketKey] ?? null;
        $labels[] = $bucketDate->format($period['date_format']);
        $orderCounts[] = $bucketRow === null ? 0 : (int) $bucketRow['order_count'];
        $revenues[] = $bucketRow === null ? 0 : (float) $bucketRow['revenue'];
    }

    $chartData[$periodName] = [
        'labels' => $labels,
        'orders' => $orderCounts,
        'revenue' => $revenues,
    ];
}

$chartDataJson = json_encode(
    $chartData,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
$orderStatusLabels = [
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'ready' => 'Siap',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
$quickLinks = [
    ['label' => 'Daftar Pesanan', 'description' => 'Lihat pesanan masuk dan ubah statusnya.', 'path' => 'orders/index.php', 'icon' => 'bi-receipt'],
    ['label' => 'Reservasi Meja', 'description' => 'Periksa jadwal dan kelola reservasi.', 'path' => 'bookings/index.php', 'icon' => 'bi-calendar2-check'],
    ['label' => 'Pembayaran QRIS', 'description' => 'Tinjau dan verifikasi bukti pembayaran.', 'path' => 'payments/index.php', 'icon' => 'bi-qr-code'],
    ['label' => 'Laporan Penjualan', 'description' => 'Rangkuman penjualan berdasarkan periode.', 'path' => 'reports/index.php', 'icon' => 'bi-bar-chart-line'],
    ['label' => 'Menu Produk', 'description' => 'Kelola produk dan ketersediaannya.', 'path' => 'products/index.php', 'icon' => 'bi-cup-straw'],
    ['label' => 'Kategori', 'description' => 'Kelola kategori menu.', 'path' => 'categories/index.php', 'icon' => 'bi-grid'],
    ['label' => 'Varian', 'description' => 'Kelola pilihan dan harga varian.', 'path' => 'variants/index.php', 'icon' => 'bi-sliders'],
    ['label' => 'Data Meja', 'description' => 'Kelola meja dan kapasitasnya.', 'path' => 'tables/index.php', 'icon' => 'bi-table'],
];

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <p class="text-uppercase small fw-semibold text-success mb-1">Ringkasan operasional</p>
        <h2 class="h4 fw-bold mb-0">Selamat datang, <?= sanitize((string) $_SESSION['admin_name']) ?>.</h2>
    </div>
    <span class="text-secondary small">Data hari ini · <?= sanitize($today->format('d/m/Y')) ?></span>
</div>

<section class="row g-3 mb-4" aria-label="Ringkasan hari ini">
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <span class="text-secondary small">Total pesanan hari ini</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= $todayOrderCount ?></p>
                <i class="bi bi-receipt text-success fs-4 position-absolute top-0 end-0 m-3" aria-hidden="true"></i>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body position-relative">
                <span class="text-secondary small">Pendapatan terkonfirmasi hari ini</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= sanitize(format_rupiah($todayRevenue)) ?></p>
                <i class="bi bi-cash-coin text-success fs-4 position-absolute top-0 end-0 m-3" aria-hidden="true"></i>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body position-relative">
                <span class="text-secondary small">Reservasi aktif</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= $activeBookingCount ?></p>
                <i class="bi bi-calendar2-check text-success fs-4 position-absolute top-0 end-0 m-3" aria-hidden="true"></i>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body position-relative">
                <span class="text-secondary small">Meja tersedia saat ini</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= $availableTableCount ?></p>
                <i class="bi bi-table text-success fs-4 position-absolute top-0 end-0 m-3" aria-hidden="true"></i>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="card h-100 border-0 shadow-sm">
            <div class="card-body position-relative">
                <span class="text-secondary small">Menu nonaktif</span>
                <p class="h3 fw-bold mb-0 mt-2"><?= $unavailableProductCount ?></p>
                <i class="bi bi-cup-hot text-success fs-4 position-absolute top-0 end-0 m-3" aria-hidden="true"></i>
            </div>
        </article>
    </div>
</section>

<section class="mb-4" aria-labelledby="quick-links-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 id="quick-links-heading" class="h5 fw-bold mb-1">Akses cepat</h2>
            <p class="text-secondary small mb-0">Buka bagian admin langsung dari halaman utama.</p>
        </div>
    </div>
    <div class="row g-3">
        <?php foreach ($quickLinks as $link): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?= sanitize(admin_base_url()) ?>/<?= sanitize($link['path']) ?>"
                   class="card h-100 border-0 shadow-sm text-decoration-none text-body">
                    <div class="card-body d-flex align-items-start gap-3">
                        <span class="rounded-3 bg-success-subtle text-success p-3 fs-5">
                            <i class="bi <?= sanitize($link['icon']) ?>" aria-hidden="true"></i>
                        </span>
                        <span>
                            <strong class="d-block mb-1"><?= sanitize($link['label']) ?></strong>
                            <span class="small text-secondary"><?= sanitize($link['description']) ?></span>
                        </span>
                        <i class="bi bi-arrow-up-right ms-auto text-secondary" aria-hidden="true"></i>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4" aria-labelledby="sales-chart-heading">
    <div class="card-header bg-white border-0 p-3 p-lg-4 pb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 id="sales-chart-heading" class="h5 fw-bold mb-1">Grafik penjualan</h2>
                <p class="text-secondary small mb-0">Pesanan selesai dan pendapatan terkonfirmasi.</p>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Periode grafik penjualan">
                <button type="button" class="btn btn-outline-success active" data-chart-period="daily" aria-pressed="true">Harian</button>
                <button type="button" class="btn btn-outline-success" data-chart-period="weekly" aria-pressed="false">Mingguan</button>
                <button type="button" class="btn btn-outline-success" data-chart-period="monthly" aria-pressed="false">Bulanan</button>
            </div>
        </div>
    </div>
    <div class="card-body p-3 p-lg-4">
        <div style="height: 20rem">
            <canvas id="sales-chart" role="img" aria-label="Grafik jumlah pesanan dan pendapatan"></canvas>
            <div id="sales-chart-fallback" class="d-none overflow-auto" role="img" aria-label="Grafik jumlah pesanan dan pendapatan">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-secondary mt-2">
            <span><i class="bi bi-square-fill text-success me-1" aria-hidden="true"></i>Pesanan</span>
            <span><i class="bi bi-square-fill text-warning me-1" aria-hidden="true"></i>Pendapatan</span>
        </div>
    </div>
</section>

<section class="card border-0 shadow-sm" aria-labelledby="recent-orders-heading">
    <div class="card-header bg-white border-0 p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 id="recent-orders-heading" class="h5 fw-bold mb-1">Pesanan terbaru</h2>
            <p class="text-secondary small mb-0">Lima transaksi terakhir yang masuk.</p>
        </div>
        <a href="<?= sanitize(admin_base_url()) ?>/orders/index.php" class="btn btn-sm btn-outline-success">Lihat semua pesanan</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="ps-4">Pesanan</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Pembayaran</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pe-4">Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentOrders === []): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pesanan.</td></tr>
            <?php else: ?>
                <?php foreach ($recentOrders as $order): ?>
                    <?php
                    $statusStyle = match ($order['order_status']) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'ready' => 'primary',
                        default => 'warning',
                    };
                    ?>
                    <tr>
                        <td class="ps-4">
                            <a class="fw-semibold text-decoration-none"
                               href="<?= sanitize(admin_base_url()) ?>/orders/detail.php?id=<?= (int) $order['id'] ?>">
                                <?= sanitize($order['order_code']) ?>
                            </a>
                        </td>
                        <td><?= sanitize($order['customer_name']) ?></td>
                        <td><?= $order['payment_method'] === 'qris' ? 'QRIS' : 'Cash' ?></td>
                        <td class="text-nowrap"><?= sanitize(format_rupiah($order['total'])) ?></td>
                        <td>
                            <span class="badge text-bg-<?= sanitize($statusStyle) ?>">
                                <?= sanitize($orderStatusLabels[$order['order_status']] ?? ucfirst((string) $order['order_status'])) ?>
                            </span>
                        </td>
                        <td class="pe-4 text-nowrap"><?= sanitize(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
    const salesChartData = <?= $chartDataJson ?>;
    const salesCanvas = document.getElementById('sales-chart');
    const salesFallback = document.getElementById('sales-chart-fallback');
    let salesChart = null;

    const renderSalesFallback = (period) => {
        salesCanvas.classList.add('d-none');
        salesFallback.classList.remove('d-none');
        salesFallback.replaceChildren();

        const values = salesChartData[period];
        const maxOrders = Math.max(1, ...values.orders);
        const maxRevenue = Math.max(1, ...values.revenue);
        const formatter = new Intl.NumberFormat('id-ID');
        values.labels.forEach((label, index) => {
            const row = document.createElement('div');
            row.className = 'mb-2';

            const caption = document.createElement('div');
            caption.className = 'd-flex justify-content-between small text-secondary';
            const dateLabel = document.createElement('span');
            dateLabel.textContent = label;
            const summary = document.createElement('span');
            summary.textContent = values.orders[index] + ' pesanan · Rp ' + formatter.format(values.revenue[index]);
            caption.append(dateLabel, summary);

            const orderBar = document.createElement('div');
            orderBar.className = 'progress mt-1';
            orderBar.style.height = '.35rem';
            orderBar.setAttribute('aria-label', values.orders[index] + ' pesanan');
            const orderFill = document.createElement('div');
            orderFill.className = 'progress-bar bg-success';
            orderFill.style.width = (values.orders[index] / maxOrders * 100) + '%';
            orderBar.append(orderFill);

            const revenueBar = document.createElement('div');
            revenueBar.className = 'progress mt-1';
            revenueBar.style.height = '.35rem';
            revenueBar.setAttribute('aria-label', 'Pendapatan Rp ' + formatter.format(values.revenue[index]));
            const revenueFill = document.createElement('div');
            revenueFill.className = 'progress-bar bg-warning';
            revenueFill.style.width = (values.revenue[index] / maxRevenue * 100) + '%';
            revenueBar.append(revenueFill);

            row.append(caption, orderBar, revenueBar);
            salesFallback.append(row);
        });
    };

    if (typeof Chart !== 'undefined') {
        salesChart = new Chart(salesCanvas, {
            type: 'bar',
            data: {
                labels: salesChartData.daily.labels,
                datasets: [
                    {
                        label: 'Pesanan selesai',
                        data: salesChartData.daily.orders,
                        backgroundColor: 'rgba(37, 75, 59, .75)',
                        borderRadius: 4,
                        yAxisID: 'orders',
                    },
                    {
                        type: 'line',
                        label: 'Pendapatan',
                        data: salesChartData.daily.revenue,
                        borderColor: '#d09b4a',
                        backgroundColor: '#d09b4a',
                        pointRadius: 3,
                        tension: .25,
                        yAxisID: 'revenue',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    orders: { beginAtZero: true, position: 'left', title: { display: true, text: 'Pesanan' }, ticks: { precision: 0 } },
                    revenue: { beginAtZero: true, position: 'right', title: { display: true, text: 'Pendapatan (Rp)' }, grid: { drawOnChartArea: false } },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.yAxisID === 'revenue'
                                ? 'Pendapatan: Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y)
                                : 'Pesanan: ' + context.parsed.y,
                        },
                    },
                },
            },
        });
    } else {
        renderSalesFallback('daily');
    }

    document.querySelectorAll('[data-chart-period]').forEach((button) => {
        button.addEventListener('click', () => {
            const period = button.dataset.chartPeriod;
            if (!period || !salesChartData[period]) {
                return;
            }

            document.querySelectorAll('[data-chart-period]').forEach((periodButton) => {
                const isActive = periodButton === button;
                periodButton.classList.toggle('active', isActive);
                periodButton.setAttribute('aria-pressed', String(isActive));
            });
            if (salesChart) {
                salesChart.data.labels = salesChartData[period].labels;
                salesChart.data.datasets[0].data = salesChartData[period].orders;
                salesChart.data.datasets[1].data = salesChartData[period].revenue;
                salesChart.update();
            } else {
                renderSalesFallback(period);
            }
        });
    });
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
