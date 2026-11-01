# SpaceRent — Rencana MVP & Alur Bisnis

Status: **DRAFT untuk disetujui** · Target: MVP yang bisa dipakai end-to-end, termasuk pembayaran Midtrans (Sandbox).

## 1. Kondisi Saat Ini (hasil audit kode)

| Sudah ada | Belum ada (gap menuju MVP) |
|---|---|
| Katalog ruangan + filter + detail | **Cek ketersediaan / anti double-booking** (sekarang 2 orang bisa booking slot yang sama) |
| Form booking + kode `SR-XXXXXX` + estimasi total | **Pembayaran** (sekarang konfirmasi manual via WhatsApp) |
| Status booking: `pending/confirmed/completed/cancelled` (diubah manual admin) | Status pembayaran terpisah, auto-expire, auto-confirm |
| Admin: dashboard, CRUD ruangan, booking, pesan | Email notifikasi, refund/pembatalan, laporan pendapatan, **chat customer ↔ pemilik** (sekarang hanya form kontak satu arah + WhatsApp) |
| Halaman statis: S&K, FAQ, kontak | Pelacakan booking oleh customer (cek via kode + email) |

Stack: Laravel 13, Blade, Tailwind CDN, SQLite. Tabel: `spaces`, `bookings`, `contact_messages`, `users` (admin).

## 2. Keputusan Produk (asumsi — mohon dikoreksi bila salah)

1. **Guest checkout**, tanpa akun customer. Identitas = kode booking + email. (Paling simpel untuk MVP.)
2. **Bayar penuh di muka** via Midtrans Snap (popup/redirect). Tanpa DP/cicilan.
3. **Pembayaran otomatis = konfirmasi otomatis.** Admin tidak perlu approve manual; admin hanya memantau, membatalkan, atau menandai selesai.
4. **Slot ditahan 30 menit** saat menunggu pembayaran, lalu otomatis dilepas.
5. Satuan sewa mengikuti `price_unit` yang ada: **per jam** (butuh tanggal + jam mulai + durasi) atau **per hari/bulan** (tanggal mulai + durasi).
6. **Refund MVP: manual** — admin batalkan booking, refund dilakukan lewat dashboard Midtrans. Kebijakan dicantumkan di S&K.
7. Satu ruangan = satu unit (tidak ada stok multi-unit). Coworking "desk" diperlakukan sama; kuota desk di luar MVP.

## 3. Alur Bisnis

### 3.1 Alur utama customer

```
Cari ruangan → Detail → Isi form booking
      │
      ▼
 Validasi + CEK KETERSEDIAAN slot  ──(bentrok)──► pesan "slot sudah terisi"
      │ (aman)
      ▼
 Buat booking: status=pending, payment_status=unpaid, expires_at = now+30m
      │
      ▼
 Halaman booking → tombol "Bayar Sekarang" → Midtrans Snap token
      │
      ▼
 Customer bayar (VA / QRIS / e-wallet / kartu)
      │
      ▼
 Midtrans kirim NOTIFICATION (webhook) ──► server verifikasi signature
      │
      ├─ settlement/capture ─► payment_status=paid, status=confirmed, kirim email
      ├─ pending            ─► tetap menunggu
      ├─ expire/cancel/deny ─► payment_status=failed/expired, status=cancelled, slot lepas
      └─ (tidak bayar 30m)  ─► scheduler: status=cancelled, slot lepas
```

### 3.2 Siklus status booking

```
pending ──bayar sukses──► confirmed ──hari sewa lewat──► completed
   │                          │
   └─expired/gagal/batal──► cancelled ◄──admin batalkan (+refund manual)
```

- `status` = siklus sewa (`pending, confirmed, completed, cancelled`) — sudah ada, dipertahankan.
- `payment_status` = siklus uang (`unpaid, paid, failed, expired, refunded`) — **baru**.
- Slot dianggap **terpakai** bila `status in (confirmed)` ATAU `status = pending AND expires_at > now()`.

### 3.3 Aturan ketersediaan (anti double-booking)

- **Per jam**: bentrok bila `date` sama dan rentang `[start_time, start_time+duration)` beririsan dengan booking aktif pada ruangan yang sama.
- **Per hari/bulan**: bentrok bila rentang `[date, date+duration)` beririsan.
- Cek dilakukan **di dalam DB transaction + `lockForUpdate`** pada baris ruangan agar tidak race condition.
- UI: setelah pilih tanggal, tampilkan slot/tanggal yang sudah terisi (endpoint JSON kecil).

### 3.4 Alur admin

1. Login → Dashboard: tambah metrik **pendapatan (paid)**, booking menunggu bayar, booking hari ini.
2. Booking: filter by `status` & `payment_status`; lihat detail transaksi Midtrans (order_id, metode bayar, waktu bayar).
3. Aksi: **Batalkan** (set cancelled + tandai perlu refund), **Tandai selesai**, tandai **refunded** setelah refund manual di Midtrans.
4. Pesan masuk & CRUD ruangan: tetap seperti sekarang.

### 3.5 Aturan pembatalan & refund (usulan untuk S&K)

| Waktu pembatalan | Refund |
|---|---|
| ≥ 48 jam sebelum sewa | 100% |
| 24–48 jam | 50% |
| < 24 jam | 0% |

MVP: customer meminta batal via WhatsApp/kontak → admin proses. Self-service cancel = fase berikutnya.

### 3.6 Chat customer ↔ pemilik

Tujuan: customer bisa bertanya ke pemilik (ketersediaan, fasilitas, nego waktu) tanpa pindah ke WhatsApp, dan percakapan tercatat di sistem.

**Asumsi MVP:** "pemilik" = tim admin SpaceRent (satu inbox bersama). Belum ada akun pemilik per ruangan / multi-tenant. Customer tetap guest (tanpa login).

**Dua titik masuk:**
1. **Tanya dulu (pra-booking)** — tombol "Chat Pemilik" di halaman detail ruangan. Customer isi nama + email, lalu mulai percakapan yang terkait ruangan tsb.
2. **Setelah booking** — tombol "Chat" di halaman booking `/booking/{kode}`; percakapan otomatis terkait booking itu (nama/email terisi).

```
Customer klik "Chat Pemilik"
      │
      ▼
 Buat/ambil percakapan (space_id, booking_id?, email) → token akses rahasia (disimpan di cookie + link di email)
      │
      ▼
 Kirim pesan ──► tersimpan, unread_admin++ ──► (opsional) email notif ke admin
      │
      ▼
 Admin buka Inbox Chat → balas ──► tersimpan, unread_customer++ ──► email notif ke customer (hanya jika offline)
      │
      ▼
 Kedua sisi melihat pesan baru via polling tiap ±5 detik (tanpa websocket)
      │
      ▼
 Admin tutup percakapan (closed) / customer kirim pesan lagi → otomatis dibuka kembali
```

**Aturan:**
- Akses customer = **token acak panjang** (URL `/chat/{token}`), bukan tebakan kode; tanpa token tidak bisa baca.
- Hanya teks, maks 1000 karakter/pesan; throttle (mis. 20 pesan/menit); sanitasi output (escape Blade).
- Admin melihat konteks: ruangan, booking & status bayar terkait, tombol cepat "buka booking".
- Jam balas ditampilkan ("Biasanya membalas dalam 1 jam") — teks statis dari config.
- Fallback WhatsApp tetap ada sebagai tombol sekunder.

## 4. Desain Teknis

### 4.1 Perubahan database (1 migration baru pada `bookings`)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `payment_status` | string, default `unpaid` | `unpaid/paid/failed/expired/refunded` |
| `expires_at` | timestamp nullable | batas bayar (now+30m) |
| `order_id` | string unique nullable | dikirim ke Midtrans (`SR-XXXXXX-<attempt>` agar bisa retry) |
| `snap_token` | string nullable | cache token Snap |
| `payment_type` | string nullable | mis. `bank_transfer`, `qris`, `gopay` |
| `paid_at` | timestamp nullable | |
| `midtrans_payload` | json nullable | raw notification terakhir (audit) |
| `end_at`/`starts_at` | datetime | hasil normalisasi rentang waktu untuk query bentrok yang sederhana & terindeks |

Index: `(space_id, starts_at, end_at)`.

### 4.2 Komponen kode baru

```
config/midtrans.php                      server_key, client_key, is_production=false
app/Services/MidtransService.php         createSnapToken(), verifySignature(), mapStatus()
app/Services/AvailabilityService.php     isAvailable(space, start, end), takenSlots()
app/Http/Controllers/PaymentController.php
    POST /booking/{booking}/pay          → buat/ambil snap token (JSON)
    GET  /booking/{booking}/finish       → halaman hasil (redirect dari Snap)
app/Http/Controllers/MidtransWebhookController.php
    POST /midtrans/notification          → public, CSRF-exempt, verifikasi signature
app/Console/Commands/ExpireBookings.php  → jadwal tiap menit: cancel pending yg lewat expires_at
app/Mail/BookingPaid.php (+ Pending)     → email konfirmasi (log driver saat dev)
```

Package: `midtrans/midtrans-php` (resmi). Env baru: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION=false`.

### 4.3 Poin keamanan pembayaran (wajib)

- **Jangan percaya redirect browser.** Satu-satunya sumber kebenaran status bayar = webhook (+ opsional re-check via `Transaction::status()`).
- Verifikasi `signature_key = sha512(order_id + status_code + gross_amount + ServerKey)`.
- Cocokkan `gross_amount` dengan `bookings.total` sebelum menandai paid.
- Webhook **idempoten** (notifikasi bisa datang berulang/tidak berurutan; jangan turunkan `paid` → `pending`).
- Total dihitung **server-side** (sudah begitu); server key hanya di `.env`.
- Webhook dikecualikan dari CSRF & throttle ketat; endpoint booking tetap throttle.

### 4.4 Testing sandbox

- Daftar akun Midtrans Sandbox → ambil Server/Client Key (prefix `SB-Mid-...`).
- Webhook butuh URL publik → pakai tunnel (project sudah punya trust-proxies untuk HTTPS tunnel) dan set **Payment Notification URL** di dashboard Sandbox ke `https://<tunnel>/midtrans/notification`.
- Simulasi bayar via Midtrans Payment Simulator (VA/QRIS/GoPay) + kartu tes `4811 1111 1111 1114`.
- Test otomatis (PHPUnit): webhook signature valid/invalid, idempotensi, amount mismatch, bentrok slot, auto-expire.

### 4.5 Desain teknis chat

Tabel baru:

| Tabel | Kolom |
|---|---|
| `conversations` | `id`, `token` (unique, 40 char), `space_id` nullable, `booking_id` nullable, `name`, `email`, `status` (`open/closed`), `unread_admin`, `unread_customer`, `last_message_at`, timestamps |
| `messages` | `id`, `conversation_id`, `sender` (`customer/admin`), `user_id` nullable (admin pengirim), `body` text, `read_at` nullable, timestamps. Index `(conversation_id, id)` |

(Tabel `contact_messages` yang ada tetap untuk form Hubungi Kami; tidak digabung.)

Kode baru:

```
app/Models/Conversation.php, ChatMessage.php
app/Http/Controllers/ChatController.php          customer (publik, akses via token)
    POST /ruangan/{space}/chat      → mulai percakapan (pra-booking)
    POST /booking/{booking}/chat    → mulai percakapan dari booking
    GET  /chat/{token}              → halaman chat
    GET  /chat/{token}/messages?after={id}   → JSON pesan baru (polling)
    POST /chat/{token}/messages     → kirim pesan
app/Http/Controllers/Admin/ChatController.php    admin (auth)
    GET  /admin/chats               → inbox (filter: belum dibaca / open / closed)
    GET  /admin/chats/{conversation}, POST balas, PATCH tutup/buka
app/Mail/ChatReplied.php, NewChatMessage.php     notifikasi email (di-queue)
```

Keputusan teknis:
- **MVP pakai polling** (JS `fetch` tiap 5 dtk, hanya ambil `id > after`): tanpa infrastruktur tambahan, jalan di `php artisan serve`.
- Upgrade jalur: **Laravel Reverb** (websocket resmi) + Echo bila butuh realtime sejati — skema DB tidak berubah.
- Badge unread di menu admin & dashboard.
- Test: akses tanpa token ditolak, token salah 404, throttle, unread counter, polling hanya pesan baru.

## 5. Tahapan Pengerjaan

| Fase | Isi | Hasil yang bisa didemokan |
|---|---|---|
| **1. Fondasi booking** | Migration baru, `AvailabilityService`, anti-bentrok di `store`, `expires_at`, command expire + scheduler, test | Dua orang tak bisa booking slot sama; pending otomatis hangus |
| **2. Midtrans Sandbox** | Install SDK, config, `MidtransService`, tombol Bayar + Snap.js di halaman booking, halaman finish | Customer bisa membuka popup bayar & bayar di sandbox |
| **3. Webhook & status** | Endpoint notifikasi, verifikasi signature, mapping status, idempoten, test | Bayar di simulator → booking otomatis `confirmed/paid` |
| **4. Admin** | Kolom/filter payment_status, detail transaksi, metrik pendapatan, aksi batal/refunded/selesai | Admin memantau uang & status |
| **5. Chat** | Migration `conversations`+`messages`, halaman chat customer (polling), inbox & balas admin, badge unread, email notif, throttle, test | Customer tanya pemilik dari detail ruangan / booking; admin membalas dari panel |
| **6. Polesan MVP** | Email konfirmasi, halaman "Cek Booking" (kode+email), update S&K/FAQ (kebijakan bayar & refund), auto-`completed` | Alur lengkap end-to-end |

Estimasi: fase 1–3 = inti MVP (booking + bayar); fase 5 (chat) berdiri sendiri dan bisa dikerjakan paralel/sebelum fase 4; 4 & 6 = penyempurnaan.

## 6. Di Luar Scope MVP (backlog)

Chat realtime websocket (Reverb), lampiran file/gambar di chat, akun pemilik per ruangan (multi-owner), akun customer & login, DP/cicilan, kode promo/voucher, refund otomatis via API, self-service cancel/reschedule, multi-unit/kuota desk, review & rating dari customer, invoice PDF, multi-admin/role, pajak/biaya layanan, deployment production.

## 7. Pertanyaan Terbuka

1. Setuju **bayar penuh + konfirmasi otomatis**, atau mau admin tetap approve dulu?
2. Durasi tahan slot **30 menit** cukup? (Midtrans bisa diatur, mis. 60 menit.)
3. Kebijakan refund 48j/24j di 3.5 oke, atau ada kebijakan lain?
4. Notifikasi email cukup, atau juga WhatsApp otomatis (butuh layanan pihak ketiga)?
5. Sudah punya akun Midtrans Sandbox? (Server/Client Key dibutuhkan di fase 2.)
6. Chat: "pemilik" cukup **admin SpaceRent (satu inbox)**, atau tiap ruangan punya pemilik sendiri dengan login terpisah (jauh lebih besar scope-nya)?
7. Chat polling 5 detik cukup untuk MVP, atau harus realtime (Reverb)?
