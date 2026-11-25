# Deployment Moji Kafe

## Prasyarat

- Docker Engine dan Docker Compose v2.
- Akses ke repository dan host Dokploy.
- Jika akan menerima pembayaran QRIS, siapkan gambar QRIS resmi terlebih dahulu.

## Menjalankan secara lokal

1. Salin `.env.example` menjadi `.env`.
2. Isi `DB_PASS` dan `MYSQL_ROOT_PASSWORD` dengan password acak yang kuat dan berbeda. Jangan commit `.env`.
3. Pertahankan `APP_ENV=production`. Untuk pengujian lokal dengan akun seed, ubah ke `APP_ENV=development` hanya pada mesin lokal dan jangan gunakan nilai tersebut di production. Sesuaikan `BASE_URL` dan `WEB_PORT` jika port 8080 sudah digunakan.
4. Jalankan:

   ```sh
   docker compose config
   docker compose up -d --build
   docker compose ps
   ```

5. Buka URL yang ditentukan `BASE_URL` (default `http://localhost:8080`).

Apache hanya menyajikan `public/`; endpoint admin dipetakan secara terpisah ke `/admin`. Database tidak dipublikasikan ke port host. Data MySQL disimpan di volume `db_data`, sementara file upload disimpan di `uploads_data`. Pertahankan `DB_NAME=moji_kafe`; skrip skema dan seed saat ini menggunakan nama database tersebut.

Seed admin `admin/admin123` hanya untuk development dan ditolak ketika `APP_ENV=production`. Sebelum membuka aplikasi production, ganti hash password akun admin dengan password kuat melalui pengelolaan database yang aman. Jangan mengaktifkan `APP_ENV=development` di production.

## QRIS

Belum ada gambar QRIS yang disertakan atau dibuat oleh konfigurasi ini. Biarkan `CAFE_QRIS_IMAGE_PATH` kosong sampai gambar resmi tersedia. Halaman pembayaran akan menampilkan bahwa QRIS belum dikonfigurasi dan bukti tidak dapat diunggah, tetapi pilihan QRIS masih tampil saat checkout. Jangan menerima pesanan QRIS dari pelanggan sampai gambar resmi dikonfigurasi; pembayaran tunai tetap dapat digunakan.

Setelah gambar resmi tersedia:

1. Simpan file JPG, JPEG, PNG, atau WEBP di volume upload pada `public/uploads/`, misalnya `public/uploads/qris.png`.
2. Atur `CAFE_QRIS_IMAGE_PATH=uploads/qris.png` pada environment aplikasi. Nilai ini relatif terhadap `public/`, bukan path absolut.
3. Restart/recreate service web dan pastikan QRIS tampil pada halaman pembayaran sebelum mengizinkan pembayaran QRIS.

Simpan gambar di volume `uploads_data` agar tidak hilang saat container web diperbarui.

## Deploy ke Dokploy

1. Buat project di Dokploy dan hubungkan repository, atau pilih deployment Docker Compose.
2. Pastikan Dokploy menggunakan `docker-compose.yml` di root repository.
3. Tambahkan environment variables pada service web dan database sesuai `.env.example`. Gunakan password acak yang kuat; jangan menyalin contoh password dari dokumentasi.
4. Set `APP_ENV=production` dan `BASE_URL` ke URL publik HTTPS yang akan digunakan, tanpa path `/public`.
5. Pastikan service web tersedia pada port container `80` untuk routing domain Traefik. Port host `WEB_PORT` hanya di-bind ke loopback agar akses HTTP langsung tidak melewati HTTPS Traefik.
6. Tambahkan domain di Dokploy, arahkan DNS ke server, aktifkan HTTPS/Let's Encrypt, lalu deploy.
7. Pastikan kedua named volumes (`db_data` dan `uploads_data`) tetap dipertahankan saat redeploy. Atur backup rutin untuk keduanya; jangan gunakan `docker compose down -v` pada data yang ingin disimpan.
8. Verifikasi halaman utama, login admin dengan password production yang sudah diganti, pembuatan order/reservasi, koneksi database, dan persistensi berkas upload.

### Database yang sudah berisi data

Mount `schema.sql` dan `seed.sql` hanya menginisialisasi volume database yang kosong. Untuk database lama, backup database terlebih dahulu dan jalankan migrasi yang relevan satu kali sebelum menggunakan versi aplikasi baru. Untuk kolom token Tahap 11, migrasinya tersedia di `database/migrate_public_access_tokens.sql`. Jangan menghapus volume database saat memperbarui aplikasi.

## Pemantauan

```sh
docker compose logs -f web
docker compose logs -f db
```

Jika container web tidak dapat terhubung ke database, periksa environment `DB_*` dan status health service database di Dokploy.
