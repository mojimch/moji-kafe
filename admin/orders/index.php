<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

$statusOptions = [
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'ready' => 'Siap',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
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
    $conditions[] = 'o.order_status = :status';
    $parameters['status'] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = 'o.order_code LIKE :order_code';
    $parameters['order_code'] = '%' . $search . '%';
}
$whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

$countQuery = $pdo->prepare('SELECT COUNT(*) FROM orders o' . $whereSql);
$countQuery->execute($parameters);
$totalOrders = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$ordersQuery = $pdo->prepare(
    'SELECT o.id, o.order_code, o.customer_name, o.customer_phone, o.order_type,
            o.total, o.payment_method, o.payment_status, o.order_status, o.created_at,
            t.table_number, COALESCE(i.item_count, 0) AS item_count
     FROM orders o
     LEFT JOIN `tables` t ON t.id = o.table_id
     LEFT JOIN (
         SELECT order_id, SUM(quantity) AS item_count
         FROM order_items
         GROUP BY order_id
     ) i ON i.order_id = o.id'
    . $whereSql .
    ' ORDER BY o.created_at DESC, o.id DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $name => $value) {
    $ordersQuery->bindValue(':' . $name, $value, PDO::PARAM_STR);
}
$ordersQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$ordersQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersQuery->execute();
$orders = $ordersQuery->fetchAll();

$pageTitle = 'Pesanan';
require __DIR__ . '/../../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h5 fw-bold mb-1">Daftar pesanan</h2>
        <p class="text-secondary small mb-0"><?= $totalOrders ?> pesanan ditemukan</p>
    </div>
</div>

<section class="card border-0 shadow-sm mb-4" aria-label="Filter pesanan">
    <div class="card-body p-3 p-lg-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="order-search" class="form-label">Kode pesanan</label>
                <input
                    type="search"
                    class="form-control"
                    id="order-search"
                    name="code"
                    value="<?= sanitize($search) ?>"
                    placeholder="ORD-..."
                    maxlength="32"
                >
            </div>
            <div class="col-12 col-md-4">
                <label for="order-status" class="form-label">Status pesanan</label>
                <select class="form-select" id="order-status" name="status">
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
                <a href="<?= sanitize(admin_base_url()) ?>/orders/index.php" class="btn btn-outline-secondary">Reset</a>
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
                    <th scope="col">Jenis / Meja</th>
                    <th scope="col">Pembayaran</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end pe-4">Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="text-center text-secondary py-5">Belum ada pesanan yang cocok.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $orderStatusStyle = match ($order['order_status']) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'ready' => 'primary',
                        default => 'warning',
                    };
                    $paymentStatusLabels = [
                        'pending' => 'Menunggu',
                        'verified' => 'Terverifikasi',
                        'rejected' => 'Ditolak',
                    ];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <a class="fw-semibold text-decoration-none" href="<?= sanitize(admin_base_url()) ?>/orders/detail.php?id=<?= (int) $order['id'] ?>">
                                <?= sanitize($order['order_code']) ?>
                            </a>
                            <small class="d-block text-secondary"><?= sanitize(date('d/m/Y H:i', strtotime($order['created_at']))) ?></small>
                            <small class="d-block text-secondary"><?= (int) $order['item_count'] ?> item</small>
                        </td>
                        <td>
                            <span class="fw-medium"><?= sanitize($order['customer_name']) ?></span>
                            <small class="d-block text-secondary"><?= sanitize($order['customer_phone']) ?></small>
                        </td>
                        <td>
                            <?= $order['order_type'] === 'dine_in' ? 'Dine-in' : 'Takeaway' ?>
                            <?php if ($order['order_type'] === 'dine_in' && is_string($order['table_number'])): ?>
                                <small class="d-block text-secondary"><?= sanitize($order['table_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="d-block"><?= $order['payment_method'] === 'qris' ? 'QRIS' : 'Cash' ?></span>
                            <small class="text-secondary"><?= sanitize($paymentStatusLabels[$order['payment_status']] ?? ucfirst((string) $order['payment_status'])) ?></small>
                        </td>
                        <td class="text-nowrap fw-semibold"><?= sanitize(format_rupiah($order['total'])) ?></td>
                        <td>
                            <span class="badge text-bg-<?= sanitize($orderStatusStyle) ?>">
                                <?= sanitize($statusOptions[$order['order_status']] ?? ucfirst((string) $order['order_status'])) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="<?= sanitize(admin_base_url()) ?>/orders/detail.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-success">
                                Lihat detail
                            </a>
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
