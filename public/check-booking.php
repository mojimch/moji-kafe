<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$bookingCode = is_string($_GET['code'] ?? null) ? trim($_GET['code']) : '';
$accessToken = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
$errorMessage = null;
$booking = null;

if (array_key_exists('code', $_GET) && $bookingCode !== '') {
    if (!is_valid_booking_code($bookingCode)) {
        http_response_code(400);
        $errorMessage = 'Format kode reservasi tidak valid. Gunakan format BOOK-YYYYMMDD-XXX.';
    } else {
        $bookingQuery = $pdo->prepare(
            'SELECT b.booking_code, b.access_token_hash, b.booking_date, b.start_time, b.end_time, b.guest_count,
                    b.status, b.created_at, t.table_number
             FROM bookings b
             INNER JOIN `tables` t ON t.id = b.table_id
             WHERE b.booking_code = :booking_code'
        );
        $bookingQuery->execute(['booking_code' => $bookingCode]);
        $booking = $bookingQuery->fetch();

        if (
            $booking === false
            || !verify_public_access($accessToken, $booking['access_token_hash'], $booking['created_at'])
        ) {
            $booking = null;
            http_response_code(404);
            $errorMessage = 'Reservasi tidak ditemukan atau token akses tidak valid.';
        }
    }
}

$statusLabels = [
    'pending' => 'Menunggu',
    'confirmed' => 'Dikonfirmasi',
    'in_use' => 'Sedang digunakan',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
$statusStyles = [
    'pending' => 'warning',
    'confirmed' => 'success',
    'in_use' => 'primary',
    'completed' => 'secondary',
    'cancelled' => 'danger',
];

$pageTitle = 'Cek Reservasi';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" aria-labelledby="check-booking-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Informasi kunjunganmu</p>
        <h1 id="check-booking-heading" class="display-6 fw-bold mb-2">Cek status reservasi</h1>
        <p class="text-secondary mb-0">Masukkan kode reservasi dan token akses yang diberikan setelah permintaan berhasil dicatat.</p>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-label="Form pencarian reservasi">
        <div class="card-body p-4">
            <form method="get" action="<?= sanitize(BASE_URL) ?>/check-booking.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-8">
                    <label for="booking-code" class="form-label">Kode reservasi</label>
                    <input
                        type="text"
                        class="form-control text-uppercase"
                        id="booking-code"
                        name="code"
                        value="<?= sanitize($bookingCode) ?>"
                        placeholder="BOOK-YYYYMMDD-XXX"
                        pattern="BOOK-[0-9]{8}-[0-9]{3}"
                        maxlength="17"
                        required
                    >
                </div>
                <div class="col-12 col-md-8">
                    <label for="booking-access-token" class="form-label">Token akses</label>
                    <input
                        type="text"
                        class="form-control"
                        id="booking-access-token"
                        name="token"
                        value="<?= sanitize($accessToken) ?>"
                        pattern="[a-f0-9]{64}"
                        maxlength="64"
                        autocomplete="off"
                    >
                    <div class="form-text">Wajib untuk reservasi baru. Kode lama tanpa token hanya dapat dicek selama masa transisi 7 hari.</div>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>Cari reservasi
                    </button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-warning" role="alert"><?= sanitize($errorMessage) ?></div>
    <?php elseif ($booking !== null): ?>
        <?php
        $status = $booking['status'];
        $statusLabel = $statusLabels[$status] ?? 'Status reservasi diperbarui';
        $statusStyle = $statusStyles[$status] ?? 'secondary';
        $bookingStart = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['start_time']);
        $bookingEnd = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['end_time']);
        if ($bookingEnd <= $bookingStart) {
            $bookingEnd = $bookingEnd->modify('+1 day');
        }
        ?>
        <section class="card border-0 shadow-sm" aria-labelledby="booking-details-heading">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h2 id="booking-details-heading" class="h4 fw-bold mb-1">Rincian reservasi</h2>
                        <span class="small text-secondary">Kode reservasi</span>
                        <strong class="text-success d-block fs-5"><?= sanitize($booking['booking_code']) ?></strong>
                        <?php if ($accessToken !== ''): ?>
                            <span class="small text-secondary d-block mt-2">Token akses — simpan secara pribadi</span>
                            <code class="user-select-all"><?= sanitize($accessToken) ?></code>
                        <?php endif; ?>
                    </div>
                    <span class="badge text-bg-<?= sanitize($statusStyle) ?> fs-6"><?= sanitize($statusLabel) ?></span>
                </div>

                <div class="row g-4">
                    <div class="col-6 col-md-4">
                        <span class="small text-secondary d-block">Meja</span>
                        <strong><?= sanitize($booking['table_number']) ?></strong>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="small text-secondary d-block">Jumlah tamu</span>
                        <strong><?= (int) $booking['guest_count'] ?> orang</strong>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="small text-secondary d-block">Tanggal</span>
                        <strong><?= sanitize($bookingStart->format('d/m/Y')) ?></strong>
                    </div>
                    <div class="col-12">
                        <span class="small text-secondary d-block">Waktu</span>
                        <strong>
                            <?= sanitize($bookingStart->format('H:i')) ?>
                            –
                            <?= sanitize($bookingEnd->format('d/m/Y H:i')) ?>
                        </strong>
                        <span class="small text-secondary d-block">Durasi reservasi 2 jam.</span>
                    </div>
                </div>

                <?php if ($status === 'pending'): ?>
                    <div class="alert alert-info mt-4 mb-0" role="status">
                        Permintaan reservasi sudah dicatat dan menunggu konfirmasi pihak kafe.
                    </div>
                <?php elseif ($status === 'cancelled'): ?>
                    <div class="alert alert-secondary mt-4 mb-0" role="status">
                        Reservasi ini dibatalkan. Silakan buat reservasi baru bila masih diperlukan.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
