<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

function checkout_normalize_phone(string $phone): string
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

function checkout_parse_cart(string $serializedCart): array
{
    if ($serializedCart === '' || strlen($serializedCart) > 65536) {
        throw new InvalidArgumentException('Keranjang kosong atau terlalu besar. Periksa kembali keranjangmu.');
    }

    try {
        $cart = json_decode($serializedCart, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('Data keranjang tidak valid. Muat ulang halaman keranjang dan coba lagi.');
    }

    if (!is_array($cart) || !array_is_list($cart) || $cart === []) {
        throw new InvalidArgumentException('Keranjang kosong. Tambahkan menu sebelum checkout.');
    }

    $lines = [];
    $seen = [];
    foreach ($cart as $line) {
        if (
            !is_array($line)
            || !isset($line['productId'], $line['quantity'])
            || !is_int($line['productId'])
            || $line['productId'] < 1
            || !is_int($line['quantity'])
            || $line['quantity'] < 1
            || $line['quantity'] > 99
            || !array_key_exists('variantId', $line)
            || ($line['variantId'] !== null && (!is_int($line['variantId']) || $line['variantId'] < 1))
        ) {
            throw new InvalidArgumentException('Ada item keranjang yang tidak valid. Periksa kembali menu yang dipilih.');
        }

        $key = $line['productId'] . ':' . ($line['variantId'] ?? 'none');
        if (isset($seen[$key])) {
            throw new InvalidArgumentException('Item keranjang duplikat. Ubah jumlahnya dari halaman keranjang.');
        }
        $seen[$key] = true;
        $lines[] = [
            'product_id' => $line['productId'],
            'variant_id' => $line['variantId'],
            'quantity' => $line['quantity'],
        ];
    }

    return $lines;
}

function checkout_price_cents(mixed $price): int
{
    if (!is_string($price) || preg_match('/\A(0|[1-9]\d{0,7})(?:\.(\d{1,2}))?\z/', $price, $matches) !== 1) {
        throw new RuntimeException('Harga produk di database tidak valid.');
    }

    $fraction = str_pad($matches[2] ?? '', 2, '0');
    return ((int) $matches[1] * 100) + (int) $fraction;
}

function checkout_format_cents(int $amount): string
{
    return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}

$errorMessage = null;
$formValues = [
    'customer_name' => '',
    'customer_phone' => '',
    'order_type' => 'takeaway',
    'table_id' => '',
    'payment_method' => 'cash',
];

$tables = $pdo->query(
    'SELECT id, table_number, capacity
     FROM `tables`
     WHERE is_active = 1
     ORDER BY table_number'
)->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($formValues) as $field) {
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

            $customerPhone = checkout_normalize_phone($formValues['customer_phone']);
            if (!in_array($formValues['order_type'], ['dine_in', 'takeaway'], true)) {
                throw new InvalidArgumentException('Pilih jenis pesanan yang valid.');
            }
            if (!in_array($formValues['payment_method'], ['cash', 'qris'], true)) {
                throw new InvalidArgumentException('Pilih metode pembayaran yang valid.');
            }

            $tableId = null;
            if ($formValues['order_type'] === 'dine_in') {
                $parsedTableId = filter_var(
                    $formValues['table_id'],
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if ($parsedTableId === false) {
                    throw new InvalidArgumentException('Pilih meja aktif untuk pesanan dine-in.');
                }
                $tableId = (int) $parsedTableId;
            }

            $cartLines = checkout_parse_cart(
                is_string($_POST['cart_data'] ?? null) ? $_POST['cart_data'] : ''
            );
            $productIds = array_values(array_unique(array_column($cartLines, 'product_id')));
            sort($productIds, SORT_NUMERIC);
            $placeholders = implode(', ', array_fill(0, count($productIds), '?'));

            $pdo->beginTransaction();

            if ($tableId !== null) {
                $tableCheck = $pdo->prepare(
                    'SELECT id FROM `tables` WHERE id = :id AND is_active = 1 FOR UPDATE'
                );
                $tableCheck->execute(['id' => $tableId]);
                if ($tableCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Meja yang dipilih sudah tidak aktif. Pilih meja lain.');
                }
            }

            $productQuery = $pdo->prepare(
                'SELECT id, name, price, is_available
                 FROM products
                 WHERE id IN (' . $placeholders . ')
                 FOR UPDATE'
            );
            $productQuery->execute($productIds);
            $productsById = [];
            foreach ($productQuery->fetchAll() as $product) {
                $productsById[(int) $product['id']] = $product;
            }

            $variantQuery = $pdo->prepare(
                'SELECT id, product_id, name, price, is_available
                 FROM product_variants
                 WHERE product_id IN (' . $placeholders . ')
                 FOR UPDATE'
            );
            $variantQuery->execute($productIds);
            $variantsById = [];
            $variantCounts = [];
            foreach ($variantQuery->fetchAll() as $variant) {
                $variantId = (int) $variant['id'];
                $productId = (int) $variant['product_id'];
                $variantsById[$variantId] = $variant;
                $variantCounts[$productId] = ($variantCounts[$productId] ?? 0) + 1;
            }

            $validatedLines = [];
            $totalCents = 0;
            $maximumCents = 9999999999;
            foreach ($cartLines as $line) {
                $product = $productsById[$line['product_id']] ?? null;
                if ($product === null || (int) $product['is_available'] !== 1) {
                    throw new InvalidArgumentException('Salah satu menu sudah tidak tersedia. Perbarui keranjangmu.');
                }

                $variant = null;
                if ($line['variant_id'] === null) {
                    if (($variantCounts[$line['product_id']] ?? 0) > 0) {
                        throw new InvalidArgumentException('Pilih varian untuk setiap menu yang memilikinya.');
                    }
                    $priceCents = checkout_price_cents($product['price']);
                } else {
                    $variant = $variantsById[$line['variant_id']] ?? null;
                    if (
                        $variant === null
                        || (int) $variant['product_id'] !== $line['product_id']
                        || (int) $variant['is_available'] !== 1
                    ) {
                        throw new InvalidArgumentException('Salah satu varian sudah tidak tersedia. Perbarui keranjangmu.');
                    }
                    $priceCents = checkout_price_cents($variant['price']);
                }

                $lineTotalCents = $priceCents * $line['quantity'];
                if ($totalCents > $maximumCents - $lineTotalCents) {
                    throw new InvalidArgumentException('Total pesanan melebihi batas transaksi.');
                }
                $totalCents += $lineTotalCents;
                $validatedLines[] = [
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'product_name' => $product['name'],
                    'variant_name' => $variant['name'] ?? null,
                    'price' => checkout_format_cents($priceCents),
                    'quantity' => $line['quantity'],
                    'subtotal' => checkout_format_cents($lineTotalCents),
                ];
            }

            $accessToken = generate_public_access_token();
            $orderInsert = $pdo->prepare(
                'INSERT INTO orders
                    (order_code, access_token_hash, customer_name, customer_phone, order_type, table_id,
                     subtotal, total, payment_method, payment_status, order_status)
                 VALUES
                    (:order_code, :access_token_hash, :customer_name, :customer_phone, :order_type, :table_id,
                     :subtotal, :total, :payment_method, :payment_status, :order_status)'
            );
            $orderCode = null;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $candidateCode = generate_order_code();
                try {
                    $orderInsert->execute([
                        'order_code' => $candidateCode,
                        'access_token_hash' => hash('sha256', $accessToken),
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'order_type' => $formValues['order_type'],
                        'table_id' => $tableId,
                        'subtotal' => checkout_format_cents($totalCents),
                        'total' => checkout_format_cents($totalCents),
                        'payment_method' => $formValues['payment_method'],
                        'payment_status' => 'pending',
                        'order_status' => 'pending',
                    ]);
                    $orderCode = $candidateCode;
                    break;
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000' || (int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                        throw $exception;
                    }
                }
            }

            if ($orderCode === null) {
                throw new RuntimeException('Kode pesanan unik tidak dapat dibuat setelah beberapa percobaan.');
            }

            $orderId = (int) $pdo->lastInsertId();
            $itemInsert = $pdo->prepare(
                'INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, variant_name, price, quantity, subtotal)
                 VALUES
                    (:order_id, :product_id, :variant_id, :product_name, :variant_name, :price, :quantity, :subtotal)'
            );
            foreach ($validatedLines as $line) {
                $itemInsert->execute([
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'product_name' => $line['product_name'],
                    'variant_name' => $line['variant_name'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $pdo->commit();
            $paymentUrl = BASE_URL . '/payment.php?code=' . rawurlencode($orderCode)
                . '&token=' . rawurlencode($accessToken);
            $paymentUrlJson = json_encode(
                $paymentUrl,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            );
            $pageTitle = 'Pesanan dibuat';
            require __DIR__ . '/../includes/header.php';
            ?>
            <section class="container py-4 py-lg-5 text-center" aria-labelledby="order-created-heading">
                <div class="contact-panel p-4 p-lg-5">
                    <i class="bi bi-check-circle display-4 text-success" aria-hidden="true"></i>
                    <h1 id="order-created-heading" class="h2 fw-bold mt-3">Pesanan berhasil dibuat</h1>
                    <p class="text-secondary mb-3">Keranjang akan dikosongkan dan kamu akan diarahkan ke halaman pembayaran.</p>
                    <p id="cart-clear-message" class="small text-warning-emphasis" role="status"></p>
                    <a href="<?= sanitize($paymentUrl) ?>" class="btn btn-success">Lanjut ke pembayaran</a>
                </div>
            </section>
            <script>
                (() => {
                    try {
                        localStorage.removeItem('mojiKafeCart');
                    } catch (error) {
                        document.getElementById('cart-clear-message').textContent =
                            'Pesanan sudah dibuat, tetapi keranjang browser tidak dapat dikosongkan. Hapus keranjang secara manual setelah pembayaran.';
                    }
                    window.location.replace(<?= $paymentUrlJson ?>);
                })();
            </script>
            <?php
            require __DIR__ . '/../includes/footer.php';
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
            error_log('Checkout database error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Pesanan tidak dapat disimpan saat ini. Silakan coba kembali.';
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout processing error: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Pesanan tidak dapat diproses saat ini. Silakan coba kembali.';
        }
    }
}

$csrfField = csrf_field();
$pageTitle = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-4 py-lg-5" aria-labelledby="checkout-heading">
    <div class="mb-4">
        <p class="section-kicker text-uppercase small fw-semibold mb-2">Satu langkah lagi</p>
        <h1 id="checkout-heading" class="display-6 fw-bold mb-2">Checkout</h1>
        <p class="text-secondary mb-0">Isi detail pesanan. Harga dan ketersediaan menu akan divalidasi kembali oleh server.</p>
    </div>

    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-danger" role="alert"><?= sanitize($errorMessage) ?></div>
    <?php endif; ?>

    <form method="post" data-checkout-form>
        <?= $csrfField ?>
        <input type="hidden" name="cart_data" value="">
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <section class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3">Informasi pelanggan</h2>
                        <div class="mb-3">
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
                            <div class="form-text">Masukkan 8–15 digit; tanda +, spasi, tanda kurung, titik, dan tanda hubung diperbolehkan.</div>
                        </div>

                        <fieldset class="mb-4">
                            <legend class="form-label fw-semibold">Jenis pesanan</legend>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="order_type"
                                    id="order-dine-in"
                                    value="dine_in"
                                    data-order-type
                                    <?= $formValues['order_type'] === 'dine_in' ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="order-dine-in">Dine-in</label>
                            </div>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="order_type"
                                    id="order-takeaway"
                                    value="takeaway"
                                    data-order-type
                                    <?= $formValues['order_type'] === 'takeaway' ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="order-takeaway">Takeaway</label>
                            </div>
                        </fieldset>

                        <div class="mb-4" data-dine-in-table <?= $formValues['order_type'] === 'dine_in' ? '' : 'hidden' ?>>
                            <label for="order-table" class="form-label">Pilih meja aktif</label>
                            <select class="form-select" id="order-table" name="table_id">
                                <option value="">Pilih meja</option>
                                <?php foreach ($tables as $table): ?>
                                    <option
                                        value="<?= (int) $table['id'] ?>"
                                        <?= $formValues['table_id'] === (string) $table['id'] ? 'selected' : '' ?>
                                    ><?= sanitize($table['table_number']) ?> (maks. <?= (int) $table['capacity'] ?> orang)</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($tables === []): ?>
                                <div class="form-text text-danger">Belum ada meja aktif. Pilih takeaway atau hubungi kafe.</div>
                            <?php endif; ?>
                        </div>

                        <fieldset>
                            <legend class="form-label fw-semibold">Metode pembayaran</legend>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="payment_method"
                                    id="payment-cash"
                                    value="cash"
                                    <?= $formValues['payment_method'] === 'cash' ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="payment-cash">Cash</label>
                            </div>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="payment_method"
                                    id="payment-qris"
                                    value="qris"
                                    <?= $formValues['payment_method'] === 'qris' ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="payment-qris">QRIS</label>
                            </div>
                        </fieldset>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <section class="card border-0 shadow-sm" aria-labelledby="checkout-summary-heading">
                    <div class="card-body p-4">
                        <h2 id="checkout-summary-heading" class="h5 fw-bold mb-3">Ringkasan pesanan</h2>
                        <div data-checkout-items class="d-grid gap-3" aria-live="polite"></div>
                        <div data-checkout-empty class="small text-secondary" hidden>
                            Keranjang kosong. <a href="<?= sanitize(BASE_URL) ?>/menu.php">Pilih menu</a> sebelum checkout.
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span class="fw-semibold">Estimasi subtotal</span>
                            <strong class="text-success" data-checkout-total>Rp 0</strong>
                        </div>
                        <p class="small text-secondary mt-2 mb-0">Tidak ada biaya tambahan. Harga dan total akhir dihitung ulang di server saat pesanan dibuat.</p>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-4">
                            Buat pesanan <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
