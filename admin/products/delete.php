<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_helpers.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    exit('Metode penghapusan produk tidak diizinkan.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
}

$id = admin_parse_positive_int($_POST['id'] ?? null);
if ($id === null) {
    set_admin_flash('danger', 'Produk yang dipilih tidak valid.');
    redirect_admin('products/index.php');
}

try {
    $select = $pdo->prepare('SELECT image FROM products WHERE id = :id');
    $select->execute(['id' => $id]);
    $image = $select->fetchColumn();
    if ($image === false) {
        set_admin_flash('warning', 'Produk tidak ditemukan.');
        redirect_admin('products/index.php');
    }

    $delete = $pdo->prepare('DELETE FROM products WHERE id = :id');
    $delete->execute(['id' => $id]);
    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('Product delete did not affect exactly one row.');
    }

    $imageRemoved = remove_product_image(is_string($image) ? $image : null);
    set_admin_flash(
        $imageRemoved ? 'success' : 'warning',
        $imageRemoved
            ? 'Produk berhasil dihapus.'
            : 'Produk berhasil dihapus, tetapi foto lama tidak dapat dihapus.'
    );
    redirect_admin('products/index.php');
} catch (PDOException $exception) {
    error_log('Product deletion database error: ' . $exception->getMessage());
    set_admin_flash('danger', 'Produk tidak dapat dihapus saat ini.');
    redirect_admin('products/index.php');
} catch (RuntimeException $exception) {
    error_log('Product deletion error: ' . $exception->getMessage());
    set_admin_flash('danger', 'Produk tidak dapat dihapus saat ini.');
    redirect_admin('products/index.php');
}
