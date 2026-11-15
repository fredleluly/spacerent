# SpaceRent

Marketplace penyewaan ruang kerja & event space (meeting room, private office, coworking, event space, studio podcast, virtual office) di Jakarta, dengan alur pemesanan ala Airbnb dasar. Dibangun dengan Laravel, Blade, Tailwind CSS v4 (Vite), font Geist, dan ikon Lucide.

## Fitur

- **Pencarian**: filter wilayah, kapasitas, rating, satuan harga, dan **tanggal kosong**; urut harga, rating, terbaru; favorit.
- **Pemesanan**: kalender + pemilih jam (jam terisi abu-abu), durasi, rincian harga (sewa, kebersihan, biaya layanan), pesan jauh hari sampai batas ruangan, anti double-booking (lock + validasi jam buka, jeda, lead time, blokir tanggal).
- **Mode booking**: Instant Book atau perlu persetujuan penjual (otomatis batal bila tidak dijawab).
- **Pembayaran** Midtrans; pembayaran terlambat tidak pernah menghidupkan booking yang slotnya sudah terisi, uangnya masuk antrean refund.
- **Pembatalan & refund** sesuai kebijakan ruangan (Fleksibel / Moderat / Ketat), **jadwal ulang** 1 kali, pembatalan oleh penjual/admin selalu refund penuh.
- **Ulasan & rating nyata**: hanya setelah booking selesai, sub-rating, komentar, **foto**, **anonim**, balasan penjual, laporan dan moderasi admin. Rating dihitung otomatis.
- **Panel penjual**: kalender booking, blokir tanggal, setujui/tolak, ulasan, aturan jadwal per ruangan. **Panel admin**: moderasi, refund (Midtrans atau manual), audit lewat soft delete.
- Email siklus booking dan pengingat H-7 / H-1.
- **Payout penjual**: komisi platform (`SPACERENT_COMMISSION`, default 10%), halaman Pendapatan (saldo, rincian per booking, rekening), penarikan minimum Rp 50.000 setelah masa tahan 1 hari, dan menu Penarikan di admin untuk menandai transfer atau menolak. Catatan: pencairan bank masih manual oleh admin.

- **Chatbot bantuan** di kanan bawah (anonim boleh bertanya): membaca database lewat tool read-only; pesanan dan riwayat hanya untuk pemilik akun yang login. Provider diganti di Admin > Chatbot AI: mesin aturan (bawaan), Google Gemini Flash (kunci terenkripsi), atau Ollama lokal, dengan fallback otomatis. Panduan: `docs/CHATBOT.md`.
- **Peta** (Leaflet + OpenStreetMap): cari ruangan di peta dan "Dekat saya" dari beranda, penjual menetapkan lokasi ruangan (klik peta, cari alamat, lokasi saya), peta kecil di halaman ruangan.
- **Section SDGs** di landing page (SDG 11 sebagai goal utama, plus 8, 9, 12, 13, 17) dengan animasi, angka platform dari database, dan sumber.

**Mulai cepat:** `bash scripts/dev.sh` (backend port 8000, bisa dibuka dari HP). Panduan lengkap, akun, alur klik, dan study case: **`docs/PANDUAN.md`**.

Dokumen: `docs/AUDIT-REVAMP.md` (audit), `docs/STUDY-CASES.md` (katalog kasus uji), `docs/PLAN-MVP.md` (rencana awal).

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
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Zona waktu aplikasi `Asia/Jakarta` (ubah lewat `APP_TIMEZONE`). Selama pengembangan UI jalankan `npm run dev`. Test: `php artisan test` (174 test, termasuk `BookingScenarioProbeTest` dan `BookingLifecycleTest`).

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
