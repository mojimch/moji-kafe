# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Moji Kafe — Food Ordering & Table Booking System

**Versi:** 1.0  
**Status:** Final  
**Platform:** Web Application  
**Target Pengguna:** Pelanggan Kafe & Admin Kafe  
**Teknologi:** PHP Native 8.2+, MySQL, Bootstrap 5, HTML, CSS, JavaScript
---

# 1. Ringkasan Produk

**Moji Kafe** adalah aplikasi web yang digunakan untuk membantu pelanggan melakukan pemesanan makanan/minuman dan reservasi meja secara online.

Sistem juga menyediakan halaman admin untuk mengelola menu, meja, pesanan, reservasi, pembayaran, serta melihat ringkasan penjualan.

Aplikasi dibuat dengan konsep sederhana dan mudah digunakan sehingga cocok digunakan oleh kafe skala kecil hingga menengah.

---

# 2. Latar Belakang

Proses pemesanan dan reservasi pada kafe secara manual dapat menyebabkan beberapa masalah, seperti:

- Pelanggan harus datang atau bertanya langsung mengenai menu.
- Pelanggan kesulitan mengetahui harga menu terbaru.
- Proses pencatatan pesanan masih dapat dilakukan secara manual.
- Admin kesulitan memantau status pesanan.
- Informasi ketersediaan meja tidak dapat diketahui pelanggan secara langsung.
- Bukti pembayaran perlu diperiksa dan dicatat secara manual.

Moji Kafe dibuat untuk membantu mendigitalisasi proses tersebut melalui satu aplikasi web.

---

# 3. Tujuan Produk

Tujuan utama Moji Kafe adalah:

1. Menyediakan menu makanan dan minuman secara online.
2. Memungkinkan pelanggan melakukan pemesanan secara online.
3. Memungkinkan pelanggan melakukan reservasi meja.
4. Menampilkan ketersediaan meja.
5. Mendukung pembayaran Cash dan QRIS.
6. Memungkinkan pelanggan mengunggah bukti pembayaran QRIS.
7. Memudahkan admin mengelola pesanan dan reservasi.
8. Memudahkan admin mengelola menu dan meja.
9. Menampilkan dashboard dan laporan penjualan sederhana.
10. Memberikan kode unik agar pelanggan dapat mengecek status pesanan/reservasi.

---

# 4. Target Pengguna

## 4.1 Pelanggan

Pelanggan menggunakan sistem untuk:

- Melihat menu.
- Melihat harga.
- Memilih varian menu.
- Memasukkan produk ke keranjang.
- Memilih Dine-in atau Takeaway.
- Memilih meja untuk Dine-in.
- Melakukan checkout.
- Memilih metode pembayaran.
- Mengunggah bukti pembayaran QRIS.
- Melakukan reservasi meja.
- Mengecek status pesanan.
- Mengecek status reservasi.

Pelanggan **tidak perlu membuat akun atau login**.

---

## 4.2 Admin Kafe

Admin menggunakan sistem untuk:

- Login ke halaman admin.
- Mengelola menu.
- Mengelola kategori menu.
- Mengelola varian menu.
- Mengatur ketersediaan/stok menu.
- Mengelola meja.
- Melihat dan mengelola pesanan.
- Mengubah status pesanan.
- Melihat dan mengelola reservasi.
- Mengubah status reservasi.
- Memverifikasi pembayaran QRIS.
- Melihat dashboard.
- Melihat laporan penjualan.

---

# 5. Scope Produk

## 5.1 Customer Side

### A. Landing Page

Menampilkan:

- Nama/logo Moji Kafe.
- Informasi singkat kafe.
- Tombol Lihat Menu.
- Tombol Pesan.
- Tombol Reservasi Meja.
- Informasi kontak sederhana.

---

### B. Menu

Menu dibagi menjadi tiga kategori:

1. Makanan
2. Minuman
3. Snack

Setiap produk dapat memiliki:

- Nama produk.
- Foto.
- Deskripsi.
- Harga.
- Kategori.
- Status tersedia/tidak tersedia.
- Varian.

---

### C. Varian Produk

Produk dapat memiliki beberapa varian.

Contoh:

**Es Kopi Susu**
- Regular
- Large

Harga varian dapat berbeda.

---

### D. Keranjang

Pelanggan dapat:

- Menambahkan produk.
- Memilih varian.
- Mengubah jumlah.
- Menghapus produk.
- Melihat subtotal.
- Melanjutkan ke checkout.

---

# 6. Proses Pemesanan

Alur pemesanan:

```text
Pelanggan
   ↓
Pilih Menu
   ↓
Pilih Varian
   ↓
Masukkan ke Keranjang
   ↓
Checkout
   ↓
Pilih Dine-in / Takeaway
   ↓
Jika Dine-in → Pilih Meja
   ↓
Isi Nama + Nomor WhatsApp
   ↓
Pilih Pembayaran
   ↓
Cash / QRIS
   ↓
Pesanan Dibuat
   ↓
Mendapatkan Kode Pesanan
   ↓
Cek Status Pesanan
```

---

# 7. Checkout

Pelanggan mengisi:

- Nama.
- Nomor WhatsApp.
- Jenis pesanan:
  - Dine-in
  - Takeaway
- Meja jika memilih Dine-in.
- Metode pembayaran:
  - Cash
  - QRIS

Pelanggan tidak diwajibkan membuat akun.

---

# 8. Pembayaran

## 8.1 Cash

Jika memilih Cash:

- Pesanan dibuat.
- Status pembayaran dicatat sebagai Cash.
- Admin dapat melihat metode pembayaran tersebut.

---

## 8.2 QRIS

Jika memilih QRIS:

1. Sistem menampilkan informasi QRIS.
2. Pelanggan melakukan pembayaran.
3. Pelanggan mengunggah bukti pembayaran.
4. Sistem menyimpan bukti pembayaran.
5. Admin memeriksa bukti.
6. Admin mengonfirmasi atau menolak pembayaran.

Status pembayaran:

```text
Menunggu Verifikasi
        ↓
     Terverifikasi
```

atau

```text
Menunggu Verifikasi
        ↓
       Ditolak
```

---

# 9. Status Pesanan

Status order:

```text
Menunggu
   ↓
Diproses
   ↓
Siap
   ↓
Selesai
```

Pesanan juga dapat dibatalkan:

```text
Menunggu / Diproses
        ↓
    Dibatalkan
```

Admin dapat mengubah status pesanan.

---

# 10. Kode Pesanan

Setiap pesanan mendapatkan kode unik.

Contoh:

```text
ORD-20261124-001
```

Pelanggan dapat menggunakan kode tersebut untuk mengecek status pesanan tanpa login.

---

# 11. Reservasi Meja

Pelanggan dapat melakukan reservasi meja.

Data yang diperlukan:

- Nama pelanggan.
- Nomor WhatsApp.
- Jumlah orang.
- Tanggal reservasi.
- Waktu mulai.
- Meja yang dipilih.

Durasi default reservasi adalah:

**2 jam.**

---

# 12. Pemilihan Meja

Pada halaman reservasi, pelanggan dapat melihat meja yang tersedia.

Contoh:

```text
Meja 01 — 2 Orang — Tersedia
Meja 02 — 4 Orang — Tersedia
Meja 03 — 4 Orang — Terisi
Meja 04 — 6 Orang — Tersedia
```

Pelanggan kemudian memilih meja yang sesuai.

Sistem harus mencegah terjadinya **double booking** pada meja dan waktu yang sama.

---

# 13. Status Reservasi

Status reservasi:

```text
Menunggu
   ↓
Dikonfirmasi
   ↓
Sedang Digunakan
   ↓
Selesai
```

Reservasi juga dapat dibatalkan:

```text
Menunggu / Dikonfirmasi
        ↓
    Dibatalkan
```

---

# 14. Kode Reservasi

Setiap reservasi mendapatkan kode unik.

Contoh:

```text
BOOK-20261124-001
```

Pelanggan dapat menggunakan kode tersebut untuk mengecek status reservasi.

---

# 15. Customer Notification

Sistem tidak menggunakan WhatsApp atau email notification otomatis pada MVP.

Sebagai gantinya, pelanggan mendapatkan:

- Kode pesanan.
- Kode reservasi.
- Halaman pengecekan status.

Contoh:

```text
Masukkan Kode Pesanan
[ ORD-20261124-001 ]

[ Cek Status ]
```

---

# 16. Admin Dashboard

Dashboard admin menampilkan:

- Jumlah pesanan.
- Jumlah reservasi.
- Jumlah meja tersedia.
- Jumlah menu tidak tersedia.
- Total pendapatan.
- Grafik penjualan harian.
- Grafik penjualan mingguan.
- Grafik penjualan bulanan.

---

# 17. Manajemen Menu

Admin dapat melakukan CRUD:

**Create**
- Menambahkan menu.

**Read**
- Melihat daftar menu.

**Update**
- Mengubah menu.

**Delete**
- Menghapus menu.

Data menu:

- Nama.
- Deskripsi.
- Harga.
- Foto.
- Kategori.
- Status tersedia/tidak tersedia.

---

# 18. Manajemen Kategori

Kategori default:

```text
Makanan
Minuman
Snack
```

Admin dapat:

- Menambahkan kategori.
- Melihat kategori.
- Mengubah kategori.
- Menghapus kategori.

---

# 19. Manajemen Varian

Admin dapat:

- Menambahkan varian.
- Mengubah varian.
- Menghapus varian.
- Mengatur harga varian.
- Mengatur ketersediaan varian.

---

# 20. Manajemen Meja

Admin dapat melakukan CRUD meja.

Data meja:

- Nomor meja.
- Kapasitas.
- Status aktif/nonaktif.

Admin juga dapat melihat:

- Meja tersedia.
- Meja terisi.
- Informasi reservasi meja.

---

# 21. Manajemen Pesanan

Admin dapat melihat:

- Kode pesanan.
- Nama pelanggan.
- Nomor WhatsApp.
- Produk.
- Jumlah.
- Total.
- Jenis pesanan.
- Meja.
- Metode pembayaran.
- Status pembayaran.
- Status pesanan.
- Waktu pesanan.

Admin dapat mengubah status pesanan.

---

# 22. Manajemen Reservasi

Admin dapat melihat:

- Kode reservasi.
- Nama pelanggan.
- Nomor WhatsApp.
- Jumlah orang.
- Meja.
- Tanggal.
- Waktu.
- Status.

Admin dapat mengubah status reservasi.

---

# 23. Manajemen Pembayaran

Admin dapat melihat pembayaran.

Untuk QRIS:

- Melihat bukti pembayaran.
- Memeriksa bukti.
- Menyetujui pembayaran.
- Menolak pembayaran.

Sistem mencatat:

- Metode pembayaran.
- Jumlah pembayaran.
- Status.
- Admin yang melakukan verifikasi.
- Waktu verifikasi.

---

# 24. Laporan

Admin dapat melihat laporan sederhana:

### Laporan Harian

- Total pesanan.
- Total pendapatan.

### Laporan Mingguan

- Total pesanan.
- Total pendapatan.

### Laporan Bulanan

- Total pesanan.
- Total pendapatan.

Dashboard juga menyediakan grafik penjualan.

---

# 25. Struktur Halaman Customer

```text
/
├── index.php
├── menu.php
├── detail-menu.php
├── cart.php
├── checkout.php
├── booking.php
├── payment.php
├── upload-proof.php
├── check-order.php
└── check-booking.php
```

---

# 26. Struktur Halaman Admin

```text
/admin/
├── login.php
├── logout.php
├── dashboard.php
├── orders/
├── bookings/
├── products/
├── categories/
├── variants/
├── tables/
├── payments/
└── reports/
```

---

# 27. Struktur Project

```text
moji-kafe/
│
├── public/
│   ├── index.php
│   ├── menu.php
│   ├── cart.php
│   ├── checkout.php
│   ├── booking.php
│   ├── payment.php
│   ├── check-order.php
│   ├── check-booking.php
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   └── app.js
│   │   └── images/
│   │
│   └── uploads/
│       └── payments/
│
├── admin/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── orders/
│   ├── bookings/
│   ├── products/
│   ├── categories/
│   ├── variants/
│   ├── tables/
│   ├── payments/
│   └── reports/
│
├── config/
│   ├── database.php
│   └── config.php
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── auth.php
│   ├── functions.php
│   └── csrf.php
│
└── database/
    ├── schema.sql
    └── seed.sql
```

---

# 28. Database Design

Database:

```text
moji_kafe
```

## 28.1 admins

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| name | VARCHAR | Nama admin |
| username | VARCHAR | Unique |
| password | VARCHAR | Password hash |
| created_at | DATETIME | Waktu dibuat |
| updated_at | DATETIME | Waktu diubah |

---

## 28.2 categories

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| name | VARCHAR | Nama kategori |
| created_at | DATETIME | Waktu dibuat |

Default:

- Makanan
- Minuman
- Snack

---

## 28.3 products

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| category_id | INT | Foreign Key |
| name | VARCHAR | Nama produk |
| description | TEXT | Deskripsi |
| price | DECIMAL | Harga |
| image | VARCHAR | Foto |
| is_available | BOOLEAN | Ketersediaan |
| created_at | DATETIME | Waktu dibuat |
| updated_at | DATETIME | Waktu diubah |

---

## 28.4 product_variants

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| product_id | INT | Foreign Key |
| name | VARCHAR | Nama varian |
| price | DECIMAL | Harga |
| is_available | BOOLEAN | Ketersediaan |
| created_at | DATETIME | Waktu dibuat |

---

## 28.5 tables

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| table_number | VARCHAR | Nomor meja |
| capacity | INT | Kapasitas |
| is_active | BOOLEAN | Status |
| created_at | DATETIME | Waktu dibuat |
| updated_at | DATETIME | Waktu diubah |

---

## 28.6 bookings

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| booking_code | VARCHAR | Kode unik |
| customer_name | VARCHAR | Nama |
| customer_phone | VARCHAR | WhatsApp |
| table_id | INT | Meja |
| booking_date | DATE | Tanggal |
| start_time | TIME | Waktu mulai |
| end_time | TIME | Waktu selesai |
| guest_count | INT | Jumlah orang |
| status | VARCHAR | Status |
| created_at | DATETIME | Waktu dibuat |
| updated_at | DATETIME | Waktu diubah |

Status:

```text
pending
confirmed
in_use
completed
cancelled
```

---

## 28.7 orders

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| order_code | VARCHAR | Kode unik |
| customer_name | VARCHAR | Nama |
| customer_phone | VARCHAR | WhatsApp |
| order_type | ENUM | dine_in/takeaway |
| table_id | INT | Meja |
| subtotal | DECIMAL | Subtotal |
| total | DECIMAL | Total |
| payment_method | ENUM | cash/qris |
| payment_status | VARCHAR | Status pembayaran |
| order_status | VARCHAR | Status pesanan |
| created_at | DATETIME | Waktu dibuat |
| updated_at | DATETIME | Waktu diubah |

---

## 28.8 order_items

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| order_id | INT | Foreign Key |
| product_id | INT | Foreign Key |
| variant_id | INT | Foreign Key |
| product_name | VARCHAR | Snapshot nama |
| variant_name | VARCHAR | Snapshot varian |
| price | DECIMAL | Snapshot harga |
| quantity | INT | Jumlah |
| subtotal | DECIMAL | Subtotal |

Snapshot nama dan harga digunakan agar data historis pesanan tetap benar apabila menu diubah kemudian.

---

## 28.9 payments

| Field | Type | Keterangan |
|---|---|---|
| id | INT | Primary Key |
| order_id | INT | Foreign Key |
| method | ENUM | cash/qris |
| amount | DECIMAL | Jumlah |
| proof_image | VARCHAR | Bukti pembayaran |
| status | VARCHAR | Status |
| verified_by | INT | Admin |
| verified_at | DATETIME | Waktu verifikasi |
| created_at | DATETIME | Waktu dibuat |

---

# 29. Relasi Database

```text
categories
    │
    └── products
           │
           └── product_variants


tables
    ├── bookings
    └── orders
            │
            ├── order_items
            │
            └── payments
                    │
                    └── admins
```

Relasi utama:

```text
categories 1 ─── N products
products   1 ─── N product_variants
tables     1 ─── N bookings
tables     1 ─── N orders
orders     1 ─── N order_items
orders     1 ─── N payments
admins     1 ─── N payments
```

---

# 30. API / Endpoint

## Customer

```text
GET  /api/products
GET  /api/products/{id}

POST /api/orders
GET  /api/orders/{code}

GET  /api/tables/availability
POST /api/bookings
GET  /api/bookings/{code}

POST /api/payments/{order_id}/proof
```

## Admin

```text
POST /admin/login

GET  /api/admin/orders
PUT  /api/admin/orders/{id}/status

GET  /api/admin/bookings
PUT  /api/admin/bookings/{id}/status

CRUD /api/admin/products
CRUD /api/admin/categories
CRUD /api/admin/variants
CRUD /api/admin/tables

PUT /api/admin/payments/{id}/verify
```

---

# 31. Business Rules

### Pesanan

1. Produk hanya dapat dipesan jika tersedia.
2. Varian hanya dapat dipilih jika tersedia.
3. Dine-in harus memiliki meja.
4. Takeaway tidak wajib memiliki meja.
5. Nama dan nomor WhatsApp wajib diisi.
6. Setiap order memiliki kode unik.

### Reservasi

1. Durasi default reservasi adalah 2 jam.
2. Meja yang sudah dibooking pada waktu yang bertabrakan tidak dapat dipilih.
3. Jumlah tamu tidak boleh melebihi kapasitas meja.
4. Setiap booking memiliki kode unik.
5. Meja nonaktif tidak dapat dipilih.

### Pembayaran

1. Cash tidak memerlukan upload bukti.
2. QRIS memerlukan upload bukti pembayaran.
3. Bukti QRIS harus diverifikasi admin.
4. Pembayaran yang belum diverifikasi tetap berstatus menunggu.

---

# 32. Security Requirements

Sistem harus menerapkan keamanan dasar:

- Password admin menggunakan `password_hash()`.
- Login menggunakan session.
- Query database menggunakan PDO prepared statements.
- Validasi input dilakukan di server.
- Validasi upload file.
- Membatasi ukuran file upload.
- Membatasi tipe file yang diperbolehkan.
- Menggunakan CSRF protection untuk form penting.
- Melakukan escaping output untuk mencegah XSS.
- Admin page hanya dapat diakses admin yang sudah login.
- File upload tidak boleh dapat menjalankan script PHP.

---

# 33. Non-Functional Requirements

## Performance

Halaman harus dapat dimuat dengan cepat pada koneksi internet normal.

## Usability

Interface harus sederhana dan mudah digunakan.

## Responsive

Website harus dapat digunakan pada:

- Desktop.
- Laptop.
- Tablet.
- Smartphone.

## Maintainability

Kode harus dipisahkan berdasarkan fungsi agar mudah dikembangkan.

## Compatibility

Aplikasi ditargetkan berjalan pada browser modern seperti:

- Google Chrome.
- Microsoft Edge.
- Mozilla Firefox.

---

# 34. UI/UX

Gaya desain yang digunakan:

- Modern.
- Sederhana.
- Bersih.
- Responsive.
- Cocok untuk website kafe.

Komponen Bootstrap 5 yang dapat digunakan:

- Navbar.
- Card.
- Button.
- Modal.
- Form.
- Table.
- Badge.
- Alert.
- Toast.
- Pagination.
- Dropdown.

---

# 35. MVP Priority

## P0 — Wajib

### Customer

- Landing page.
- Menu.
- Kategori.
- Detail menu.
- Varian.
- Keranjang.
- Checkout.
- Dine-in/Takeaway.
- Pemilihan meja.
- Cash.
- QRIS.
- Upload bukti QRIS.
- Kode pesanan.
- Cek status pesanan.
- Reservasi meja.
- Kode reservasi.
- Cek status reservasi.

### Admin

- Login.
- Dashboard.
- CRUD menu.
- CRUD kategori.
- CRUD varian.
- Status ketersediaan menu.
- CRUD meja.
- Manajemen pesanan.
- Manajemen reservasi.
- Manajemen pembayaran.
- Verifikasi QRIS.
- Laporan penjualan sederhana.

---

# 36. P1 — Pengembangan Berikutnya

Fitur yang dapat ditambahkan setelah MVP:

- Notifikasi WhatsApp.
- Login pelanggan.
- Riwayat pesanan pelanggan.
- Promo/diskon.
- Voucher.
- Rating menu.
- Cetak struk.
- Export laporan ke Excel/PDF.
- Manajemen staff dengan role berbeda.

---

# 37. Development Roadmap

## Phase 1 — Foundation

- Membuat project PHP.
- Setup XAMPP/Apache/MySQL.
- Membuat database.
- Membuat koneksi PDO.
- Membuat struktur folder.
- Membuat layout Bootstrap.

## Phase 2 — Master Data

- Kategori.
- Produk.
- Varian.
- Meja.
- Admin.

## Phase 3 — Customer Ordering

- Menu.
- Detail produk.
- Cart.
- Checkout.
- Order.

## Phase 4 — Booking

- Ketersediaan meja.
- Reservasi.
- Validasi double booking.
- Status reservasi.

## Phase 5 — Payment

- Cash.
- QRIS.
- Upload bukti.
- Verifikasi admin.

## Phase 6 — Dashboard

- Statistik.
- Pendapatan.
- Grafik penjualan.
- Laporan.

## Phase 7 — Testing & Security

- Validasi form.
- Testing database.
- Testing order.
- Testing booking.
- Testing payment.
- Testing login.
- Security review.

---

# 38. Acceptance Criteria

Produk dianggap memenuhi MVP apabila:

### Customer

- [ ] Customer dapat melihat menu.
- [ ] Customer dapat memilih kategori.
- [ ] Customer dapat memilih varian.
- [ ] Customer dapat memasukkan produk ke cart.
- [ ] Customer dapat checkout.
- [ ] Customer dapat memilih Dine-in/Takeaway.
- [ ] Customer dapat memilih meja.
- [ ] Customer dapat melakukan reservasi.
- [ ] Customer dapat memilih Cash/QRIS.
- [ ] Customer dapat upload bukti QRIS.
- [ ] Customer mendapatkan kode order.
- [ ] Customer mendapatkan kode booking.
- [ ] Customer dapat mengecek status order.
- [ ] Customer dapat mengecek status booking.

### Admin

- [ ] Admin dapat login.
- [ ] Admin dapat mengelola kategori.
- [ ] Admin dapat mengelola produk.
- [ ] Admin dapat mengelola varian.
- [ ] Admin dapat mengelola meja.
- [ ] Admin dapat melihat order.
- [ ] Admin dapat mengubah status order.
- [ ] Admin dapat melihat booking.
- [ ] Admin dapat mengubah status booking.
- [ ] Admin dapat melihat bukti QRIS.
- [ ] Admin dapat memverifikasi pembayaran.
- [ ] Admin dapat melihat dashboard.
- [ ] Admin dapat melihat laporan penjualan.

---

# 39. Success Metrics

Keberhasilan MVP dapat diukur melalui:

1. Customer berhasil membuat order tanpa login.
2. Order berhasil masuk ke dashboard admin.
3. Admin dapat mengubah status order.
4. Customer dapat melihat perubahan status.
5. Customer berhasil melakukan reservasi.
6. Sistem mencegah double booking.
7. QRIS proof berhasil diunggah.
8. Admin berhasil melakukan verifikasi pembayaran.
9. Dashboard menampilkan data penjualan dengan benar.
10. Tidak terjadi error pada alur utama aplikasi.

---

# 40. Kesimpulan

**Moji Kafe** merupakan aplikasi web untuk mendigitalisasi proses pemesanan makanan/minuman dan reservasi meja.

Sistem memiliki dua jenis pengguna utama:

- **Pelanggan**
- **Admin Kafe**

Pelanggan dapat menggunakan sistem tanpa membuat akun untuk melihat menu, memesan makanan/minuman, memilih Dine-in atau Takeaway, melakukan reservasi meja, membayar menggunakan Cash/QRIS, serta mengecek status menggunakan kode unik.

Admin memiliki kontrol penuh terhadap menu, kategori, varian, meja, pesanan, reservasi, pembayaran, serta laporan.

MVP akan dikembangkan menggunakan:

```text
Frontend
HTML5
CSS3
Bootstrap 5
JavaScript

Backend
PHP 8.x
PDO

Database
MySQL 8.x

Server
Apache / XAMPP
```

Fokus utama pengembangan adalah menghasilkan aplikasi yang **sederhana, mudah digunakan, responsive, aman, dan dapat mendukung seluruh alur utama pemesanan serta reservasi Moji Kafe**.