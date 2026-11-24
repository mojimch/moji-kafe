# AGENTS.MD — Development & Deployment Prompts Guide

Dokumen ini berisi panduan *prompt step-by-step* yang dirancang untuk AI Coding Agent (seperti Claude Code, Cursor, Copilot, atau LLM Agent lainnya) untuk membangun aplikasi **Moji Kafe** dari awal hingga siap dideploy menggunakan **Dokploy**.

---

## Instructions for AI Agent
- Ikuti alur pembuatan fitur sesuai urutan langkah (Step 1 hingga Step 12).
- Setiap langkah harus diuji dan divalidasi sebelum melangkah ke tahap berikutnya.
- Pastikan standar keamanan PHP 8.2+ diterapkan: Prepared Statements (PDO), CSRF Protection, Password Hashing (`password_hash`), Sanitasi Output (`htmlspecialchars`), serta Validasi Upload File.
- Arsitektur berbasis PHP Native + MySQL + Bootstrap 5.

---

## Step 1: Initialize Project Structure & Database Schema

```text
PROMPT STEP 1:
Bertindaklah sebagai Senior Backend Developer. Buatkan struktur direktori project dasar dan skrip database untuk aplikasi "Moji Kafe" berdasarkan spesifikasi PRD berikut:

1. Buat struktur folder project berikut:
   - public/ (index.php, menu.php, cart.php, checkout.php, booking.php, payment.php, check-order.php, check-booking.php)
   - public/assets/css/ (style.css)
   - public/assets/js/ (app.js)
   - public/uploads/payments/
   - admin/ (index.php, login.php, logout.php, dashboard.php)
   - admin/orders/, admin/bookings/, admin/products/, admin/categories/, admin/variants/, admin/tables/, admin/payments/, admin/reports/
   - config/ (database.php, config.php)
   - includes/ (header.php, footer.php, auth.php, functions.php, csrf.php)
   - database/ (schema.sql, seed.sql)

2. Buat file `database/schema.sql` dengan skema MySQL 8.0+ berisi tabel:
   - `admins` (id, name, username, password, created_at, updated_at)
   - `categories` (id, name, created_at)
   - `products` (id, category_id, name, description, price, image, is_available, created_at, updated_at)
   - `product_variants` (id, product_id, name, price, is_available, created_at)
   - `tables` (id, table_number, capacity, is_active, created_at, updated_at)
   - `bookings` (id, booking_code, customer_name, customer_phone, table_id, booking_date, start_time, end_time, guest_count, status, created_at, updated_at)
   - `orders` (id, order_code, customer_name, customer_phone, order_type [dine_in/takeaway], table_id, subtotal, total, payment_method [cash/qris], payment_status, order_status, created_at, updated_at)
   - `order_items` (id, order_id, product_id, variant_id, product_name, variant_name, price, quantity, subtotal)
   - `payments` (id, order_id, method, amount, proof_image, status, verified_by, verified_at, created_at)

   Pastikan Foreign Key dan Index sudah diset dengan tepat.

3. Buat file `database/seed.sql` berisi data dummy awal:
   - 1 Admin default (username: `admin`, password hash untuk `admin123`).
   - 3 Kategori (Makanan, Minuman, Snack).
   - 5 Produk beserta variannya.
   - 4 Meja (Meja 01 s/d Meja 04 dengan kapasitas bervariasi).

4. Buat file `config/database.php` menggunakan PDO dengan error mode exception, serta file `config/config.php` untuk menyimpan BASE_URL dan konfigurasi global.
```

---

## Step 2: Core Helpers & Security Foundation

```text
PROMPT STEP 2:
Buatkan fungsi-fungsi pembantu (helpers) dan keamanan dasar pada folder `includes/`:

1. `includes/csrf.php`:
   - Fungsi `generate_csrf_token()` untuk membuat token CSRF dan menyimpannya di `$_SESSION['csrf_token']`.
   - Fungsi `verify_csrf_token($token)` untuk memverifikasi token saat request POST.
   - Field input hidden HTML helper `csrf_field()`.

2. `includes/functions.php`:
   - Helper `sanitize($data)` untuk membersihkan input XSS (`htmlspecialchars`).
   - Helper `format_rupiah($number)` untuk memformat mata uang.
   - Helper `generate_order_code()` -> format `ORD-YYYYMMDD-XXX`.
   - Helper `generate_booking_code()` -> format `BOOK-YYYYMMDD-XXX`.
   - Helper response JSON `json_response($status, $message, $data)`.
   - Helper upload gambar aman `upload_image($file, $target_dir)` dengan pengecekan ekstensi (jpg, jpeg, png, webp), mime-type, batas ukuran file (max 2MB), dan nama file teracak unik.

3. `includes/auth.php`:
   - Fungsi `is_logged_in()` untuk memvalidasi session admin.
   - Fungsi `require_admin()` untuk meredirect ke `/admin/login.php` jika belum terotentikasi.
```

---

## Step 3: Admin Layout & Authentication

```text
PROMPT STEP 3:
Implementasikan fitur Login & Logout Admin serta template layout dasar dashboard admin:

1. `admin/login.php`:
   - Form Bootstrap 5 (Username, Password, CSRF token).
   - Verifikasi credential ke tabel `admins` menggunakan `password_verify()`.
   - Set session admin (`admin_id`, `admin_name`) dan redirect ke `admin/dashboard.php`.
   - Tampilkan alert error jika autentikasi gagal.

2. `admin/logout.php`:
   - Hapus session admin dan redirect kembali ke `admin/login.php`.

3. Template Layout Admin (`includes/admin_header.php` & `includes/admin_footer.php`):
   - Sidebar/Navbar Bootstrap 5 modern dengan link nav: Dashboard, Pesanan, Reservasi, Pembayaran QRIS, Menu Produk, Kategori, Varian, Meja, Laporan, Logout.
   - Sertakan CSS Bootstrap 5, FontAwesome/Bootstrap Icons, dan script Chart.js.
```

---

## Step 4: Admin Master Data Management (CRUD)

```text
PROMPT STEP 4:
Buat module CRUD lengkap pada sisi admin untuk mengelola Kategori, Produk, Varian, dan Meja:

1. `admin/categories/index.php`:
   - List, Tambah, Edit, Hapus Kategori.

2. `admin/products/index.php`, `create.php`, `edit.php`, `delete.php`:
   - Tampilkan daftar produk dengan filter kategori & status ketersediaan.
   - Form tambah/edit produk dengan upload gambar.
   - Toggle status ketersediaan (is_available).

3. `admin/variants/index.php`:
   - Kelola varian produk berdasarkan `product_id` (contoh: Regular, Large).
   - Atur harga varian dan status ketersediaan varian.

4. `admin/tables/index.php`:
   - CRUD nomor meja, kapasitas tempat duduk, dan status aktif/nonaktif.
```

---

## Step 5: Public Customer Layout & Menu Browsing

```text
PROMPT STEP 5:
Buat tampilan publik pelanggan (Customer side) untuk halaman Landing Page dan Katalogue Menu:

1. Header & Footer Publik (`includes/header.php` & `includes/footer.php`):
   - Navbar Bootstrap 5 responsive: Logo Moji Kafe, Beranda, Menu, Reservasi Meja, Cek Pesanan, Cek Reservasi, Keranjang (dengan badge counter).

2. Landing Page (`public/index.php`):
   - Banner hero modern Moji Kafe.
   - CTA Button: "Lihat Menu", "Pesan Sekarang", "Reservasi Meja".
   - Highlight menu populer/rekomendasi.
   - Jam operasional dan lokasi/kontak kafe.

3. Katalogue Menu (`public/menu.php`):
   - Filter tab berdasarkan kategori (Semua, Makanan, Minuman, Snack).
   - Card produk menampilkan gambar, nama, harga dasar, ketersediaan, dan modal/pilihan varian.
   - Tombol "Tambah ke Keranjang" yang memicu skrip JavaScript (`assets/js/app.js`) untuk menyimpan cart di LocalStorage/Session.
```

---

## Step 6: Customer Cart & Checkout Flow

```text
PROMPT STEP 6:
Implementasikan fitur Keranjang dan Checkout untuk pelanggan:

1. Halaman Keranjang (`public/cart.php`):
   - Daftar item di keranjang (Nama Produk, Varian, Harga Item, Qty, Subtotal).
   - Kontrol ubah qty (+/-) dan hapus item.
   - Perhitungan total belanja.

2. Halaman Checkout (`public/checkout.php`):
   - Form input Pelanggan: Nama Lengkap dan Nomor WhatsApp.
   - Radio button Jenis Pesanan: `Dine-in` atau `Takeaway`.
   - Jika `Dine-in`: Tampilkan dropdown pilihan Meja yang aktif.
   - Radio button Metode Pembayaran: `Cash` atau `QRIS`.
   - Submit Form:
     * Validasi input di server side.
     * Simpan data ke tabel `orders` dan `order_items` menggunakan transaksi Database PDO (`beginTransaction`, `commit`).
     * Hapus keranjang belanja.
     * Redirect ke halaman pembayaran (`public/payment.php?code=ORD-XXXXX`).
```

---

## Step 7: Payment Handling & QRIS Upload

```text
PROMPT STEP 7:
Buat alur penanganan pembayaran Cash & QRIS serta upload bukti transfer:

1. Halaman Pembayaran (`public/payment.php`):
   - Jika pembayaran `Cash`: Tampilkan rincian pesanan dan instruksi untuk melakukan pembayaran langsung di kasir.
   - Jika pembayaran `QRIS`: Tampilkan gambar QRIS Kafe, Total Pembayaran, dan form upload bukti bayar.

2. Upload Bukti QRIS (`public/upload-proof.php`):
   - Proses upload gambar bukti bayar.
   - Simpan data bukti ke tabel `payments` dengan status `pending` (Menunggu Verifikasi).
   - Tampilkan feedback pesan sukses upload dan beri tautan untuk mengecek status order.

3. Halaman Cek Status Pesanan (`public/check-order.php`):
   - Form pencarian berdasarkan Kode Pesanan (`ORD-YYYYMMDD-XXX`).
   - Tampilkan rincian pesanan, item yang dipesan, jenis pesanan, nomor meja (jika dine-in), status pembayaran, dan status pesanan (`Menunggu`, `Diproses`, `Siap`, `Selesai`, `Ditalakan`).
```

---

## Step 8: Table Booking / Table Reservation System

```text
PROMPT STEP 8:
Implementasikan fitur Reservasi Meja Pelanggan:

1. Halaman Reservasi (`public/booking.php`):
   - Form Reservasi: Nama, WhatsApp, Jumlah Tamu, Tanggal Reservasi, Waktu Mulai (Jam).
   - Sistem melakukan pengecekan ketersediaan meja berdasarkan aturan:
     * Durasi reservasi default: **2 Jam** (`end_time = start_time + 2 jam`).
     * Mencegah **double booking**: Meja tidak bisa dipilih jika ada reservasi lain pada rentang jam yang bertabrakan (overlapping).
     * Filter meja yang kapasitasnya cukup untuk `guest_count`.
   - Pilih Meja yang tersedia.
   - Submit Reservasi: Simpan ke tabel `bookings` dengan status `pending`, buat `booking_code` unik (`BOOK-YYYYMMDD-XXX`).

2. Halaman Cek Reservasi (`public/check-booking.php`):
   - Form pencarian berdasarkan Kode Reservasi (`BOOK-YYYYMMDD-XXX`).
   - Tampilkan status reservasi (`Menunggu`, `Dikonfirmasi`, `Sedang Digunakan`, `Selesai`, `Dibatalkan`).
```

---

## Step 9: Admin Order, Booking & Payment Verification

```text
PROMPT STEP 9:
Buat modul pengelolaan transaksi pada dashboard admin:

1. Manajemen Pesanan (`admin/orders/index.php` & `detail.php`):
   - Daftar pesanan masuk (Filter status & pencarian kode pesanan).
   - Update status pesanan: `pending` -> `processing` -> `ready` -> `completed` / `cancelled`.

2. Manajemen Reservasi (`admin/bookings/index.php`):
   - Daftar reservasi meja beserta statusnya.
   - Update status reservasi: `pending` -> `confirmed` -> `in_use` -> `completed` / `cancelled`.

3. Verifikasi Pembayaran QRIS (`admin/payments/index.php`):
   - Daftar bukti pembayaran QRIS yang perlu diverifikasi.
   - Modal/Halaman preview gambar bukti transfer.
   - Action Button: **Setujui** (`verified`) atau **Tolak** (`rejected`). Update otomatis `payment_status` pada tabel `orders`.
```

---

## Step 10: Admin Dashboard & Sales Reports

```text
PROMPT STEP 10:
Buat halaman Dashboard & Laporan Penjualan Admin:

1. Halaman Dashboard (`admin/dashboard.php`):
   - Metric Cards: Total Pesanan Hari Ini, Total Pendapatan Hari Ini, Reservasi Aktif, Jumlah Meja Available.
   - Chart.js Grafik Penjualan (Harian, Mingguan, Bulanan).
   - Tabel Pesanan Terbaru (5 transaksi terakhir).

2. Halaman Laporan (`admin/reports/index.php`):
   - Filter Laporan berdasarkan Range Tanggal (Harian, Mingguan, Bulanan).
   - Ringkasan Total Pesanan, Total Pendapatan, dan Produk Terlaris (Top Selling Items).
   - Tabel rincian laporan penjualan.
```

---

## Step 11: Security & Quality Audit

```text
PROMPT STEP 11:
Lakukan audit keamanan dan perbaikan bug pada seluruh aplikasi Moji Kafe:

1. Keamanan:
   - Pastikan seluruh query ke database menggunakan PDO Prepared Statements.
   - Pastikan seluruh form POST menggunakan validasi token CSRF.
   - Pastikan seluruh output string publik/admin di-escape menggunakan `htmlspecialchars()`.
   - Cegah akses direktori file upload `.php` di folder `public/uploads/`.
   - Proteksi semua halaman di folder `admin/` dengan pengecekan `require_admin()`.

2. Validasi & UX:
   - Tes alur pencegahan double booking meja.
   - Tes penanganan stok/ketersediaan produk.
   - Pastikan layout Bootstrap 5 responsive sempurna pada layar Mobile (360px+), Tablet, dan Desktop.
```

---

## Step 12: Dokploy Deployment Configuration

```text
PROMPT STEP 12:
Buat file konfigurasi Docker dan petunjuk deployment untuk Dokploy:

1. Buat file `Dockerfile` di root project:
   ```dockerfile
   FROM php:8.2-apache

   # Install MySQL client & PDO extensions
   RUN docker-php-ext-install pdo pdo_mysql

   # Enable Apache mod_rewrite
   RUN a2enmod rewrite

   # Set working directory
   WORKDIR /var/www/html

   # Copy application files
   COPY . /var/www/html/

   # Fix permissions
   RUN chown -R www-data:www-data /var/www/html \
       && chmod -R 755 /var/www/html \
       && chmod -R 777 /var/www/html/public/uploads

   EXPOSE 80
   ```

2. Buat file `docker-compose.yml` di root project:
   ```yaml
   version: '3.8'

   services:
     web:
       build: .
       container_name: moji_kafe_web
       restart: always
       ports:
         - "8080:80"
       environment:
         DB_HOST: db
         DB_NAME: moji_kafe
         DB_USER: moji_user
         DB_PASS: moji_password
       depends_on:
         - db
       volumes:
         - uploads_data:/var/www/html/public/uploads

     db:
       image: mysql:8.0
       container_name: moji_kafe_db
       restart: always
       environment:
         MYSQL_DATABASE: moji_kafe
         MYSQL_USER: moji_user
         MYSQL_PASSWORD: moji_password
         MYSQL_ROOT_PASSWORD: root_password
       volumes:
         - db_data:/var/lib/mysql
         - ./database/schema.sql:/docker-entrypoint-initdb.d/1_schema.sql
         - ./database/seed.sql:/docker-entrypoint-initdb.d/2_seed.sql

   volumes:
     db_data:
     uploads_data:
   ```

3. Buat file `.env.example`:
   ```env
   APP_NAME="Moji Kafe"
   APP_ENV=production
   BASE_URL=http://localhost:8080

   DB_HOST=db
   DB_PORT=3306
   DB_NAME=moji_kafe
   DB_USER=moji_user
   DB_PASS=moji_password
   ```

4. Dokumentasikan langkah deploy ke Dokploy:
   - Buat Project baru di Dashboard **Dokploy**.
   - Pilih Deployment via **Git Repository** (GitHub / GitLab) atau **Docker Compose**.
   - Masukkan Environment Variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`).
   - Buat Persistent Volume mount untuk `/var/www/html/public/uploads` agar bukti pembayaran tidak hilang saat container direstart.
   - Deploy aplikasi dan verifikasi domain/SSL domain yang dikonfigurasi di Dokploy Traefik reverse proxy.
```