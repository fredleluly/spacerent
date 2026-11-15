<?php

namespace App\Services\Chatbot;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Space;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Support\RefundPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tool read-only yang boleh dipanggil chatbot (oleh LLM maupun mesin aturan).
 *
 * Akses dibatasi DI SINI (server), bukan di prompt:
 * - tool publik: tersedia untuk semua orang
 * - tool pembeli (my_bookings, booking_detail, my_favorites): hanya pengguna login berperan buyer, selalu di-scope ke user itu
 * - tool penjual (seller_overview): hanya penjual, hanya ruangan miliknya
 */
class ChatbotTools
{
    public const PUBLIC_TOOLS = ['search_spaces', 'nearest_spaces', 'space_details', 'check_availability', 'get_info'];

    public const BUYER_TOOLS = ['my_bookings', 'booking_detail', 'my_favorites'];

    public const SELLER_TOOLS = ['seller_overview'];

    /** @var array<string, array> kartu ruangan terkumpul selama satu giliran, kunci = slug */
    private array $spaces = [];

    /** @var array<string, array> */
    private array $actions = [];

    /** @var list<string> */
    private array $used = [];

    public function __construct(
        private readonly ?User $user = null,
        private readonly ?float $lat = null,
        private readonly ?float $lng = null,
    ) {}

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** @return list<array> kartu ruangan (maks 5) */
    public function spaces(): array
    {
        return array_slice(array_values($this->spaces), 0, 5);
    }

    /** @return list<array> */
    public function actions(): array
    {
        return array_values($this->actions);
    }

    /** @return list<string> nama tool yang dipanggil */
    public function used(): array
    {
        return array_values(array_unique($this->used));
    }

    public function addAction(array $action): void
    {
        $this->actions[$action['type']] = $action;
    }

    public function addLoginAction(): void
    {
        $this->addAction(['type' => 'login', 'url' => route('login')]);
    }

    public function addHandoffAction(): void
    {
        $wa = preg_replace('/\D/', '', (string) config('spacerent.whatsapp'));
        $this->addAction(['type' => 'handoff', 'whatsapp' => $wa ? "https://wa.me/{$wa}" : null, 'contact' => route('contact')]);
    }

    /** @return list<string> nama tool yang tersedia bagi pengguna saat ini */
    public function allowed(): array
    {
        $names = self::PUBLIC_TOOLS;
        if ($this->user?->isBuyer()) {
            $names = array_merge($names, self::BUYER_TOOLS);
        }
        if ($this->user?->isSeller()) {
            $names = array_merge($names, self::SELLER_TOOLS);
        }

        return $names;
    }

    /** Definisi tool netral (dipetakan tiap provider ke format masing-masing). Hanya tool yang boleh dipakai peran ini. */
    public function definitions(): array
    {
        $cat = ['type' => 'string', 'enum' => array_keys(Space::CATEGORIES), 'description' => 'Kategori ruangan'];
        $filters = [
            'category' => $cat,
            'min_capacity' => ['type' => 'integer', 'description' => 'Kapasitas minimal (jumlah orang)'],
            'max_price' => ['type' => 'integer', 'description' => 'Harga maksimal dalam rupiah per satuan sewa'],
        ];

        $all = [
            'search_spaces' => [
                'description' => 'Cari ruangan yang tersedia di SpaceRent berdasarkan kata kunci, kategori, wilayah, kapasitas, dan harga.',
                'parameters' => ['type' => 'object', 'properties' => array_merge($filters, [
                    'query' => ['type' => 'string', 'description' => 'Nama ruangan atau kawasan, mis. Kemang'],
                    'area' => ['type' => 'string', 'enum' => Space::AREAS, 'description' => 'Wilayah Jakarta'],
                    'unit' => ['type' => 'string', 'enum' => ['jam', 'hari', 'bulan'], 'description' => 'Satuan sewa'],
                    'limit' => ['type' => 'integer', 'description' => 'Jumlah hasil, maksimal 5'],
                ])],
            ],
            'nearest_spaces' => [
                'description' => 'Cari ruangan terdekat dari lokasi pengguna (koordinat). Bila lokasi belum dibagikan, tool meminta UI menampilkan tombol lokasi.',
                'parameters' => ['type' => 'object', 'properties' => array_merge($filters, [
                    'radius_km' => ['type' => 'number', 'description' => 'Radius maksimal dalam km'],
                    'limit' => ['type' => 'integer', 'description' => 'Jumlah hasil, maksimal 5'],
                ])],
            ],
            'space_details' => [
                'description' => 'Detail satu ruangan: deskripsi, fasilitas, jam buka, aturan, kebijakan batal, biaya, ulasan terbaru.',
                'parameters' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'description' => 'Slug ruangan dari hasil pencarian']], 'required' => ['slug']],
            ],
            'check_availability' => [
                'description' => 'Cek apakah ruangan kosong pada tanggal tertentu. Untuk ruangan per jam, kembalikan jam mulai yang kosong.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'slug' => ['type' => 'string'],
                    'date' => ['type' => 'string', 'description' => 'Tanggal format YYYY-MM-DD'],
                    'duration' => ['type' => 'integer', 'description' => 'Durasi dalam satuan sewa ruangan (jam/hari/bulan), bawaan 1'],
                ], 'required' => ['slug', 'date']],
            ],
            'get_info' => [
                'description' => 'Informasi kebijakan dan cara kerja SpaceRent: pembatalan/refund, jadwal ulang, cara memesan, pembayaran, biaya, ulasan, penarikan dana penjual, kontak.',
                'parameters' => ['type' => 'object', 'properties' => ['topic' => ['type' => 'string', 'enum' => array_keys($this->infoTopics()), 'description' => 'Topik']], 'required' => ['topic']],
            ],
            'my_bookings' => [
                'description' => 'Daftar pesanan milik pengguna yang sedang login (terbaru dulu).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'status' => ['type' => 'string', 'enum' => array_keys(Booking::STATUSES)],
                    'limit' => ['type' => 'integer', 'description' => 'Maksimal 5'],
                ]],
            ],
            'booking_detail' => [
                'description' => 'Detail satu pesanan milik pengguna yang login berdasarkan kode booking (mis. SR-AB12CD). Pesanan orang lain tidak akan ditemukan.',
                'parameters' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string']], 'required' => ['code']],
            ],
            'my_favorites' => [
                'description' => 'Ruangan favorit milik pengguna yang login.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            'seller_overview' => [
                'description' => 'Ringkasan ruangan dan booking pada ruangan milik penjual yang login.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];

        $out = [];
        foreach ($this->allowed() as $name) {
            $out[] = ['name' => $name] + $all[$name];
        }

        return $out;
    }

    /** Jalankan tool. Tidak pernah melempar; kegagalan dikembalikan sebagai ['error' => ...]. */
    public function execute(string $name, array $args = []): array
    {
        if (! in_array($name, $this->allowed(), true)) {
            if (in_array($name, array_merge(self::BUYER_TOOLS, self::SELLER_TOOLS), true)) {
                if (! $this->user) {
                    $this->addLoginAction();

                    return ['error' => 'login_required', 'message' => 'Informasi ini hanya tersedia setelah pengguna masuk ke akun.'];
                }

                return ['error' => 'not_allowed', 'message' => 'Akun ini tidak memiliki akses ke informasi tersebut.'];
            }

            return ['error' => 'unknown_tool'];
        }

        $this->used[] = $name;

        try {
            return match ($name) {
                'search_spaces' => $this->searchSpaces($args),
                'nearest_spaces' => $this->nearestSpaces($args),
                'space_details' => $this->spaceDetails($args),
                'check_availability' => $this->checkAvailability($args),
                'get_info' => $this->getInfo($args),
                'my_bookings' => $this->myBookings($args),
                'booking_detail' => $this->bookingDetail($args),
                'my_favorites' => $this->myFavorites(),
                'seller_overview' => $this->sellerOverview(),
            };
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'tool_failed', 'message' => 'Data tidak dapat dimuat saat ini.'];
        }
    }

    // ---------------------------------------------------------------- tool publik

    private function searchSpaces(array $a): array
    {
        $limit = $this->limit($a);
        $query = Space::query()->where('is_available', true)->filter([
            'category' => $this->enum($a['category'] ?? null, array_keys(Space::CATEGORIES)),
            'area' => $this->enum($a['area'] ?? null, Space::AREAS),
            'unit' => $this->enum($a['unit'] ?? null, ['jam', 'hari', 'bulan']),
            'q' => isset($a['query']) ? mb_substr(trim((string) $a['query']), 0, 80) : null,
        ]);
        $this->applyNumeric($query, $a);

        $found = $query->sortBy(null)->limit($limit)->get();
        $found->each(fn (Space $s) => $this->remember($s));

        return [
            'count' => $found->count(),
            'spaces' => $found->map(fn (Space $s) => $this->summary($s))->all(),
            'note' => $found->isEmpty() ? 'Tidak ada ruangan yang cocok dengan filter ini.' : null,
        ];
    }

    private function nearestSpaces(array $a): array
    {
        $lat = isset($a['lat']) && is_numeric($a['lat']) ? (float) $a['lat'] : $this->lat;
        $lng = isset($a['lng']) && is_numeric($a['lng']) ? (float) $a['lng'] : $this->lng;

        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            $this->addAction(['type' => 'request_location']);

            return ['needs_location' => true, 'message' => 'Lokasi pengguna belum dibagikan. Minta pengguna menekan tombol lokasi atau menyebut kawasan (mis. Kemang), lalu gunakan search_spaces.'];
        }

        $radius = isset($a['radius_km']) && is_numeric($a['radius_km']) ? max(0.5, min(100, (float) $a['radius_km'])) : null;
        $found = Space::nearest($lat, $lng, $this->limit($a), function (Builder $q) use ($a) {
            if ($cat = $this->enum($a['category'] ?? null, array_keys(Space::CATEGORIES))) {
                $q->where('category', $cat);
            }
            $this->applyNumeric($q, $a);
        }, $radius);

        $found->each(fn (Space $s) => $this->remember($s));

        return [
            'count' => $found->count(),
            'spaces' => $found->map(fn (Space $s) => $this->summary($s) + ['distance_km' => $s->distance_km])->all(),
            'note' => $found->isEmpty() ? 'Belum ada ruangan berkoordinat dalam radius tersebut.' : null,
        ];
    }

    private function spaceDetails(array $a): array
    {
        $space = Space::where('slug', (string) ($a['slug'] ?? ''))->where('is_available', true)->first();
        if (! $space) {
            return ['found' => false, 'message' => 'Ruangan tidak ditemukan.'];
        }
        $this->remember($space);

        $reviews = $space->reviews()->where('status', 'published')->latest()->limit(3)->get(['rating', 'comment'])
            ->map(fn ($r) => ['rating' => $r->rating, 'comment' => mb_substr(strip_tags((string) $r->comment), 0, 200)])->all();
        $policy = RefundPolicy::POLICIES[$space->cancellation_policy] ?? RefundPolicy::POLICIES['moderate'];

        return $this->summary($space) + [
            'found' => true,
            'description' => mb_substr(strip_tags((string) $space->description), 0, 600),
            'facilities' => $space->facilities ?? [],
            'address' => $space->address_line,
            'open_hours' => substr((string) $space->open_time, 0, 5).' - '.substr((string) $space->close_time, 0, 5),
            'open_days' => collect($space->openDays())->map(fn ($d) => Space::DAYS[$d] ?? $d)->all(),
            'min_duration' => max(1, (int) $space->min_duration).' '.$space->unit_label,
            'max_duration' => $space->effectiveMaxDuration().' '.$space->unit_label,
            'instant_book' => (bool) $space->instant_book,
            'cleaning_fee' => $space->cleaning_fee ? 'Rp '.number_format((int) $space->cleaning_fee, 0, ',', '.') : null,
            'cancellation_policy' => $policy['label'].': '.$policy['summary'],
            'rules' => $space->rules ? mb_substr(strip_tags((string) $space->rules), 0, 300) : null,
            'recent_reviews' => $reviews,
        ];
    }

    private function checkAvailability(array $a): array
    {
        $space = Space::where('slug', (string) ($a['slug'] ?? ''))->where('is_available', true)->first();
        if (! $space) {
            return ['found' => false, 'message' => 'Ruangan tidak ditemukan.'];
        }
        try {
            $day = Carbon::parse((string) ($a['date'] ?? ''))->startOfDay();
        } catch (\Throwable) {
            return ['error' => 'invalid_date', 'message' => 'Format tanggal tidak dikenali. Gunakan YYYY-MM-DD.'];
        }
        $this->remember($space);
        $duration = max(1, min(60, (int) ($a['duration'] ?? 1)));
        $svc = app(AvailabilityService::class);
        $base = ['space' => $space->name, 'slug' => $space->slug, 'date' => $day->toDateString(), 'unit' => $space->price_unit,
            'note' => 'Ketersediaan final dikonfirmasi saat pemesanan di halaman ruangan.'];

        if (! $space->isOpenOnDay($day)) {
            return $base + ['bookable' => false, 'reason' => 'Ruangan tidak buka pada hari tersebut.'];
        }

        if ($space->price_unit === 'jam') {
            $slots = collect($svc->slotsForDay($space, $day, $duration))->where('available', true)->pluck('time')->values();

            return $base + [
                'duration' => $duration.' jam',
                'bookable' => $slots->isNotEmpty(),
                'available_start_times' => $slots->take(24)->all(),
                'reason' => $slots->isEmpty() ? 'Tidak ada jam mulai yang kosong untuk durasi ini.' : null,
            ];
        }

        [$start, $end] = $svc->range($space, $day, null, $duration);
        $reason = $svc->validate($space, $start, $end, $duration);
        $free = $reason === null ? $svc->isAvailable($space, $start, $end) : false;

        return $base + [
            'duration' => $duration.' '.$space->unit_label,
            'bookable' => $reason === null && $free,
            'reason' => $reason ?? ($free ? null : 'Jadwal tersebut sudah terisi.'),
        ];
    }

    /** @return array<string, string> */
    public function infoTopics(): array
    {
        $fee = (float) config('spacerent.service_fee_percent');
        $expiry = (int) config('midtrans.expiry_minutes', 30);
        $policies = collect(RefundPolicy::POLICIES)->map(fn ($p) => $p['label'].': '.$p['summary'])->implode(' ');

        return [
            'cancellation' => 'Kebijakan batal dipilih pemilik per ruangan dan tampil sebelum memesan. '.$policies.' Bila pemilik atau admin yang membatalkan, pembeli menerima refund penuh. Biaya layanan hanya dikembalikan pada refund 100%.',
            'reschedule' => 'Jadwal ulang maksimal '.(int) config('spacerent.max_reschedules').' kali, paling lambat '.(int) config('spacerent.reschedule_min_hours').' jam sebelum jadwal mulai, lewat halaman booking (tombol Ubah jadwal), selama slot baru kosong.',
            'how_to_book' => 'Buka halaman ruangan, pilih tanggal di kalender, jam mulai, dan durasi, isi data, lalu bayar online. Pemesanan memerlukan akun pembeli (login). Ruangan Instant Book langsung terkunci; ruangan lain menunggu persetujuan pemilik maksimal '.(int) config('spacerent.approval_hours').' jam.',
            'payment' => 'Pembayaran online lewat Midtrans: kartu, virtual account bank, e-wallet, dan QRIS. Slot ditahan '.$expiry.' menit; lewat itu booking dibatalkan otomatis.',
            'fees' => 'Total terdiri dari sewa, biaya kebersihan (bila ada, ditentukan pemilik), dan biaya layanan '.rtrim(rtrim(number_format($fee, 1, ',', ''), '0'), ',').'% . Rincian tampil sebelum membayar.',
            'review' => 'Ulasan dapat ditulis setelah booking selesai (dalam '.(int) config('spacerent.review_window_days').' hari), dengan rating, komentar, foto, dan opsi anonim.',
            'seller_payout' => 'Penjual menerima pendapatan dikurangi komisi platform '.rtrim(rtrim(number_format((float) config('spacerent.commission_percent'), 1, ',', ''), '0'), ',').'%. Penarikan minimal Rp '.number_format((int) config('spacerent.payout_min'), 0, ',', '.').' setelah masa tahan '.(int) config('spacerent.payout_hold_days').' hari, diproses manual oleh admin.',
            'contact' => 'Hubungi tim lewat WhatsApp '.config('spacerent.whatsapp').', telepon '.config('spacerent.hotline').', email '.config('spacerent.email').', atau halaman Hubungi Kami.',
        ];
    }

    private function getInfo(array $a): array
    {
        $topics = $this->infoTopics();
        $topic = (string) ($a['topic'] ?? '');
        if ($topic === 'contact') {
            $this->addHandoffAction();
        }
        if (! isset($topics[$topic])) {
            return ['topics' => array_keys($topics), 'message' => 'Pilih salah satu topik.'];
        }

        return ['topic' => $topic, 'info' => $topics[$topic]];
    }

    // ---------------------------------------------------------------- tool pembeli (selalu scoped ke user)

    private function myBookings(array $a): array
    {
        $q = Booking::query()->with('space')->where('user_id', $this->user->id)->latest('starts_at');
        if ($s = $this->enum($a['status'] ?? null, array_keys(Booking::STATUSES))) {
            $q->where('status', $s);
        }
        $rows = $q->limit($this->limit($a))->get();

        return [
            'count' => $rows->count(),
            'bookings' => $rows->map(fn (Booking $b) => $this->bookingSummary($b))->all(),
            'note' => $rows->isEmpty() ? 'Belum ada pesanan.' : null,
        ];
    }

    private function bookingDetail(array $a): array
    {
        $code = strtoupper(trim((string) ($a['code'] ?? '')));
        $b = Booking::with('space')->where('user_id', $this->user->id)->where('code', $code)->first();
        if (! $b) {
            return ['found' => false, 'message' => 'Pesanan dengan kode itu tidak ditemukan di akun Anda.'];
        }

        $d = $this->bookingSummary($b) + [
            'found' => true,
            'guests' => $b->guests,
            'cancellation_policy' => RefundPolicy::label((string) $b->cancellation_policy),
            'refund_status' => $b->refund_status,
            'refund_amount' => $b->refund_amount ? 'Rp '.number_format((int) $b->refund_amount, 0, ',', '.') : null,
            'reschedule_count' => (int) $b->reschedule_count,
        ];
        if ($b->isCancellable()) {
            $d['refund_percent_if_cancelled_now'] = RefundPolicy::percent($b);
        }

        return $d;
    }

    private function myFavorites(): array
    {
        $rows = Favorite::with('space')->where('user_id', $this->user->id)->latest()->limit(5)->get()
            ->pluck('space')->filter();
        $rows->each(fn (Space $s) => $this->remember($s));

        return ['count' => $rows->count(), 'spaces' => $rows->map(fn (Space $s) => $this->summary($s))->values()->all()];
    }

    // ---------------------------------------------------------------- tool penjual (scoped ke ruangan miliknya)

    private function sellerOverview(): array
    {
        $spaces = Space::where('user_id', $this->user->id)->get();
        $ids = $spaces->pluck('id');
        $byStatus = Booking::whereIn('space_id', $ids)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $upcoming = Booking::with('space')->whereIn('space_id', $ids)->whereIn('status', ['confirmed', 'pending', 'awaiting_approval'])
            ->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get();

        return [
            'spaces' => $spaces->map(fn (Space $s) => ['name' => $s->name, 'slug' => $s->slug, 'available' => (bool) $s->is_available, 'rating' => $s->hasRating() ? $s->rating : null])->all(),
            'bookings_by_status' => $byStatus->mapWithKeys(fn ($n, $st) => [Booking::STATUSES[$st] ?? $st => $n])->all(),
            'upcoming' => $upcoming->map(fn (Booking $b) => [
                'code' => $b->code, 'space' => $b->space?->name, 'start' => $b->starts_at?->format('d M Y H:i'),
                'status' => $b->status_label,
            ])->all(),
        ];
    }

    // ---------------------------------------------------------------- util

    private function bookingSummary(Booking $b): array
    {
        return [
            'code' => $b->code,
            'space' => $b->space?->name,
            'space_url' => $b->space ? route('spaces.show', $b->space) : null,
            'start' => $b->starts_at?->format('d M Y H:i'),
            'duration' => $b->duration.' '.($b->space?->unit_label ?? ''),
            'status' => $b->status_label,
            'payment' => $b->payment_label,
            'total' => 'Rp '.number_format((int) $b->total, 0, ',', '.'),
            'can_cancel' => $b->isCancellable(),
            'booking_url' => route('booking.show', $b),
        ];
    }

    private function summary(Space $s): array
    {
        return [
            'name' => $s->name,
            'slug' => $s->slug,
            'category' => $s->category_label,
            'location' => $s->location,
            'capacity' => (int) $s->capacity,
            'price' => $s->formatted_price.' / '.$s->unit_label,
            'rating' => $s->hasRating() ? (float) $s->rating : null,
            'rating_count' => (int) $s->rating_count,
            'instant_book' => (bool) $s->instant_book,
            'url' => route('spaces.show', $s),
        ];
    }

    private function remember(Space $s): void
    {
        $this->spaces[$s->slug] = [
            'name' => $s->name,
            'slug' => $s->slug,
            'url' => route('spaces.show', $s),
            'image' => $s->image_url,
            'price' => $s->formatted_price,
            'unit' => $s->unit_label,
            'capacity' => (int) $s->capacity,
            'area' => $s->area,
            'district' => $s->district,
            'rating' => $s->hasRating() ? (float) $s->rating : null,
            'rating_count' => (int) $s->rating_count,
            'distance_km' => $s->distance_km ?? null,
            'lat' => $s->latitude,
            'lng' => $s->longitude,
        ];
    }

    private function applyNumeric(Builder $q, array $a): void
    {
        if (isset($a['min_capacity']) && is_numeric($a['min_capacity'])) {
            $q->where('capacity', '>=', max(1, (int) $a['min_capacity']));
        }
        if (isset($a['max_price']) && is_numeric($a['max_price'])) {
            $q->where('price', '<=', max(0, (int) $a['max_price']));
        }
    }

    private function limit(array $a): int
    {
        return max(1, min(5, (int) ($a['limit'] ?? 5)));
    }

    private function enum(mixed $v, array $allowed): ?string
    {
        return is_string($v) && in_array($v, $allowed, true) ? $v : null;
    }
}
