# Chatbot SpaceRent

Asisten bantuan di kanan bawah semua halaman publik. Pengunjung anonim boleh bertanya; pengguna yang sudah login mendapat jawaban tentang data miliknya sendiri. Semua fakta dibaca langsung dari database lewat tool server (read-only).

## Arsitektur

```
Widget (resources/js/chatbot.js)
  GET  /chatbot/config      -> pengaturan publik (tanpa kunci)
  POST /chatbot/message     -> ChatbotController (validasi, batas laju, CSRF)
        |
        v
ChatbotService::reply()
  1. ChatbotSettings        pengaturan bertipe dari tabel settings
  2. Provider terpilih      rules | gemini | ollama
       - rules  : RulesEngine (tanpa AI)
       - gemini : GeminiProvider (REST generateContent, function calling)
       - ollama : OllamaProvider (/api/chat, tool calling)
  3. ChatbotTools           satu-satunya jalan ke database
  4. Gagal / kosong        -> RulesEngine (fallback: true)
  5. ChatbotLog             opsional, tanpa koordinat dan kunci
```

Berkas: `app/Services/Chatbot/*`, `app/Http/Controllers/ChatbotController.php`, `app/Http/Controllers/Admin/ChatbotSettingsController.php`, `resources/chatbot/system-prompt.md`, `config/chatbot.php`.

## Matriks akses (dijaga di server, bukan di prompt)

| Peran | Tool yang tersedia | Data yang boleh dilihat |
|---|---|---|
| Anonim | `search_spaces`, `nearest_spaces`, `space_details`, `check_availability`, `get_info` | Ruangan tersedia, harga, lokasi, jadwal kosong, kebijakan, cara pesan |
| Pembeli | + `my_bookings`, `booking_detail`, `my_favorites` | Pesanan dan favorit miliknya saja (`user_id` = akun) |
| Penjual | + `seller_overview` | Ruangan dan booking pada ruangan miliknya |
| Admin | Hanya tool publik | Chat bukan alat admin |

Kode booking milik orang lain dijawab "tidak ditemukan" (tanpa membedakan). Tool privat tidak dikirim ke LLM untuk peran yang tidak berhak, dan `execute()` memeriksa ulang.

## Katalog tool

| Tool | Fungsi |
|---|---|
| `search_spaces` | Cari kata kunci, kategori, wilayah, kapasitas minimal, harga maksimal |
| `nearest_spaces` | Terdekat dari koordinat (haversine); tanpa lokasi mengembalikan aksi `request_location` |
| `space_details` | Deskripsi, fasilitas, jam buka, kebijakan, biaya, ulasan terbaru |
| `check_availability` | Jam mulai kosong (per jam) atau status tanggal (harian/bulanan) lewat `AvailabilityService` |
| `get_info` | Batal/refund, jadwal ulang, cara pesan, pembayaran, biaya, ulasan, payout, kontak (dari `config/spacerent.php`) |
| `my_bookings`, `booking_detail`, `my_favorites` | Data pembeli sendiri |
| `seller_overview` | Ringkasan penjual |

## Mengganti provider (Admin > Chatbot AI)

**Mesin aturan** bawaan: gratis, cepat, selalu aktif.

**Gemini (Flash gratis):**
1. Buat kunci di https://aistudio.google.com/apikey.
2. Admin > Chatbot AI, pilih Google Gemini, tempel kunci, model `gemini-2.0-flash`, klik Tes koneksi, lalu Simpan.
3. Kunci disimpan terenkripsi (`APP_KEY`), hanya tampil sebagai `AIza******wxyz`, dan tidak dikirim ke browser. Kosongkan kolom untuk mempertahankan, atau centang Hapus kunci.

**Ollama (lokal):**
```bash
ollama serve
ollama pull llama3.1
```
Pilih Ollama, alamat `http://localhost:11434`, model yang mendukung tool (llama3.1, qwen2.5, mistral-nemo). Bila Laravel di server lain, arahkan ke alamat Ollama yang dapat dijangkau.

Bila provider AI gagal (kunci salah, kuota habis, timeout, Ollama mati), jawaban otomatis dari mesin aturan dan respons berisi `fallback: true`.

## Best practice yang diterapkan

1. **Akses di sisi server.** Prompt bisa dibujuk; kueri ber-scope `user_id` tidak.
2. **Read-only.** Tidak ada tool yang menulis data, memesan, atau membayar.
3. **Rahasia.** Kunci terenkripsi, di-mask di UI, dibersihkan dari jawaban, log, dan pesan galat.
4. **Batas laju** per akun/IP (bukan per sesi), batas panjang pesan, riwayat dipangkas, hanya role `user`/`assistant`.
5. **Fallback** ke mesin aturan agar pengunjung tidak pernah melihat galat mentah.
6. **Anti prompt injection.** Teks pengguna, nama ruangan, ulasan adalah data; prompt melarang membocorkan instruksi; mesin aturan menolak pola umum.
7. **Privasi.** Lokasi hanya di sessionStorage peramban dan dikirim per permintaan; tidak disimpan di log.
8. **Observabilitas.** Panel admin menampilkan jumlah percakapan 24 jam, rasio cadangan, latensi, dan 20 log terakhir.
9. **Teruji.** `tests/Feature/ChatbotTest.php` dan `ChatbotSettingsTest.php` memakai `Http::fake` (tanpa jaringan).

## Peta dan "terdekat"

Leaflet + tile OpenStreetMap (sumber terbuka, tanpa API key); alamat dicari lewat Nominatim dari browser. Penjual menetapkan lokasi di form ruangan (klik peta, seret penanda, cari alamat, atau lokasi saya). Pengunjung memakai bagian "Cari ruangan di peta" di beranda: cari kawasan, atau Dekat saya. `GET /peta/ruangan` mengembalikan penanda (`lat`, `lng`, `q`, `category`, `radius_km`, `limit`). Ruangan lama diberi koordinat dari kawasannya oleh migrasi `2026_11_18_000002`; penjual dapat memperbaikinya.

## Pemecahan masalah

| Gejala | Penyebab / solusi |
|---|---|
| Jawaban selalu "mode cadangan" | Kunci Gemini salah/kuota habis atau Ollama mati. Pakai Tes koneksi |
| Gemini dipilih tetapi mesin aturan yang menjawab | Kunci belum diisi (diperlakukan sebagai rules) |
| 429 | Melewati batas pesan per menit; naikkan di pengaturan |
| "Terdekat" meminta lokasi terus | Izin lokasi ditolak; sebutkan kawasan, mis. Kemang |
| Ruangan tidak muncul di peta | Belum punya koordinat atau tidak tersedia |
| Ollama menolak tool | Model tidak mendukung tool; ganti model |
