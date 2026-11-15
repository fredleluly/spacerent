<?php

namespace App\Services\Chatbot;

use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;

/**
 * Mesin aturan tanpa LLM. Selalu tersedia: dipakai saat provider = "rules" dan sebagai cadangan
 * ketika Gemini/Ollama gagal. Semua fakta tetap berasal dari ChatbotTools (database), bukan dari teks template.
 */
class RulesEngine
{
    private const CATEGORY_WORDS = [
        'meeting-room' => ['meeting', 'rapat', 'ruang rapat', 'boardroom'],
        'private-office' => ['private office', 'kantor privat', 'kantor', 'office', 'suite'],
        'coworking' => ['coworking', 'co-working', 'hot desk', 'cowork'],
        'event-space' => ['event', 'acara', 'ballroom', 'pernikahan', 'wedding', 'seminar', 'workshop', 'gala'],
        'studio-podcast' => ['podcast', 'studio', 'rekaman'],
        'virtual-office' => ['virtual office', 'alamat usaha', 'alamat bisnis'],
    ];

    private const AREA_ALIASES = [
        'jaksel' => 'Jakarta Selatan', 'jakpus' => 'Jakarta Pusat', 'jakbar' => 'Jakarta Barat',
        'jakut' => 'Jakarta Utara', 'jaktim' => 'Jakarta Timur',
    ];

    private const DAYS_ID = ['minggu' => 0, 'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, "jum'at" => 5, 'sabtu' => 6];

    private const MONTHS_ID = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];

    public function answer(string $message, ChatbotTools $tools, ?User $user): string
    {
        $raw = trim($message);
        $t = ' '.mb_strtolower(preg_replace('/\s+/u', ' ', $raw)).' ';

        if ($this->isInjection($t)) {
            return 'Maaf, saya tidak bisa mengubah aturan saya atau membuka instruksi internal, kunci, maupun data pengguna lain. Saya tetap senang membantu mencari ruangan, cek jadwal, dan menjelaskan kebijakan SpaceRent.';
        }
        if ($this->asksOtherPeople($t)) {
            return 'Maaf, saya hanya dapat menampilkan data akun Anda sendiri. Informasi pesanan atau data pribadi orang lain tidak dapat saya buka.';
        }

        if (preg_match('/\bSR-[A-Z0-9]{4,8}\b/i', $raw, $m)) {
            return $this->bookingByCode($m[0], $tools, $user);
        }
        if ($this->asksPrivate($t)) {
            return $this->privateAnswer($t, $tools, $user);
        }
        if (preg_match('/(terdekat|dekat (saya|sini|aku|lokasi)|sekitar (saya|sini)|nearby|paling dekat|dekat dari)/u', $t)) {
            return $this->nearest($t, $tools);
        }
        if (preg_match('/(kosong|tersedia|ketersediaan|available|jadwal|bisa (dipesan|disewa|dibooking)|masih ada)/u', $t) && ($date = $this->parseDate($t))) {
            return $this->availability($t, $date, $tools);
        }
        if ($topic = $this->infoTopic($t)) {
            $r = $tools->execute('get_info', ['topic' => $topic]);

            return (string) ($r['info'] ?? 'Silakan hubungi tim kami untuk informasi ini.');
        }
        if ($space = $this->mentionedSpace($t)) {
            return $this->details($space, $tools);
        }
        if ($this->looksLikeSearch($t)) {
            return $this->search($t, $tools);
        }
        if (preg_match('/\b(halo|hai|hi|hello|selamat (pagi|siang|sore|malam)|assalamualaikum|permisi)\b/u', $t)) {
            return 'Halo! Saya asisten SpaceRent. Saya bisa mencarikan ruangan (mis. "ruang meeting untuk 10 orang di Kemang"), mencari yang terdekat dari lokasi Anda, mengecek jadwal kosong, dan menjelaskan kebijakan batal atau cara memesan.';
        }
        if (preg_match('/(terima kasih|makasih|thanks|thank you|trims)/u', $t)) {
            return 'Sama-sama! Kabari saya kalau ada yang ingin dicari lagi.';
        }

        $tools->addHandoffAction();

        return 'Maaf, saya belum bisa membantu hal itu. Saya fokus pada SpaceRent: mencari ruangan, harga, jadwal, lokasi terdekat, dan kebijakan. Tim kami siap membantu lewat WhatsApp atau halaman Hubungi Kami.';
    }

    // ---------------------------------------------------------------- niat

    private function isInjection(string $t): bool
    {
        return (bool) preg_match('/((abaikan|lupakan|ignore|disregard|forget).{0,40}(instruksi|aturan|perintah|prompt|instruction|rules|sebelumnya|previous))|system prompt|prompt (sistem|kamu|anda|mu)\b|instruksi (internal|sistem|rahasia)|api ?key|kunci api|bocorkan|reveal your|jailbreak|developer mode|pura-pura jadi|berperan sebagai admin/u', $t);
    }

    private function asksOtherPeople(string $t): bool
    {
        if (preg_match('/(orang lain|pengguna lain|user lain|akun lain|milik (teman|dia|si |bapak|ibu|pak |bu )|punya (teman|dia|si ))/u', $t)
            && preg_match('/(pesanan|booking|riwayat|data|email|nomor|telepon|transaksi|order|alamat)/u', $t)) {
            return true;
        }

        return (bool) preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $t) && preg_match('/(pesanan|booking|riwayat|transaksi|order)/u', $t);
    }

    private function asksPrivate(string $t): bool
    {
        return (bool) preg_match('/(riwayat|pesanan (saya|ku)|pesananku|booking (saya|ku)|bookingku|order (saya|ku)|transaksi (saya|ku)|status (pesanan|booking|pembayaran)|cek (pesanan|booking)|favorit (saya|ku)|ruangan (saya|ku) |refund (saya|ku)|tagihan (saya|ku)|akun saya|pembayaran (saya|ku))/u', $t);
    }

    private function infoTopic(string $t): ?string
    {
        return match (true) {
            (bool) preg_match('/(jadwal ulang|reschedule|ubah jadwal|ganti jadwal|pindah jadwal)/u', $t) => 'reschedule',
            (bool) preg_match('/(batal|refund|pengembalian dana|kembalikan uang|pembatalan|cancel)/u', $t) => 'cancellation',
            (bool) preg_match('/(cara (memesan|pesan|booking|menyewa|sewa)|bagaimana (memesan|booking|menyewa)|gimana (cara )?(pesan|booking|sewa))/u', $t) => 'how_to_book',
            (bool) preg_match('/(bayar|pembayaran|midtrans|qris|virtual account|gopay|metode)/u', $t) => 'payment',
            (bool) preg_match('/(biaya layanan|service fee|biaya kebersihan|biaya tambahan|admin fee|rincian harga)/u', $t) => 'fees',
            (bool) preg_match('/(ulasan|review|rating)/u', $t) && ! preg_match('/(terbaik|tertinggi|bagus)/u', $t) => 'review',
            (bool) preg_match('/(penarikan|tarik dana|payout|komisi|pendapatan penjual|jadi mitra|daftarkan ruangan)/u', $t) => 'seller_payout',
            (bool) preg_match('/(kontak|hubungi|whatsapp|\bwa\b|telepon|email|cs\b|customer service|bicara dengan (manusia|orang|admin))/u', $t) => 'contact',
            default => null,
        };
    }

    private function looksLikeSearch(string $t): bool
    {
        if ($this->category($t) || $this->area($t) || $this->district($t) || $this->capacity($t) !== null || $this->budget($t) !== null) {
            return true;
        }

        return (bool) preg_match('/(ruang(an)?|cari|rekomendasi|saran|harga|murah|sewa|tempat|lokasi|kapasitas|booking|pesan)/u', $t);
    }

    // ---------------------------------------------------------------- jawaban

    private function bookingByCode(string $code, ChatbotTools $tools, ?User $user): string
    {
        if (! $user) {
            $tools->addLoginAction();

            return 'Detail pesanan hanya tersedia setelah Anda masuk ke akun. Silakan masuk, lalu tanyakan lagi kode booking Anda.';
        }
        $r = $tools->execute('booking_detail', ['code' => $code]);
        if (($r['error'] ?? null) === 'not_allowed') {
            return 'Akun ini tidak memiliki daftar pesanan sebagai pembeli.';
        }
        if (! ($r['found'] ?? false)) {
            return 'Pesanan dengan kode itu tidak ditemukan di akun Anda. Periksa kembali kodenya, atau lihat daftar di halaman Pesanan Saya.';
        }

        $lines = ["**{$r['code']}** di **{$r['space']}**", "- Jadwal: {$r['start']} ({$r['duration']})", "- Status: {$r['status']}, pembayaran {$r['payment']}", "- Total: {$r['total']}"];
        if (! empty($r['refund_amount'])) {
            $lines[] = "- Refund: {$r['refund_amount']} ({$r['refund_status']})";
        }
        if (isset($r['refund_percent_if_cancelled_now'])) {
            $lines[] = "- Bila dibatalkan sekarang, refund {$r['refund_percent_if_cancelled_now']}% (kebijakan {$r['cancellation_policy']})";
        }
        $lines[] = 'Kelola pesanan di halaman booking: '.$r['booking_url'];

        return implode("\n", $lines);
    }

    private function privateAnswer(string $t, ChatbotTools $tools, ?User $user): string
    {
        if (! $user) {
            $tools->addLoginAction();

            return 'Riwayat dan pesanan hanya dapat dilihat setelah Anda masuk ke akun. Anda tetap bisa bertanya soal ruangan, harga, jadwal, dan kebijakan tanpa masuk.';
        }
        if ($user->isSeller()) {
            return $this->sellerSummary($tools);
        }
        if (! $user->isBuyer()) {
            return 'Akun admin dikelola lewat panel admin. Lewat chat ini saya hanya menampilkan informasi publik.';
        }

        if (str_contains($t, 'favorit')) {
            $r = $tools->execute('my_favorites');
            if (empty($r['spaces'])) {
                return 'Anda belum menyimpan ruangan favorit. Tekan ikon hati di kartu ruangan untuk menyimpannya.';
            }

            return "Ruangan favorit Anda:\n".implode("\n", array_map(fn ($s) => $this->spaceLine($s), $r['spaces']));
        }

        $r = $tools->execute('my_bookings', ['limit' => 5]);
        if (empty($r['bookings'])) {
            return 'Anda belum memiliki pesanan. Cari ruangan dan pesan langsung dari halaman ruangan.';
        }
        $lines = array_map(fn ($b) => "- **{$b['code']}** · {$b['space']} · {$b['start']} · {$b['status']} ({$b['payment']}) · {$b['total']}", $r['bookings']);

        return "Pesanan terbaru Anda:\n".implode("\n", $lines)."\nSebutkan kode booking (mis. {$r['bookings'][0]['code']}) untuk melihat rinciannya.";
    }

    private function sellerSummary(ChatbotTools $tools): string
    {
        $r = $tools->execute('seller_overview');
        if (empty($r['spaces'])) {
            return 'Anda belum memiliki ruangan. Tambahkan ruangan dari panel penjual.';
        }
        $out = ['Ruangan Anda: '.implode(', ', array_column($r['spaces'], 'name')).'.'];
        if ($r['bookings_by_status']) {
            $out[] = 'Booking: '.implode(', ', array_map(fn ($n, $s) => "{$s} {$n}", $r['bookings_by_status'], array_keys($r['bookings_by_status']))).'.';
        }
        foreach ($r['upcoming'] as $b) {
            $out[] = "- {$b['code']} · {$b['space']} · {$b['start']} · {$b['status']}";
        }

        return implode("\n", $out);
    }

    private function nearest(string $t, ChatbotTools $tools): string
    {
        // Tanpa koordinat tetapi pengguna menyebut kawasan: cari berdasarkan kawasan itu.
        if (! $tools->hasLocation() && ($this->district($t) || $this->area($t))) {
            return $this->search($t, $tools);
        }

        $args = $this->filters($t);
        $r = $tools->execute('nearest_spaces', $args);

        if ($r['needs_location'] ?? false) {
            return 'Untuk mencari yang terdekat, saya perlu lokasi Anda. Tekan tombol "Bagikan lokasi saya" di bawah, atau sebutkan kawasannya (mis. Kemang, Kuningan, atau Jakarta Selatan).';
        }
        if (empty($r['spaces'])) {
            return 'Belum ada ruangan berkoordinat yang cocok di sekitar Anda. Coba perluas filter atau sebutkan kawasan tertentu.';
        }

        return "Ruangan terdekat dari lokasi Anda:\n".implode("\n", array_map(fn ($s) => $this->spaceLine($s), $r['spaces']))."\nMau saya cek jadwal kosongnya?";
    }

    private function search(string $t, ChatbotTools $tools): string
    {
        $args = $this->filters($t);
        if ($d = $this->district($t)) {
            $args['query'] = $d;
        }
        $r = $tools->execute('search_spaces', $args);

        if (empty($r['spaces'])) {
            return 'Belum ada ruangan yang cocok dengan kriteria itu. Coba longgarkan filter (kapasitas, harga, atau kawasan), atau minta saya mencari yang terdekat dari lokasi Anda.';
        }

        $head = $r['count'] === 1 ? 'Saya menemukan 1 ruangan:' : "Saya menemukan {$r['count']} ruangan:";

        return $head."\n".implode("\n", array_map(fn ($s) => $this->spaceLine($s), $r['spaces']))."\nMau saya cek jadwal kosong atau yang terdekat dari lokasi Anda?";
    }

    private function details(Space $space, ChatbotTools $tools): string
    {
        $r = $tools->execute('space_details', ['slug' => $space->slug]);
        if (! ($r['found'] ?? false)) {
            return 'Ruangan itu tidak ditemukan.';
        }
        $out = ["**{$r['name']}** ({$r['location']})", "- {$r['category']}, kapasitas {$r['capacity']} orang", "- Harga {$r['price']}", "- Buka {$r['open_hours']} ({$this->dayList($r['open_days'])})"];
        if ($r['rating']) {
            $out[] = "- Rating {$r['rating']} dari {$r['rating_count']} ulasan";
        }
        if ($r['facilities']) {
            $out[] = '- Fasilitas: '.implode(', ', array_slice($r['facilities'], 0, 8));
        }
        $out[] = "- Kebijakan batal {$r['cancellation_policy']}";
        $out[] = $r['instant_book'] ? '- Instant Book: langsung terkonfirmasi setelah bayar' : '- Perlu persetujuan pemilik sebelum bayar';

        return implode("\n", $out)."\nMau saya cek jadwal kosong di tanggal tertentu?";
    }

    private function availability(string $t, Carbon $date, ChatbotTools $tools): string
    {
        $space = $this->mentionedSpace($t);
        if (! $space) {
            return 'Ruangan yang mana yang ingin dicek? Sebutkan namanya, atau minta saya mencarikan dulu (mis. "ruang meeting 10 orang di Kemang").';
        }
        $duration = 1;
        if (preg_match('/(\d{1,2})\s*(jam|hari|bulan)/u', $t, $m)) {
            $duration = max(1, (int) $m[1]);
        }
        $r = $tools->execute('check_availability', ['slug' => $space->slug, 'date' => $date->toDateString(), 'duration' => $duration]);
        if (isset($r['error'])) {
            return 'Tanggalnya belum bisa saya pahami. Tulis seperti "besok", "jumat", atau "25 desember".';
        }
        $when = $date->copy()->locale('id')->translatedFormat('l, d F Y');

        if (! $r['bookable']) {
            return "Maaf, **{$r['space']}** belum bisa dipesan pada {$when}: ".($r['reason'] ?? 'tidak ada slot kosong').' Mau coba tanggal lain?';
        }
        if (isset($r['available_start_times'])) {
            $times = $r['available_start_times'];
            $shown = implode(', ', array_slice($times, 0, 10)).(count($times) > 10 ? ', dan seterusnya' : '');

            return "**{$r['space']}** kosong pada {$when} untuk {$r['duration']}. Jam mulai tersedia: {$shown}. Pemesanan final dilakukan di halaman ruangan.";
        }

        return "**{$r['space']}** tersedia pada {$when} untuk {$r['duration']}. Pemesanan final dilakukan di halaman ruangan.";
    }

    // ---------------------------------------------------------------- penguraian

    /** @return array<string, mixed> */
    private function filters(string $t): array
    {
        return array_filter([
            'category' => $this->category($t),
            'area' => $this->area($t),
            'min_capacity' => $this->capacity($t),
            'max_price' => $this->budget($t),
        ], fn ($v) => $v !== null);
    }

    private function category(string $t): ?string
    {
        foreach (self::CATEGORY_WORDS as $slug => $words) {
            foreach ($words as $w) {
                if (str_contains($t, $w)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    private function area(string $t): ?string
    {
        foreach (self::AREA_ALIASES as $alias => $area) {
            if (preg_match('/\b'.$alias.'\b/u', $t)) {
                return $area;
            }
        }
        foreach (Space::AREAS as $area) {
            if (str_contains($t, mb_strtolower($area))) {
                return $area;
            }
        }

        return null;
    }

    private function district(string $t): ?string
    {
        foreach (Space::query()->where('is_available', true)->distinct()->pluck('district') as $d) {
            if ($d !== '' && str_contains($t, mb_strtolower($d))) {
                return $d;
            }
        }

        return null;
    }

    private function capacity(string $t): ?int
    {
        return preg_match('/(\d{1,4})\s*(orang|org|pax|peserta|kursi|tamu|people)/u', $t, $m) ? max(1, (int) $m[1]) : null;
    }

    private function budget(string $t): ?int
    {
        if (! preg_match('/(?:di ?bawah|dibawah|maks(?:imal|imum)?\.?|kurang dari|budget|hingga|sampai|<)\s*(?:rp\.?\s*)?(\d[\d.,]*)\s*(rb|ribu|k|jt|juta)?/u', $t, $m)) {
            return null;
        }
        $num = (float) str_replace(',', '.', str_replace('.', '', $m[1]));
        $unit = $m[2] ?? '';
        $mult = match (true) {
            in_array($unit, ['rb', 'ribu', 'k'], true) => 1000,
            in_array($unit, ['jt', 'juta'], true) => 1000000,
            default => $num < 10000 ? 1000 : 1,
        };

        return (int) round($num * $mult);
    }

    private function parseDate(string $t): ?Carbon
    {
        $today = now()->startOfDay();
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }
        if (str_contains($t, 'hari ini')) {
            return $today;
        }
        if (preg_match('/\blusa\b/u', $t)) {
            return $today->copy()->addDays(2);
        }
        if (preg_match('/\b(besok|esok)\b/u', $t)) {
            return $today->copy()->addDay();
        }
        foreach (self::MONTHS_ID as $name => $num) {
            if (preg_match('/\b(\d{1,2})\s*'.$name.'(?:\s+(\d{4}))?/u', $t, $m)) {
                $year = isset($m[2]) ? (int) $m[2] : (int) $today->year;
                if (! checkdate($num, (int) $m[1], $year)) {
                    return null;
                }
                $d = Carbon::create($year, $num, (int) $m[1])->startOfDay();

                return ($d->lt($today) && ! isset($m[2])) ? $d->addYear() : $d;
            }
        }
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?\b/', $t, $m) && checkdate((int) $m[2], (int) $m[1], isset($m[3]) ? (int) ($m[3] < 100 ? 2000 + $m[3] : $m[3]) : (int) $today->year)) {
            $year = isset($m[3]) ? (int) ($m[3] < 100 ? 2000 + $m[3] : $m[3]) : (int) $today->year;
            $d = Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();

            return ($d->lt($today) && ! isset($m[3])) ? $d->addYear() : $d;
        }
        foreach (self::DAYS_ID as $name => $dow) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/u', $t)) {
                $d = $today->copy();
                do {
                    $d->addDay();
                } while ($d->dayOfWeek !== $dow);

                return $d;
            }
        }

        return null;
    }

    private function mentionedSpace(string $t): ?Space
    {
        $best = null;
        foreach (Space::query()->where('is_available', true)->get(['id', 'name', 'slug', 'price_unit']) as $s) {
            $name = mb_strtolower($s->name);
            if (str_contains($t, $name) || str_contains($t, str_replace('-', ' ', $s->slug))) {
                if (! $best || mb_strlen($s->name) > mb_strlen($best->name)) {
                    $best = $s;
                }
            }
        }

        return $best;
    }

    // ---------------------------------------------------------------- format

    private function spaceLine(array $s): string
    {
        $parts = [$s['location'] ?? '', $s['price'], $s['capacity'].' orang'];
        if (! empty($s['rating'])) {
            $parts[] = '★ '.number_format($s['rating'], 1, ',', '');
        }
        if (isset($s['distance_km'])) {
            $parts[] = number_format($s['distance_km'], 1, ',', '').' km';
        }

        return "- **{$s['name']}** · ".implode(' · ', array_filter($parts));
    }

    /** @param list<string> $days */
    private function dayList(array $days): string
    {
        return count($days) === 7 ? 'setiap hari' : implode(', ', $days);
    }
}
