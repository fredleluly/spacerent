# Panduan SpaceRent

Panduan lengkap untuk menjalankan, mencoba, dan memahami SpaceRent (marketplace sewa ruang kerja ala Airbnb dasar).
Dokumen terkait: [STUDY-CASES.md](STUDY-CASES.md) (katalog kasus uji), [AUDIT-REVAMP.md](AUDIT-REVAMP.md) (audit awal), [../README.md](../README.md).

## Daftar isi
1. [Mulai cepat](#1-mulai-cepat)
2. [Server: port, backend, frontend](#2-server-port-backend-frontend)
3. [Isi file .env](#3-isi-file-env)
4. [Buka dari HP](#4-buka-dari-hp)
5. [Akun dan kata sandi](#5-akun-dan-kata-sandi)
6. [Ruangan demo](#6-ruangan-demo)
7. [Pembeli: pertama kali dan fitur](#7-pembeli--penyewa)
8. [Alur dan klik langkah demi langkah](#8-alur-dan-klik)
9. [Penjual](#9-penjual)
10. [Admin](#10-admin)
11. [Bisa dan tidak bisa, per peran](#11-bisa-dan-tidak-bisa-per-peran)
12. [Aturan bisnis (angka)](#12-aturan-bisnis)
13. [Study case untuk dicoba manual](#13-study-case-untuk-dicoba-manual)
14. [Pembayaran Midtrans sandbox](#14-pembayaran-midtrans-sandbox)
15. [Scheduler dan email](#15-scheduler-dan-email)
16. [Test otomatis](#16-test-otomatis)
17. [Masalah umum](#17-masalah-umum)
18. [Batasan saat ini](#18-batasan-saat-ini)

---

## 1. Mulai cepat

Dari folder `spacerent/`:

```bash
bash scripts/dev.sh
```

Skrip ini otomatis menyiapkan `.env`, composer, npm, database SQLite, migrasi, data demo, build frontend, lalu menjalankan backend, scheduler, dan queue. Setelah selesai, buka:

| Dari | Alamat |
|---|---|
| Laptop | http://localhost:8000 |
| HP (Wi-Fi sama) | `http://<IP-laptop>:8000` (skrip mencetak IP-nya) |
| Admin | http://localhost:8000/admin/login |

Berhenti: tekan `Ctrl+C` di terminal skrip, atau dari terminal lain `bash scripts/dev.sh stop`.

Perintah lain:

| Perintah | Fungsi |
|---|---|
| `bash scripts/dev.sh status` | Cek yang berjalan dan alamat untuk HP |
| `bash scripts/dev.sh hmr` | Frontend mode Vite dev (hot reload, hanya laptop) |
| `bash scripts/dev.sh setup` | Hanya menyiapkan (tanpa menjalankan server) |
| `bash scripts/dev.sh test` | Menjalankan semua test |
| `bash scripts/dev.sh reset` | Hapus database dan isi ulang data demo (minta konfirmasi `ya`) |
| `PORT=9000 bash scripts/dev.sh` | Ganti port backend |

**Prasyarat:** PHP 8.3+, Composer, Node.js 20+ (diuji di Node 22). Di Windows jalankan skrip dari Git Bash.

## 2. Server: port, backend, frontend

| Bagian | Port | Keterangan |
|---|---|---|
| **Backend** (Laravel, `php artisan serve`) | **8000** | Satu-satunya server yang wajib. Melayani halaman dan API. |
| **Frontend** (Tailwind + JS) | tidak ada | Sudah di-build ke `public/build` dan dilayani backend, jadi **tidak ada server frontend terpisah** pada mode biasa. Rebuild: `npm run build`. |
| Vite dev (opsional) | 5173 | Hanya bila memakai `bash scripts/dev.sh hmr` (edit CSS atau JS dan browser langsung berubah). |
| Scheduler | - | `php artisan schedule:work`: membatalkan booking kedaluwarsa tiap menit, menyelesaikan booking, mengirim pengingat tiap jam. |
| Queue | - | `php artisan queue:work`: memproses email yang diantrekan. |

Cek cepat: `bash scripts/dev.sh status`.

Manual tanpa skrip (empat terminal bila perlu):

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed && php artisan storage:link
npm run build                              # frontend (sekali, atau 'npm run dev' untuk HMR)
php artisan serve --host=0.0.0.0 --port=8000   # backend
php artisan schedule:work                  # scheduler
php artisan queue:work                     # queue
```

## 3. Isi file .env

Skrip membuat `.env` dari `.env.example`. Variabel yang penting:

| Variabel | Contoh | Fungsi |
|---|---|---|
| `APP_NAME` | `SpaceRent` | Nama di email |
| `APP_ENV` | `local` | Gunakan `production` hanya saat deploy |
| `APP_KEY` | (otomatis) | Dibuat `php artisan key:generate` |
| `APP_DEBUG` | `true` | Matikan (`false`) di produksi |
| `APP_URL` | `http://localhost:8000` | Dipakai tautan di email dan webhook. Isi domain tunnel bila menguji webhook Midtrans |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu aturan jadwal (bawaan sudah WIB) |
| `DB_CONNECTION` | `sqlite` | File `database/database.sqlite`. Untuk uji race sungguhan pakai MySQL/PostgreSQL |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | Semuanya tersimpan di database, tanpa Redis |
| `MAIL_MAILER` | `log` | `log` = email ditulis ke `storage/logs/laravel.log` (tidak terkirim). Untuk email nyata isi `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` |
| `MIDTRANS_SERVER_KEY` | `SB-Mid-server-xxxx` | **Wajib agar tombol Bayar jalan.** Dari dashboard sandbox Midtrans |
| `MIDTRANS_CLIENT_KEY` | `SB-Mid-client-xxxx` | Pasangan kunci di atas |
| `MIDTRANS_IS_PRODUCTION` | `false` | Biarkan `false` untuk sandbox |
| `SPACERENT_SERVICE_FEE` | `5` | Biaya layanan pembeli (%) |
| `SPACERENT_COMMISSION` | `10` | Komisi platform dari penjual (%) |
| `SPACERENT_WHATSAPP`, `SPACERENT_HOTLINE`, `SPACERENT_EMAIL` | | Kontak yang tampil di situs |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | `admin@spacerent.id` / `admin12345` | Akun admin yang dibuat seeder |

Kunci Midtrans: daftar di https://dashboard.sandbox.midtrans.com, lalu Settings, Access Keys. Jangan commit kunci asli.

Aturan lain (batas pending, jendela ulasan, masa tahan payout, dan sebagainya) ada di `config/spacerent.php`.

## 4. Buka dari HP

Yang dibutuhkan:
1. **Laptop dan HP di Wi-Fi yang sama.**
2. Backend berjalan di `0.0.0.0` (skrip `dev.sh` sudah begitu; `php artisan serve` biasa hanya 127.0.0.1 dan HP tidak bisa masuk).
3. **Izinkan port 8000 di firewall Windows** (PowerShell sebagai Administrator, sekali saja):
   ```powershell
   netsh advfirewall firewall add rule name="SpaceRent 8000" dir=in action=allow protocol=TCP localport=8000
   ```
4. Buka `http://<IP-laptop>:8000` di browser HP. IP dicetak skrip; atau cek `ipconfig`, ambil IPv4 Wi-Fi (bukan `192.168.56.x` milik VirtualBox).
5. Tidak perlu mengubah `.env` untuk sekadar melihat dan memesan. `APP_URL` hanya mempengaruhi tautan di email.

Catatan HP:
- Pembayaran Midtrans memakai popup Snap dan bisa dicoba dari HP.
- **Webhook Midtrans butuh alamat publik** (HP dan Wi-Fi tidak cukup). Tanpa webhook, status bayar tetap tersinkron saat Anda kembali ke halaman booking setelah membayar. Untuk webhook penuh pakai tunnel (cloudflared atau ngrok), set `APP_URL` ke domain tunnel, dan isi Notification URL Midtrans: `https://<domain>/midtrans/notification`.
- Mode `hmr` (Vite dev) hanya untuk laptop. Untuk HP gunakan mode biasa (frontend ter-build).

## 5. Akun dan kata sandi

Semua dibuat otomatis oleh seeder (`php artisan db:seed`). **Kata sandi ini hanya untuk lokal**; ganti sebelum produksi.

| Peran | Email | Kata sandi | Login di |
|---|---|---|---|
| Pembeli demo | `pembeli@spacerent.id` | `pembeli12345` | `/masuk` |
| Penjual demo | `penjual@spacerent.id` | `penjual12345` | `/masuk` |
| Admin | `admin@spacerent.id` | `admin12345` | `/admin/login` |
| Pembeli lain (pemilik ulasan demo) | `raka@contoh.id`, `intan@contoh.id`, `bayu@contoh.id`, `maya@contoh.id`, `dimas@contoh.id` | `demo12345` | `/masuk` |

Pembeli dan penjual baru bisa mendaftar sendiri di `/daftar`. Admin tidak bisa dibuat dari form publik.

Setelah login, Anda diarahkan ke: pembeli `/akun/pesanan`, penjual `/penjual/dashboard`, admin `/admin/dashboard`.

## 6. Ruangan demo

Semua dimiliki penjual demo. Aturan berbeda-beda agar tiap fitur bisa dicoba:

| Ruangan | Satuan | Harga | Mode | Kebijakan batal | Catatan |
|---|---|---|---|---|---|
| Executive Boardroom Sudirman | jam | Rp 350.000 | Instant | Moderat | Buka 08-22, jeda 30 menit, kebersihan Rp 25.000, ada ulasan |
| Creatives Pod and Podcast Studio | jam | Rp 275.000 | Instant | Moderat | Jeda 30 menit |
| Huddle Room Kebayoran | jam | Rp 120.000 | Instant | Moderat | Murah untuk uji coba bayar |
| Workshop Hall Tebet | jam | Rp 1.200.000 | Instant | Ketat | Minimum 2 jam, jeda 60 menit |
| Open Desk Coworking Kuningan | hari | Rp 150.000 | Instant | Fleksibel | Buka Senin sampai Sabtu |
| Grand Ballroom Kelapa Gading | hari | Rp 25.000.000 | **Perlu persetujuan** | Ketat | Kebersihan Rp 1,5 juta, pesan minimal 72 jam sebelumnya |
| Skyline Private Suite | bulan | Rp 18.500.000 | Instant | Ketat | Senin sampai Jumat |
| Collab Studio Kebon Jeruk | bulan | Rp 2.200.000 | Instant | Fleksibel | |
| Virtual Office Mega Kuningan | bulan | Rp 2.500.000 | Instant | Ketat | |

## 7. Pembeli / penyewa

### Pertama kali (urutan yang disarankan)
1. Buka beranda. **Menjelajah tidak perlu akun.**
2. Klik **Daftar**, pilih **Menyewa ruangan**, isi nama, email, **nomor WhatsApp** (wajib, dipakai pemilik menghubungi), kata sandi. Atau masuk dengan akun demo.
3. Cari ruangan (kata kunci, wilayah, **tanggal**, kategori, filter rating, urut harga).
4. Klik kartu ruangan. Pilih tanggal, jam, durasi, jumlah peserta. Centang persetujuan syarat. Klik **Pesan sekarang**.
5. Di halaman booking, klik **Bayar** (popup Midtrans). Booking terkonfirmasi otomatis setelah bayar.
6. Setelah sewa selesai, tulis ulasan di halaman booking.

Tamu yang belum login hanya melihat tombol **Masuk untuk memesan**.

### Yang bisa dilakukan pembeli
- Cari dan filter ruangan; lihat ketersediaan di kalender dan jam; simpan **favorit**.
- Pesan jauh hari sampai batas ruangan (bawaan 365 hari), lihat rincian harga sebelum bayar.
- Bayar online, lihat hitung mundur batas bayar (30 menit).
- **Batalkan** booking sendiri (jumlah refund tampil sebelum konfirmasi), **ubah jadwal** 1 kali.
- **Chat** dengan pemilik (dari halaman ruangan atau booking).
- Tulis **ulasan** (rating, sub-rating, komentar, foto hingga 4, anonim), edit dalam 7 hari, laporkan ulasan orang lain.
- Lihat semua pesanan di **Pesanan saya** (tab: Semua, Perlu dibayar, Akan datang, Selesai, Dibatalkan).

## 8. Alur dan klik

### A. Pesan ruangan Instant Book dan bayar
1. Beranda, klik kartu **Huddle Room Kebayoran**.
2. Di kartu pemesanan (kanan, atau tombol **Pilih jadwal** di HP): klik sebuah **tanggal** (hari lampau dan hari tutup tercoret).
3. Klik **jam mulai** (jam abu-abu = terisi). Atur durasi dengan **+ / -**. Rincian harga berubah langsung.
4. Buka **Data pemesan dan catatan** bila perlu mengubah nama, telepon, catatan.
5. Centang **Saya setuju...** lalu **Pesan sekarang**.
6. Halaman booking terbuka: status **Perlu dibayar** dengan hitung mundur. Klik **Bayar Rp ...**.
7. Popup Midtrans: lihat [bagian 14](#14-pembayaran-midtrans-sandbox). Setelah sukses, status menjadi **Terkonfirmasi**.

### B. Ruangan perlu persetujuan
1. Buka **Grand Ballroom Kelapa Gading**, pilih tanggal (minimal 72 jam ke depan), **Ajukan permintaan**.
2. Status **Menunggu persetujuan pemilik**; tombol bayar belum ada.
3. Login sebagai penjual, **Booking**, buka booking tersebut, klik **Setujui permintaan** (atau **Tolak** dengan alasan).
4. Kembali ke pembeli: status berubah **Perlu dibayar** (30 menit), lalu bayar seperti alur A.
5. Bila penjual diam 24 jam, permintaan batal otomatis (scheduler).

### C. Batalkan booking
1. **Pesanan saya**, buka booking, klik **Batalkan booking**.
2. Dialog menampilkan persen dan nominal refund sesuai kebijakan ruangan. Klik **Ya, batalkan**.
3. Booking belum dibayar: langsung batal tanpa biaya. Sudah dibayar: status **Dibatalkan, refund diproses**; admin memproses refund.

### D. Ubah jadwal (booking lunas)
1. Buka booking terkonfirmasi, klik **Ubah jadwal**.
2. Pilih tanggal dan jam baru, **Ubah jadwal**. Syarat: slot kosong, minimal 24 jam sebelum mulai, hanya 1 kali. Gagal = jadwal lama tetap aman.

### E. Tulis ulasan (setelah selesai)
1. Penjual menekan **Tandai selesai** pada booking yang sudah mulai (atau tunggu scheduler `bookings:complete` setelah jam selesai).
2. Pembeli membuka booking, bagian **Bagaimana pengalaman Anda?**: klik bintang, isi komentar, unggah foto, centang **anonim** bila mau, **Kirim ulasan**.
3. Ulasan muncul di halaman ruangan, rating ruangan terhitung ulang.

### F. Chat
Halaman ruangan atau booking, klik **Chat pemilik**; balasan di menu **Pesan** (pembeli) atau **Chat** (penjual).

## 9. Penjual

Login `penjual@spacerent.id`. Menu samping: Dashboard, Kalender, Ruangan, Booking, Ulasan, **Pendapatan**, Chat.

### Pertama kali sebagai penjual
1. Daftar di `/daftar` pilih **Menyewakan ruangan**.
2. **Ruangan**, **Tambah ruangan**. Isi informasi dasar, harga dan biaya, **jadwal dan aturan** (jam buka, hari buka, durasi minimum dan maksimum, jeda, pesan minimal, batas hari ke depan, kebijakan batal, Instant Book atau persetujuan), aturan dan info check-in, foto sampul dan galeri. Simpan.
3. Buka **Pendapatan**, isi **rekening bank** (agar bisa menarik dana).

### Yang bisa dilakukan penjual
- Kelola ruangan miliknya (tambah, ubah, nonaktifkan, hapus bila tidak ada booking aktif atau lunas).
- **Kalender** bulanan semua booking dan penutupan; saring per ruangan.
- **Tutup tanggal** (edit ruangan, bagian Tutup tanggal): tidak bisa menutup tanggal yang sudah punya booking aktif.
- **Setujui atau tolak** permintaan, **batalkan** booking (pembeli otomatis refund penuh), **tandai selesai** (setelah mulai), **tandai tidak hadir**.
- **Balas ulasan** (1 kali per ulasan).
- **Pendapatan**: lihat saldo, rincian komisi per booking, **tarik dana** (minimum Rp 50.000, masa tahan 1 hari setelah selesai), riwayat penarikan.
- Chat dengan pembeli.

## 10. Admin

Login di `/admin/login`. Menu: Dashboard, Ruangan, Booking, **Penarikan**, Ulasan, Pesan masuk, Chat, Pengguna.

- **Booking**: batalkan (refund penuh), tandai selesai atau tidak hadir, arsipkan booking yang tidak pernah dibayar. **Tidak ada lagi dropdown ubah status bebas.**
- **Refund**: pada booking berstatus *Menunggu refund*, klik **Refund lewat Midtrans** (API sandbox) atau **Tandai sudah direfund manual**.
- **Penarikan**: lihat permintaan penjual beserta rekening, klik **Sudah ditransfer** (isi no. referensi) setelah Anda mentransfer uangnya, atau **Tolak** dengan alasan (dana kembali ke saldo penjual).
- **Ulasan**: sembunyikan atau tampilkan kembali; filter *Dilaporkan*.
- Kelola semua ruangan, lihat pengguna, pesan kontak, dan chat.

## 11. Bisa dan tidak bisa, per peran

| Aksi | Tamu | Pembeli | Penjual | Admin |
|---|:--:|:--:|:--:|:--:|
| Lihat ruangan, ulasan, kalender ketersediaan | ya | ya | ya | ya |
| Memesan ruangan | tidak (diminta masuk) | **ya** | tidak | tidak |
| Bayar, batalkan, ubah jadwal booking sendiri | tidak | **ya** | tidak | tidak |
| Melihat booking orang lain | tidak | tidak | hanya ruangannya | semua |
| Menulis ulasan | tidak | ya (booking selesai miliknya) | tidak | tidak |
| Membalas ulasan | tidak | tidak | ya (ruangannya) | tidak |
| Menyembunyikan ulasan | tidak | tidak | tidak | ya |
| Setujui atau tolak permintaan | tidak | tidak | ya (ruangannya) | tidak |
| Tutup tanggal, kalender | tidak | tidak | ya | tidak |
| Tarik dana | tidak | tidak | ya | memproses |
| Refund | tidak | tidak | tidak (otomatis tercatat) | ya |
| Mengubah status booking sembarang | tidak | tidak | tidak | **tidak** (hanya aksi bernama) |
| Menghapus booking lunas atau ruangan yang punya booking aktif | tidak | tidak | tidak | **tidak** |

Batasan penting yang akan Anda temui:
- Penjual dan admin yang login **tidak bisa memesan** (hanya akun pembeli).
- Pembeli tidak bisa membayar atau membatalkan booking orang lain (ditolak 403).
- Ulasan hanya setelah booking **selesai**, satu per booking, dalam 30 hari.
- Slot yang sudah terisi, di luar jam buka, di hari tutup, atau tanggal yang ditutup penjual **tidak bisa dipilih**.
- Maksimal **3 booking menunggu** per pembeli sekaligus (anti menahan slot).

## 12. Aturan bisnis

| Hal | Aturan |
|---|---|
| Batas bayar | 30 menit sejak booking dibuat (`config/midtrans.php`) |
| Persetujuan penjual | 24 jam, lewat itu batal otomatis |
| Biaya layanan pembeli | 5% dari (sewa + kebersihan) |
| Komisi penjual | 10% dari (sewa + kebersihan yang ditahan) |
| Kebijakan Fleksibel | refund 100% sampai 24 jam sebelum mulai |
| Kebijakan Moderat | 100% sampai 48 jam, 50% sampai 24 jam |
| Kebijakan Ketat | 100% sampai 7 hari, 50% sampai 3 hari |
| Biaya layanan | hanya dikembalikan pada refund 100% |
| Dibatalkan penjual, admin, atau sistem | selalu refund penuh |
| Jadwal ulang | 1 kali, minimal 24 jam sebelum mulai, slot baru harus kosong |
| Jendela ulasan | 30 hari setelah selesai; edit sampai 7 hari |
| Foto ulasan | maksimal 4, JPG/PNG/WebP, 3 MB per foto |
| Payout | minimum Rp 50.000, masa tahan 1 hari setelah selesai |
| Grid jam | kelipatan 30 menit |

## 13. Study case untuk dicoba manual

Kolom *Hasil* adalah yang seharusnya terjadi. (Daftar lengkap ratusan kasus: [STUDY-CASES.md](STUDY-CASES.md).)

| # | Langkah | Hasil |
|---|---|---|
| 1 | Pembeli A pesan Huddle Room tanggal X jam 10:00-12:00 | Berhasil, status Perlu dibayar |
| 2 | Login pembeli lain (`raka@contoh.id`), pesan ruangan dan jam yang sama | **Ditolak**: slot terisi (jam 10 abu-abu di kalender) |
| 3 | Pembeli lain pesan **jam berbeda** di tanggal yang sama (mis. 14:00) | **Berhasil** |
| 4 | Pesan jam 12:00 persis setelah booking lain selesai (ruangan tanpa jeda) | Berhasil; pada ruangan berjeda 30 menit, 12:00 abu-abu dan 12:30 bisa |
| 5 | Biarkan booking tidak dibayar 30 menit (atau jalankan `php artisan bookings:expire` setelah waktu lewat) | Batal otomatis, slot terbuka lagi |
| 6 | Pesan 6 bulan ke depan di ruangan jam | Berhasil (dalam batas 365 hari) |
| 7 | Coba tanggal di hari tutup (Open Desk di hari Minggu) | Hari tercoret, tidak bisa dipilih |
| 8 | Buat 4 booking menunggu bayar sekaligus | Booking ke-4 ditolak (maks 3) |
| 9 | Klik **Pesan sekarang** dua kali cepat | Hanya satu booking |
| 10 | Bayar lalu batalkan > 48 jam sebelum mulai (kebijakan Moderat) | Refund 100% |
| 11 | Batalkan 24 sampai 48 jam sebelum mulai | Refund 50% dari sewa, biaya layanan tidak kembali |
| 12 | Batalkan < 24 jam sebelum mulai | Refund 0 |
| 13 | Penjual membatalkan booking lunas (kapan pun sebelum mulai) | Refund penuh ke pembeli |
| 14 | Ubah jadwal ke slot terisi | Ditolak, jadwal lama aman |
| 15 | Ubah jadwal dua kali | Kedua ditolak |
| 16 | Pesan Grand Ballroom | Menunggu persetujuan; tombol bayar belum ada |
| 17 | Penjual tolak permintaan | Booking batal, slot terbuka, pembeli tidak dikenai biaya |
| 18 | Pembeli menulis ulasan pada booking yang belum selesai | Form tidak tersedia |
| 19 | Selesaikan booking lalu tulis ulasan anonim dengan 2 foto | Tampil sebagai "Pengguna SpaceRent" dengan foto; rating ruangan berubah |
| 20 | Unggah 5 foto atau file `.php` yang diganti nama | Ditolak |
| 21 | Admin sembunyikan ulasan | Rating dan jumlah ulasan dihitung ulang |
| 22 | Penjual tutup tanggal yang sudah punya booking aktif | Ditolak, harus dibatalkan dulu |
| 23 | Pembeli A membuka `/booking/<kode milik B>` | 403 |
| 24 | Penjual menandai selesai sebelum jadwal mulai | Ditolak |
| 25 | Admin mencoba menghapus booking lunas | Ditolak |
| 26 | Penjual tarik dana tanpa rekening, atau saldo < Rp 50.000 | Ditolak dengan pesan jelas |
| 27 | Tarik dana, lalu admin **Tolak** | Dana kembali ke saldo tersedia |
| 28 | Tarik dana, lalu admin **Sudah ditransfer** | Muncul di Sudah dicairkan |
| 29 | Ruangan baru tanpa ulasan | Label **Baru**, bukan bintang palsu |
| 30 | Pembayaran datang terlambat setelah slot diambil orang lain (simulasi webhook) | Booking tidak hidup lagi; uang masuk antrean refund penuh |

Kasus 5, 24, 30 paling mudah diverifikasi lewat test otomatis (bagian 16).

## 14. Pembayaran Midtrans sandbox

1. Pastikan `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` (awalan `SB-Mid-`) terisi di `.env`. Muat ulang server bila baru diubah.
2. Booking, **Bayar**. Di popup pilih metode:
   - **Kartu:** `4811 1111 1111 1114`, CVV `123`, kedaluwarsa bulan dan tahun mendatang, OTP `112233`.
   - **Virtual account / QRIS / e-wallet:** ikuti simulator https://simulator.sandbox.midtrans.com untuk "membayar".
3. Setelah popup sukses Anda diarahkan kembali ke halaman booking, dan sistem **menyinkronkan status langsung ke Midtrans**, jadi booking menjadi Terkonfirmasi walau webhook belum terpasang.
4. Webhook penuh (untuk pembayaran yang selesai setelah Anda menutup halaman): pakai tunnel publik dan isi Notification URL `https://<domain>/midtrans/notification` di dashboard Midtrans (Settings, Payment).
5. Refund via API Midtrans tersedia di halaman booking admin; di sandbox hanya berhasil untuk transaksi yang benar-benar sudah dibayar lewat metode yang mendukung refund. Bila gagal, gunakan **Tandai sudah direfund manual**.

## 15. Scheduler dan email

| Perintah | Jadwal | Fungsi |
|---|---|---|
| `bookings:expire` | tiap menit | Batalkan booking belum dibayar atau permintaan tanpa respons |
| `bookings:complete` | tiap jam | Tandai booking lunas yang sudah lewat sebagai selesai, kirim permintaan ulasan |
| `bookings:remind` | tiap jam | Pengingat H-7 dan H-1 (sekali per booking) |

`dev.sh` menjalankan `schedule:work` otomatis. Untuk mencoba manual: `php artisan bookings:expire` dst.

Email: dengan `MAIL_MAILER=log`, semua email (pembayaran, persetujuan, batal, refund, pengingat, minta ulasan) tertulis di `storage/logs/laravel.log`, tidak terkirim sungguhan.

## 16. Test otomatis

```bash
php artisan test            # atau: bash scripts/dev.sh test
```

Berisi `BookingScenarioProbeTest` (43 skenario slot, pembayaran, otorisasi), `BookingLifecycleTest` (29 skenario jauh hari, persetujuan, refund, jadwal ulang, ulasan, rating), `PayoutTest` (10 skenario), dan test peran, chat, serta webhook. Semua memakai database memori, tidak menyentuh data demo Anda.

## 17. Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Halaman error `Vite manifest not found` | Frontend belum di-build: `npm run build` |
| Tampilan polos atau tanpa gaya | Sama seperti di atas, atau memakai mode `hmr` tapi server Vite mati |
| HP tidak bisa membuka | Backend hanya di 127.0.0.1 (jalankan lewat `dev.sh`), firewall belum izinkan port 8000, atau beda Wi-Fi |
| Tombol Bayar error 502 atau 500 | Kunci Midtrans kosong atau salah; cek `storage/logs/laravel.log` |
| Status bayar tidak berubah | Webhook tidak terjangkau; kembali ke halaman booking setelah bayar agar tersinkron |
| Booking tidak kedaluwarsa | Scheduler tidak berjalan; jalankan `php artisan schedule:work` |
| Port 8000 dipakai | `bash scripts/dev.sh stop`, atau `PORT=9000 bash scripts/dev.sh` |
| Ingin data demo bersih | `bash scripts/dev.sh reset` (menghapus seluruh data) |
| `Class "Midtrans\Config" not found` | `composer install` belum dijalankan |
| Foto ulasan atau galeri tidak tampil | `php artisan storage:link` |

## 18. Batasan saat ini

- Pencairan dana ke bank **manual** (admin menandai sudah transfer). Tidak ada integrasi transfer bank otomatis.
- Pembayaran Midtrans dan refund API hanya diuji dengan data tiruan di test; uji dengan kunci sandbox Anda sebelum dipakai.
- Belum ada: mode gelap, voucher, PPN otomatis, harga weekend atau high season, perpanjang sewa, QR check-in, invoice PDF, ruangan lintas zona waktu, notifikasi in-app.
- Race dua request bersamaan perlu diuji di MySQL atau PostgreSQL; SQLite memakai lock aplikasi dan transaksi.
- Foto ulasan demo tidak ada (hanya diuji lewat test); foto ruangan demo memakai URL Unsplash sehingga butuh internet.
- Email default hanya ditulis ke log.


## 19. Chatbot, peta, dan SDGs

- **Chatbot:** tombol bulat di kanan bawah semua halaman publik. Anonim bisa bertanya ruangan, harga, jadwal, kebijakan, dan "ruangan terdekat" (tombol Bagikan lokasi saya atau sebut kawasan). Setelah login sebagai pembeli, tanyakan "pesanan saya" atau kode booking. Contoh: `ruang meeting di Jakarta Selatan`, `apakah Executive Boardroom kosong besok?`, `bagaimana refund?`.
- **Ganti AI:** login admin, menu **Chatbot AI**. Pilih Mesin aturan, Gemini (kunci dari https://aistudio.google.com/apikey), atau Ollama (`ollama serve`, `ollama pull llama3.1`). Klik **Tes koneksi** sebelum **Simpan**. Detail dan best practice: `docs/CHATBOT.md`.
- **Peta:** di beranda, bagian **Cari ruangan di peta** (cari kawasan, atau **Dekat saya**). Penjual menetapkan lokasi di form ruangan, bagian **Lokasi di peta**.
- **SDGs:** bagian **Selaras dengan SDGs** di beranda (ketuk kartu untuk target resmi).
- Setelah menarik kode baru: `php artisan migrate` (mengisi koordinat ruangan lama) dan `npm run build`.
