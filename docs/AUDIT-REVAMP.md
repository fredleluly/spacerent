# SpaceRent — Audit Flow & Rencana Revamp

Hasil telaah kode (routes, controller, service, model, migration, view) per 2026-11-16. Belum ada perubahan kode.

## A. Kesalahan Kritis (bisa merugikan uang / data)

| # | Masalah | Bukti | Dampak |
|---|---|---|---|
| A1 | **Webhook "menghidupkan" booking yang sudah hangus.** `applyNotification` hanya menolak jika sudah `paid/refunded`. Booking `expired/cancelled` yang menerima `settlement` tetap jadi `paid + confirmed` | `MidtransService::applyNotification` | Slot sudah dipakai orang lain → **double booking nyata**, uang masuk tanpa slot |
| A2 | Booking yang dibatalkan/expire **tidak membatalkan transaksi Midtrans** (tidak ada `Transaction::cancel`); Snap token tetap bisa dibayar | `ExpireBookings`, seller/admin `cancel` | Pembeli bayar booking mati (lihat A1) |
| A3 | **Admin bisa ubah status bebas** (`cancelled → confirmed`, `pending → confirmed` tanpa bayar) tanpa cek bentrok | `Admin\BookingController::update` | Double booking, data keuangan tidak konsisten |
| A4 | **Admin bisa menghapus booking lunas**; hapus ruangan menghapus semua booking (`cascadeOnDelete`) | `destroy`, migration bookings | Riwayat transaksi hilang |
| A5 | Cek bentrok hanya di aplikasi. `lockForUpdate` **tidak berefek di SQLite**, tidak ada constraint di DB | `BookingController::store` | Dua request bersamaan bisa lolos |
| A6 | **Pembatalan tidak punya alur uang.** Seller/admin set `cancelled` tapi `payment_status` tetap `paid`; tidak ada nominal refund tercatat, tidak ada refund via API; **pembeli tidak bisa membatalkan sendiri** padahal `RefundPolicy` ada | `Seller\BookingController::cancel`, `RefundPolicy` | Pembeli harus WA admin; penjual bisa batal sepihak tanpa konsekuensi |
| A7 | Seller bisa `complete` booking **sebelum jadwal mulai** | `Seller\BookingController::complete` | Pendapatan/riwayat keliru |

## B. Kenapa "bisa pesan di jam yang sama" (dan terasa manual)

Logika overlap di `AvailabilityService` ada, tapi alur di sekelilingnya membuat pengalaman tetap salah:

1. **Tidak ada pemilih slot.** Jam mulai = `<input type="time">` bebas; ketersediaan hanya teks kuning "Sudah terisi: …" setelah ganti tanggal. User baru tahu bentrok **setelah submit**. Tidak ada grid jam / kalender.
2. **Tidak ada jam operasional.** Booking jam 02:00 atau durasi 12 jam melewati tengah malam diterima. Tabel `spaces` tak punya `open_time/close_time`, hari libur, blackout date.
3. **Jam yang sudah lewat hari ini lolos** (`after_or_equal:today` hanya cek tanggal).
4. Tidak ada **lead time**, **durasi minimum**, **buffer/cleaning time**.
5. Slot pending menahan ruangan 30 menit untuk siapa pun yang klik, tanpa batas booking pending per user → bisa dipakai memblokir ruangan (slot-hoarding).
6. Datetime tanpa zona waktu, label "WIB" hardcoded.
7. Ruangan **harian/bulanan**: hanya teks rentang, tidak ada kalender yang menonaktifkan tanggal.
8. Tombol pesan bertuliskan **"Belum ada pembayaran. Booking dikonfirmasi oleh CS via WhatsApp"** — copy usang, bertentangan dengan alur Midtrans otomatis.
9. Halaman booking masih menampilkan tombol **WhatsApp konfirmasi** padahal pembayaran otomatis.

## C. Fitur yang belum ada / salah konsep

| Area | Kondisi sekarang | Seharusnya (praktik umum marketplace booking) |
|---|---|---|
| **Rating** | Kolom `spaces.rating` angka statis, default **4.8 palsu**, diedit manual admin | Dihitung dari ulasan nyata; hanya pembeli yang booking-nya `completed` |
| **Ulasan/komentar** | Tidak ada tabel | Tabel `reviews` (rating 1–5, komentar, foto opsional, balasan penjual) |
| **Komentar anonim** | Tidak ada | Opsi anonim (identitas tersimpan untuk moderasi, tampil "Pengguna SpaceRent"); satu ulasan per booking |
| **Moderasi** | Tidak ada | Laporkan ulasan, admin sembunyikan |
| Pembatalan pembeli | Tidak ada | Tombol batal + refund sesuai kebijakan (48j/24j) + status `refund_pending → refunded` |
| Reschedule | Tidak ada | Ubah jadwal jika slot baru kosong |
| Persetujuan penjual | Auto-confirm saja | Mode "Instant Book" vs "Ajukan & Disetujui" per ruangan |
| Kalender penjual | Tidak ada | Kalender booking + blokir manual |
| Jam operasional & aturan | Tidak ada | Per ruangan: jam buka, libur, min/max durasi, buffer |
| Harga | `price × duration` | Biaya layanan, harga weekend, diskon durasi (opsional) |
| Bukti | Hanya halaman kode | Invoice PDF + e-voucher QR |
| Notifikasi | Email hanya saat paid | Dibuat, dibayar, reminder H-1, batal, refund, minta ulasan |
| Dana penjual | Semua ke akun platform | Saldo, komisi, payout |
| Galeri | Kolom `gallery` ada, **tidak dipakai di UI** | Galeri + lightbox |
| Status booking | Tanpa timeline | Timeline dibuat → dibayar → dikonfirmasi → selesai → ulasan |
| Test | Ada 51 test, 44 lulus; 7 gagal karena paket `midtrans/midtrans-php` ada di composer.json tapi belum terpasang di `vendor/`. Belum ada test race, state machine, zona waktu | Lihat `docs/STUDY-CASES.md` |

## D. Masalah UI/UX per halaman

- **Beranda**: filter tanpa tanggal/jam (tak bisa cari ruangan kosong), tanpa sort harga/rating, tanpa pagination/peta; kartu minim info.
- **Detail ruangan**: hanya 1 gambar; form 9 field di kartu sempit; nama/email/telepon diminta ulang padahal sudah login; total tanpa rincian; tidak ada ulasan, peta, aturan ruangan, kebijakan batal; CTA tidak sticky di mobile.
- **Booking show**: satu halaman untuk semua status; tanpa timeline; tombol WA usang; tanpa aksi batal/ulas.
- **Pesanan Saya**: daftar polos tanpa aksi (bayar ulang, batal, ulas, chat).
- **Seller**: dashboard 5 angka + tabel; tanpa kalender, grafik, pengaturan jam operasional.
- **Admin**: ubah status via dropdown bebas; tanpa audit log.
- **Teknis**: Tailwind via CDN (tidak untuk produksi), tanpa design system, ikon via unpkg, tanpa skeleton/empty state konsisten.

## E. Rencana Revamp (urut prioritas)

**Fase 1 — Inti & uang**
1. State machine booking terpusat; admin tidak lagi set status bebas.
2. Perbaiki A1/A2: settlement terlambat → jika slot sudah diambil tandai `refund_needed`, bukan confirmed; cancel/expire memanggil `Transaction::cancel`.
3. Pindah ke MySQL/PostgreSQL (atau `Cache::lock`) + guard overlap atomik.
4. Soft delete booking & ruangan; larang hapus yang sudah lunas.
5. Test PHPUnit bentrok, webhook, expire, transisi.

**Fase 2 — Slot booking yang benar**
1. Kolom ruangan: `open_time`, `close_time`, `open_days`, `min_duration`, `max_duration`, `buffer_minutes`, `lead_time_hours`, `instant_book`; tabel `space_blackouts`.
2. Endpoint availability baru → **grid jam / kalender**; slot terisi abu-abu dan tak bisa diklik.
3. Validasi server: jam operasional, bukan masa lalu, buffer, batas pending per user.
4. Filter beranda berdasar tanggal/jam kosong.

**Fase 3 — Siklus pesanan**
Batal oleh pembeli + refund, reschedule, approval penjual, kalender penjual + blokir, email lengkap, invoice/e-voucher.

**Fase 4 — Ulasan & rating**
Tabel `reviews` (rating, komentar, `is_anonymous`, balasan penjual, moderasi); hanya setelah `completed`; rating dihitung otomatis; hapus input rating manual; tab ulasan di detail; laporan + moderasi admin.

**Fase 5 — Revamp UI**
Tailwind via Vite, design system, redesign beranda / detail (galeri, slot picker, rincian harga, ulasan, sticky CTA) / checkout / Pesanan Saya + timeline / dashboard seller / admin + audit log.

**Fase 6 — Dana penjual**
Saldo, komisi, payout, laporan.

## F. Keputusan yang dibutuhkan

1. Mode booking default: **instant book** atau **perlu persetujuan penjual**?
2. Refund 100%/50%/0% (48j/24j) sama untuk semua, atau per ruangan?
3. Database: lanjut SQLite (tidak aman untuk concurrent) atau MySQL/PostgreSQL?
4. Ulasan hanya setelah booking selesai? Anonim tetap menyimpan identitas untuk moderasi?
5. Komisi & payout penjual: masuk scope sekarang atau nanti?
