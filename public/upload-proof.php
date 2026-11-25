<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

function redirect_payment_with_flash(string $orderCode, string $accessToken, string $type, string $message): never
{
    start_secure_session();
    $_SESSION['payment_upload_flash'] = [
        'order_code' => $orderCode,
        'type' => $type,
        'message' => $message,
    ];

    header(
        'Location: ' . BASE_URL . '/payment.php?code=' . rawurlencode($orderCode)
            . '&token=' . rawurlencode($accessToken),
        true,
        303
    );
    exit;
}

function remove_uploaded_proof(string $fileName): void
{
    if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $fileName) !== 1) {
        error_log('Skipped cleaning up a payment proof with an invalid stored filename.');
        return;
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Unable to clean up a payment proof after a failed database operation.');
    }
}

function respond_upload_request_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond_upload_request_error(405, 'Metode permintaan tidak diizinkan.');
}

$orderCode = is_string($_POST['order_code'] ?? null)
    ? strtoupper(trim($_POST['order_code']))
    : '';
$accessToken = is_string($_POST['access_token'] ?? null)
    ? trim($_POST['access_token'])
    : '';

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    if (is_valid_order_code($orderCode)) {
        redirect_payment_with_flash(
            $orderCode,
            $accessToken,
            'danger',
            'Token keamanan tidak valid. Muat ulang halaman pesanan dan coba lagi.'
        );
    }
    respond_upload_request_error(403, 'Token keamanan tidak valid.');
}

if (!is_valid_order_code($orderCode)) {
    respond_upload_request_error(400, 'Kode pesanan tidak valid.');
}

$storedFileName = null;
try {
    $pdo->beginTransaction();

    $orderQuery = $pdo->prepare(
        'SELECT id, order_code, access_token_hash, created_at, payment_method, payment_status, order_status, total
         FROM orders
         WHERE order_code = :order_code
         FOR UPDATE'
    );
    $orderQuery->execute(['order_code' => $orderCode]);
    $order = $orderQuery->fetch();
    if ($order === false) {
        throw new InvalidArgumentException('Pesanan tidak ditemukan. Periksa kembali kode pesananmu.');
    }
    if (!verify_public_access($accessToken, $order['access_token_hash'], $order['created_at'])) {
        throw new InvalidArgumentException('Token akses pesanan tidak valid.');
    }
    if ($order['payment_method'] !== 'qris') {
        throw new InvalidArgumentException('Unggah bukti hanya tersedia untuk pesanan QRIS.');
    }
    if ($order['order_status'] === 'cancelled') {
        throw new InvalidArgumentException('Pesanan yang dibatalkan tidak dapat menerima bukti pembayaran.');
    }
    if ($order['payment_status'] === 'verified') {
        throw new InvalidArgumentException('Pembayaran pesanan ini sudah terverifikasi.');
    }

    $paymentQuery = $pdo->prepare(
        'SELECT status
         FROM payments
         WHERE order_id = :order_id
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $paymentQuery->execute(['order_id' => $order['id']]);
    $latestPayment = $paymentQuery->fetch();
    if (is_array($latestPayment) && in_array($latestPayment['status'], ['pending', 'verified'], true)) {
        throw new InvalidArgumentException(
            $latestPayment['status'] === 'verified'
                ? 'Pembayaran pesanan ini sudah terverifikasi.'
                : 'Bukti pembayaran sudah diterima dan sedang menunggu verifikasi.'
        );
    }

    $proofFile = $_FILES['proof_image'] ?? null;
    if (!is_array($proofFile) || !isset($proofFile['error']) || !is_int($proofFile['error'])) {
        throw new InvalidArgumentException('Data file bukti pembayaran tidak valid.');
    }
    if ($proofFile['error'] !== UPLOAD_ERR_OK) {
        $clientUploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Pilih file bukti pembayaran terlebih dahulu.',
        ];
        if (isset($clientUploadErrors[$proofFile['error']])) {
            throw new InvalidArgumentException($clientUploadErrors[$proofFile['error']]);
        }
        throw new RuntimeException('Server tidak dapat menerima file bukti pembayaran.');
    }

    $storedFileName = upload_image($proofFile, __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payments');

    $paymentInsert = $pdo->prepare(
        'INSERT INTO payments (order_id, method, amount, proof_image, status)
         VALUES (:order_id, :method, :amount, :proof_image, :status)'
    );
    $paymentInsert->execute([
        'order_id' => $order['id'],
        'method' => 'qris',
        'amount' => $order['total'],
        'proof_image' => $storedFileName,
        'status' => 'pending',
    ]);

    $orderUpdate = $pdo->prepare(
        'UPDATE orders SET payment_status = :payment_status WHERE id = :id'
    );
    $orderUpdate->execute([
        'payment_status' => 'pending',
        'id' => $order['id'],
    ]);

    $pdo->commit();
    redirect_payment_with_flash($orderCode, $accessToken, 'success', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi.');
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storedFileName !== null) {
        remove_uploaded_proof($storedFileName);
    }
    redirect_payment_with_flash($orderCode, $accessToken, 'danger', $exception->getMessage());
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storedFileName !== null) {
        remove_uploaded_proof($storedFileName);
    }
    error_log('Payment proof database error: ' . $exception->getMessage());
    redirect_payment_with_flash(
        $orderCode,
        $accessToken,
        'danger',
        'Bukti pembayaran tidak dapat disimpan saat ini. Silakan coba kembali.'
    );
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($storedFileName !== null) {
        remove_uploaded_proof($storedFileName);
    }
    error_log('Payment proof processing error: ' . $exception->getMessage());
    redirect_payment_with_flash(
        $orderCode,
        $accessToken,
        'danger',
        'Server tidak dapat memproses file bukti pembayaran saat ini.'
    );
}
