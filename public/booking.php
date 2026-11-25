<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

function booking_normalize_phone(string $phone): string
{
    $phone = trim($phone);
    if (preg_match('/\A\+?[0-9\s().-]{8,30}\z/u', $phone) !== 1) {
        throw new InvalidArgumentException('Masukkan nomor WhatsApp yang valid.');
    }

    $digits = preg_replace('/\D/', '', $phone);
    if (!is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
        throw new InvalidArgumentException('Nomor WhatsApp harus berisi 8 sampai 15 digit.');
    }

    return str_starts_with($phone, '+') ? '+' . $digits : $digits;
}

function booking_parse_schedule(string $date, string $time): array
{
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        $parsedDate === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $parsedDate->format('Y-m-d') !== $date
    ) {
        throw new InvalidArgumentException('Pilih tanggal reservasi yang valid.');
    }

    if ($parsedDate < new DateTimeImmutable('today')) {
        throw new InvalidArgumentException('Tanggal reservasi tidak boleh sudah lewat.');
    }

    if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time) !== 1) {
        throw new InvalidArgumentException('Pilih waktu mulai yang valid.');
    }

    $start = new DateTimeImmutable($date . ' ' . $time . ':00');
    if ($start < new DateTimeImmutable()) {
        throw new InvalidArgumentException('Waktu mulai reservasi harus berada di masa depan.');
    }

    return [$start, $start->modify('+2 hours')];
}

function booking_interval_from_row(array $booking): array
{
    $start = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['start_time']);
    $end = new DateTimeImmutable($booking['booking_date'] . ' ' . $booking['end_time']);
    if ($end <= $start) {
        $end = $end->modify('+1 day');
    }

    return [$start, $end];
}

function booking_intervals_overlap(DateTimeImmutable $start, DateTimeImmutable $end, array $booking): bool
{
    [$existingStart, $existingEnd] = booking_interval_from_row($booking);
    return $start < $existingEnd && $existingStart < $end;
}

function booking_find_available_tables(
    PDO $pdo,
    string $bookingDate,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    int $guestCount
): array {
    $tablesQuery = $pdo->prepare(
        'SELECT id, table_number, capacity
         FROM `tables`
         WHERE is_active = 1 AND capacity >= :guest_count
         ORDER BY capacity, table_number'
    );
    $tablesQuery->execute(['guest_count' => $guestCount]);
    $tables = $tablesQuery->fetchAll();
    if ($tables === []) {
        return [];
    }

    $tableIds = array_map(static fn (array $table): int => (int) $table['id'], $tables);
    $placeholders = implode(', ', array_fill(0, count($tableIds), '?'));
    $previousDate = (new DateTimeImmutable($bookingDate))->modify('-1 day')->format('Y-m-d');
    $bookingsQuery = $pdo->prepare(
        'SELECT table_id, booking_date, start_time, end_time
         FROM bookings
         WHERE table_id IN (' . $placeholders . ')
           AND booking_date BETWEEN ? AND ?
           AND status IN (\'pending\', \'confirmed\', \'in_use\')
         ORDER BY booking_date, start_time'
    );
    $bookingsQuery->execute([...$tableIds, $previousDate, $bookingDate]);

    $blockedTableIds = [];
    foreach ($bookingsQuery->fetchAll() as $booking) {
        if (booking_intervals_overlap($start, $end, $booking)) {
            $blockedTableIds[(int) $booking['table_id']] = true;
        }
    }

    return array_values(array_filter(
        $tables,
        static fn (array $table): bool => !isset($blockedTableIds[(int) $table['id']])
    ));
}

$today = new DateTimeImmutable('today');
$formValues = [
    'customer_name' => '',
    'customer_phone' => '',
    'guest_count' => is_string($_GET['guest_count'] ?? null) ? trim($_GET['guest_count']) : '',
    'booking_date' => is_string($_GET['booking_date'] ?? null) ? trim($_GET['booking_date']) : $today->format('Y-m-d'),
    'start_time' => is_string($_GET['start_time'] ?? null) ? trim($_GET['start_time']) : '',
    'table_id' => '',
];
$errorMessage = null;
$availableTables = [];
$scheduleReady = false;
$scheduleStart = null;
$scheduleEnd = null;
$guestCount = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['customer_name', 'customer_phone', 'guest_count', 'booking_date', 'start_time', 'table_id'] as $field) {
        $value = $_POST[$field] ?? null;
        $formValues[$field] = is_string($value) ? trim($value) : '';
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorMessage = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        try {
            $customerName = $formValues['customer_name'];
            if (
                preg_match('/\A.{1,150}\z/us', $customerName) !== 1
                || preg_match('/[\x00-\x1F\x7F]/', $customerName) === 1
            ) {
                throw new InvalidArgumentException('Nama wajib diisi dan maksimal 150 karakter.');
            }

            $customerPhone = booking_normalize_phone($formValues['customer_phone']);
            $guestCount = filter_var(
                $formValues['guest_count'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 255]]
            );
            if ($guestCount === false) {
                throw new InvalidArgumentException('Jumlah tamu harus antara 1 dan 255 orang.');
            }
            $guestCount = (int) $guestCount;

            [$scheduleStart, $scheduleEnd] = booking_parse_schedule(
                $formValues['booking_date'],
                $formValues['start_time']
            );
            $scheduleReady = true;

            $tableId = filter_var(
                $formValues['table_id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($tableId === false) {
                throw new InvalidArgumentException('Pilih meja yang tersedia untuk jadwal tersebut.');
            }
            $tableId = (int) $tableId;

            $pdo->beginTransaction();

            $tableQuery = $pdo->prepare(
                'SELECT id, table_number, capacity
                 FROM `tables`
                 WHERE id = :id AND is_active = 1
                 FOR UPDATE'
            );
            $tableQuery->execute(['id' => $tableId]);
            $selectedTable = $tableQuery->fetch();
            if ($selectedTable === false) {
                throw new InvalidArgumentException('Meja yang dipilih sudah tidak aktif. Periksa kembali ketersediaan meja.');
            }
            if ((int) $selectedTable['capacity'] < $guestCount) {
                throw new InvalidArgumentException('Kapasitas meja tidak mencukupi jumlah tamu.');
            }

            $previousDate = $scheduleStart->modify('-1 day')->format('Y-m-d');
            $existingQuery = $pdo->prepare(
                'SELECT booking_date, start_time, end_time
                 FROM bookings
                 WHERE table_id = :table_id
                   AND booking_date BETWEEN :previous_date AND :booking_date
                   AND status IN (\'pending\', \'confirmed\', \'in_use\')
                 ORDER BY booking_date, start_time
                 FOR UPDATE'
            );
            $existingQuery->execute([
                'table_id' => $tableId,
                'previous_date' => $previousDate,
                'booking_date' => $formValues['booking_date'],
            ]);
            foreach ($existingQuery->fetchAll() as $existingBooking) {
                if (booking_intervals_overlap($scheduleStart, $scheduleEnd, $existingBooking)) {
                    throw new InvalidArgumentException('Meja tersebut baru saja dipesan pada waktu yang bertabrakan. Pilih meja atau waktu lain.');
                }
            }

            $accessToken = generate_public_access_token();
            $insert = $pdo->prepare(
                'INSERT INTO bookings
                    (booking_code, access_token_hash, customer_name, customer_phone, table_id, booking_date,
                     start_time, end_time, guest_count, status)
                 VALUES
                    (:booking_code, :access_token_hash, :customer_name, :customer_phone, :table_id, :booking_date,
                     :start_time, :end_time, :guest_count, :status)'
            );
            $bookingCode = null;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $candidateCode = generate_booking_code();
                try {
                    $insert->execute([
                        'booking_code' => $candidateCode,
                        'access_token_hash' => hash('sha256', $accessToken),
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'table_id' => $tableId,
                        'booking_date' => $formValues['booking_date'],
                        'start_time' => $scheduleStart->format('H:i:s'),
                        'end_time' => $scheduleEnd->format('H:i:s'),
                        'guest_count' => $guestCount,
                        'status' => 'pending',
                    ]);
                    $bookingCode = $candidateCode;
                    break;
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000' || (int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                        throw $exception;
                    }
                }
            }

            if ($bookingCode === null) {
                throw new RuntimeException('Kode reservasi unik tidak dapat dibuat setelah beberapa percobaan.');
            }

            $pdo->commit();
            header(
                'Location: ' . BASE_URL . '/check-booking.php?code=' . rawurlencode($bookingCode)
                    . '&token=' . rawurlencode($accessToken),
                true,
                303
            );
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(422);
            $errorMessage = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Booking creation database error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Reservasi tidak dapat disimpan saat ini. Silakan coba kembali.';
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Booking creation error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Reservasi tidak dapat diproses saat ini. Silakan coba kembali.';
        }
    }
}

if (!$scheduleReady && $formValues['guest_count'] !== '') {
    try {
        $guestCount = filter_var(
            $formValues['guest_count'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 255]]
        );
        if ($guestCount === false) {
            throw new InvalidArgumentException('Jumlah tamu harus antara 1 dan 255 orang.');
        }
        $guestCount = (int) $guestCount;
        [$scheduleStart, $scheduleEnd] = booking_parse_schedule(
            $formValues['booking_date'],
            $formValues['start_time']
        );
        $scheduleReady = true;
    } catch (InvalidArgumentException $exception) {
        if ($errorMessage === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $errorMessage = $exception->getMessage();
        }
    }
}

if ($scheduleReady && $guestCount !== null && $scheduleStart instanceof DateTimeImmutable && $scheduleEnd instanceof DateTimeImmutable) {
    $availableTables = booking_find_available_tables(
        $pdo,
        $formValues['booking_date'],
        $scheduleStart,
        $scheduleEnd,
        $guestCount
    );
}

$csrfField = csrf_field();
$pageTitle = 'Reservasi Meja';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" aria-labelledby="booking-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Rencanakan kunjunganmu</p>
        <h1 id="booking-heading" class="display-6 fw-bold mb-2">Reservasi meja</h1>
        <p class="text-secondary mb-0">Reservasi berlangsung 2 jam. Meja dikonfirmasi setelah permintaan berhasil dicatat.</p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-<?= ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'danger' : 'warning' ?>" role="alert">
            <?= sanitize($errorMessage) ?>
        </div>
    <?php endif; ?>
    <div class="row g-4 align-items-start">
        <div class="col-12 col-lg-5">
            <section class="card border-0 shadow-sm" aria-labelledby="schedule-heading">
                <div class="card-body p-4">
                    <h2 id="schedule-heading" class="h5 fw-bold mb-3">Cari waktu dan meja</h2>
                    <form method="get" action="<?= sanitize(BASE_URL) ?>/booking.php">
                        <div class="mb-3">
                            <label for="booking-date" class="form-label">Tanggal reservasi</label>
                            <input
                                type="date"
                                class="form-control"
                                id="booking-date"
                                name="booking_date"
                                value="<?= sanitize($formValues['booking_date']) ?>"
                                min="<?= sanitize($today->format('Y-m-d')) ?>"
                                required
                            >
                        </div>
                        <div class="mb-3">
                            <label for="booking-time" class="form-label">Waktu mulai</label>
                            <input
                                type="time"
                                class="form-control"
                                id="booking-time"
                                name="start_time"
                                value="<?= sanitize($formValues['start_time']) ?>"
                                required
                            >
                        </div>
                        <div class="mb-3">
                            <label for="guest-count" class="form-label">Jumlah tamu</label>
                            <input
                                type="number"
                                class="form-control"
                                id="guest-count"
                                name="guest_count"
                                value="<?= sanitize($formValues['guest_count']) ?>"
                                min="1"
                                max="255"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-outline-success w-100">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Periksa ketersediaan
                        </button>
                    </form>

                    <?php if ($scheduleReady && $scheduleStart instanceof DateTimeImmutable && $scheduleEnd instanceof DateTimeImmutable): ?>
                        <div class="contact-panel p-3 mt-4">
                            <span class="small text-secondary d-block">Durasi reservasi</span>
                            <strong>
                                <?= sanitize($scheduleStart->format('d/m/Y H:i')) ?>
                                –
                                <?= sanitize($scheduleEnd->format('d/m/Y H:i')) ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-7">
            <?php if (!$scheduleReady || $guestCount === null): ?>
                <div class="contact-panel p-4 p-lg-5 text-center">
                    <i class="bi bi-calendar2-check display-5 text-success" aria-hidden="true"></i>
                    <h2 class="h5 fw-bold mt-3">Pilih jadwal terlebih dahulu</h2>
                    <p class="text-secondary mb-0">Isi tanggal, waktu mulai, dan jumlah tamu untuk melihat meja yang tersedia.</p>
                </div>
            <?php elseif ($availableTables === []): ?>
                <div class="contact-panel p-4 p-lg-5 text-center">
                    <i class="bi bi-calendar-x display-5 text-secondary" aria-hidden="true"></i>
                    <h2 class="h5 fw-bold mt-3">Meja tidak tersedia</h2>
                    <p class="text-secondary mb-0">Tidak ada meja aktif dengan kapasitas cukup pada waktu tersebut. Pilih jadwal atau jumlah tamu lain.</p>
                </div>
            <?php else: ?>
                <section class="card border-0 shadow-sm" aria-labelledby="available-tables-heading">
                    <div class="card-body p-4">
                        <h2 id="available-tables-heading" class="h5 fw-bold mb-1">Meja tersedia</h2>
                        <p class="small text-secondary mb-4"><?= count($availableTables) ?> meja cocok untuk <?= $guestCount ?> tamu pada jadwal pilihan.</p>

                        <form method="post" action="<?= sanitize(BASE_URL) ?>/booking.php">
                            <?= $csrfField ?>
                            <input type="hidden" name="booking_date" value="<?= sanitize($formValues['booking_date']) ?>">
                            <input type="hidden" name="start_time" value="<?= sanitize($formValues['start_time']) ?>">
                            <input type="hidden" name="guest_count" value="<?= $guestCount ?>">

                            <div class="mb-4">
                                <label for="customer-name" class="form-label">Nama lengkap</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="customer-name"
                                    name="customer_name"
                                    value="<?= sanitize($formValues['customer_name']) ?>"
                                    maxlength="150"
                                    autocomplete="name"
                                    required
                                >
                            </div>
                            <div class="mb-4">
                                <label for="customer-phone" class="form-label">Nomor WhatsApp</label>
                                <input
                                    type="tel"
                                    class="form-control"
                                    id="customer-phone"
                                    name="customer_phone"
                                    value="<?= sanitize($formValues['customer_phone']) ?>"
                                    maxlength="30"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    required
                                >
                                <div class="form-text">Masukkan 8–15 digit.</div>
                            </div>
                            <fieldset class="mb-4">
                                <legend class="form-label fw-semibold">Pilih meja</legend>
                                <div class="d-grid gap-2">
                                    <?php foreach ($availableTables as $index => $table): ?>
                                        <label class="contact-panel p-3 d-flex align-items-center gap-3">
                                            <input
                                                class="form-check-input m-0"
                                                type="radio"
                                                name="table_id"
                                                value="<?= (int) $table['id'] ?>"
                                                <?= $formValues['table_id'] === (string) $table['id'] || ($formValues['table_id'] === '' && $index === 0) ? 'checked' : '' ?>
                                                required
                                            >
                                            <span>
                                                <strong><?= sanitize($table['table_number']) ?></strong>
                                                <span class="small text-secondary d-block">Kapasitas <?= (int) $table['capacity'] ?> orang</span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                Buat reservasi
                            </button>
                            <p class="small text-secondary text-center mt-3 mb-0">
                                Reservasi berstatus menunggu sampai dikonfirmasi oleh pihak kafe.
                            </p>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
