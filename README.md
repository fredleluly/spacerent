# SpaceRent

Website penyewaan ruang kerja & event space (meeting room, private office, coworking, event space, studio podcast, virtual office) di Jakarta. Dibangun dengan Laravel, Blade, dan Tailwind CSS (CDN).

## Fitur

- Beranda dengan hero, filter (kata kunci, wilayah, kapasitas), dan tab kategori
- Daftar ruangan dengan halaman detail dan fasilitas
- Form booking dengan estimasi total otomatis, validasi, dan kode booking
- Halaman konfirmasi dengan tombol konfirmasi via WhatsApp
- Syarat & Ketentuan, FAQ, dan form Hubungi Kami

## Panel Admin

Buka `/admin`. Login bawaan (dibuat oleh seeder):

- Email: `admin@spacerent.id`
- Password: `admin12345`

Ganti lewat `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env` sebelum `php artisan db:seed --class=AdminSeeder`.

Menu admin: Dashboard (statistik), CRUD Ruangan (upload gambar atau URL), Booking (cari, filter, ubah status, hapus), dan Pesan Masuk.

## Menjalankan

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Buka http://127.0.0.1:8000.

## Konfigurasi

Atur nomor kontak lewat `.env` (default ada di `config/spacerent.php`):

```
SPACERENT_WHATSAPP=6281234567890
SPACERENT_HOTLINE=0812-3456-7890
SPACERENT_EMAIL=halo@spacerent.id
```

Data ruangan ada di `database/seeders/SpaceSeeder.php`.
