# SpaceRent

Website penyewaan ruang kerja & event space (meeting room, private office, coworking, event space, studio podcast, virtual office) di Jakarta. Dibangun dengan Laravel, Blade, dan Tailwind CSS (CDN).

## Fitur

- Beranda dengan hero, filter (kata kunci, wilayah, kapasitas), dan tab kategori
- Daftar ruangan dengan halaman detail dan fasilitas
- Form booking dengan estimasi total otomatis, validasi, dan kode booking
- Halaman konfirmasi dengan tombol konfirmasi via WhatsApp
- Syarat & Ketentuan, FAQ, dan form Hubungi Kami

## Peran Pengguna (Marketplace)
Ada tiga peran. Pembeli dan penjual mendaftar sendiri di `/daftar` (pilih "Pembeli" atau "Penjual"), login di `/masuk`.

| Peran | Akses | Akun demo |
|---|---|---|
| Pembeli | Cari & booking ruangan, bayar via Midtrans, **Pesanan Saya** (`/akun/pesanan`), chat dengan penjual (`/akun/chat`), profil | `pembeli@spacerent.id` / `pembeli12345` |
| Penjual | Panel `/penjual`: dashboard pendapatan, CRUD ruangan milik sendiri, booking pada ruangannya (batal/selesai), balas chat pembeli | `penjual@spacerent.id` / `penjual12345` |
| Admin | `/admin`: semua ruangan, booking, refund, pengguna, chat | `admin@spacerent.id` / `admin12345` |

Catatan: booking wajib login sebagai pembeli. Seluruh pembayaran masuk ke akun Midtrans platform; pencairan dana ke penjual belum ada di MVP.

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

## Pembayaran Midtrans (Sandbox)

1. Daftar di https://dashboard.sandbox.midtrans.com dan ambil Server Key & Client Key (Settings > Access Keys).
2. Isi `.env` (jangan commit key asli):

```
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

3. Midtrans perlu memanggil webhook publik. Jalankan tunnel (cloudflared/ngrok) lalu set Payment Notification URL di dashboard Midtrans (Settings > Payment > Notification URL) ke `https://<domain-tunnel>/midtrans/notification`. Set juga `APP_URL` ke domain tunnel.
4. Uji pembayaran dengan Simulator di https://simulator.sandbox.midtrans.com (virtual account, QRIS, e-wallet). Kartu uji: `4811 1111 1111 1114`, CVV `123`, expiry bulan/tahun mendatang, OTP `112233`.

Batas bayar booking 30 menit; booking yang kedaluwarsa dibatalkan otomatis oleh scheduler.

## Scheduler & Queue

Jalankan scheduler (untuk meng-expire booking belum dibayar) di development:

```sh
php artisan schedule:work
```

Di produksi, tambahkan cron `* * * * * php /path/artisan schedule:run`. Jika memakai queue (`QUEUE_CONNECTION=database`), jalankan juga `php artisan queue:work`.

## Chat

Customer dapat chat dengan pemilik dari halaman ruangan atau booking. Admin membalas lewat menu Chat di panel admin.

## Konfigurasi

Atur nomor kontak lewat `.env` (default ada di `config/spacerent.php`):

```
SPACERENT_WHATSAPP=6281234567890
SPACERENT_HOTLINE=0812-3456-7890
SPACERENT_EMAIL=halo@spacerent.id
```

Data ruangan ada di `database/seeders/SpaceSeeder.php`.
