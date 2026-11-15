<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'meeting-room' => ['label' => 'Meeting Room', 'icon' => 'users'],
        'private-office' => ['label' => 'Private Office', 'icon' => 'building-2'],
        'coworking' => ['label' => 'Coworking / Desk', 'icon' => 'laptop'],
        'event-space' => ['label' => 'Event Space', 'icon' => 'party-popper'],
        'studio-podcast' => ['label' => 'Studio Podcast', 'icon' => 'mic'],
        'virtual-office' => ['label' => 'Virtual Office', 'icon' => 'briefcase'],
    ];

    public const AREAS = [
        'Jakarta Pusat',
        'Jakarta Selatan',
        'Jakarta Barat',
        'Jakarta Utara',
        'Jakarta Timur',
    ];

    public const CAPACITIES = [
        '1-5' => '1 - 5 orang',
        '6-10' => '6 - 10 orang',
        '11-20' => '11 - 20 orang',
        '21-50' => '21 - 50 orang',
        '51-1000' => 'Lebih dari 50 orang',
    ];

    public const SORTS = [
        'recommended' => 'Rekomendasi',
        'rating' => 'Rating tertinggi',
        'price_asc' => 'Harga terendah',
        'price_desc' => 'Harga tertinggi',
        'newest' => 'Terbaru',
    ];

    public const DAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 0 => 'Min'];

    /** Rating tidak boleh diisi manual: dihitung dari ulasan nyata. */
    protected $fillable = [
        'name', 'slug', 'category', 'area', 'district', 'capacity', 'price',
        'price_unit', 'image', 'gallery', 'description', 'facilities',
        'is_available', 'open_time', 'close_time', 'open_days', 'min_duration', 'max_duration',
        'buffer_minutes', 'lead_time_hours', 'max_advance_days', 'instant_book',
        'cancellation_policy', 'cleaning_fee', 'rules', 'checkin_info',
        'latitude', 'longitude', 'address_line',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'facilities' => 'array',
            'open_days' => 'array',
            'is_available' => 'boolean',
            'instant_book' => 'boolean',
            'rating' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** Representasi ringkas untuk peta dan chatbot (hanya data publik). */
    public function toMapMarker(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => route('spaces.show', $this),
            'image' => $this->image_url,
            'price' => $this->formatted_price,
            'unit' => $this->unit_label,
            'capacity' => (int) $this->capacity,
            'area' => $this->area,
            'district' => $this->district,
            'category' => $this->category_label,
            'rating' => $this->hasRating() ? (float) $this->rating : null,
            'rating_count' => (int) $this->rating_count,
            'address' => $this->address_line,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'distance_km' => $this->distance_km ?? null,
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function scopeWithCoordinates(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /** Jarak lingkaran besar dalam km (rumus haversine). */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * Ruangan paling dekat dari titik (lat, lng). Dihitung di PHP agar jalan di SQLite maupun MySQL.
     * Setiap hasil punya atribut `distance_km` (dibulatkan 1 desimal).
     *
     * @param  (callable(Builder): mixed)|null  $scope  menyaring query dasar (kategori, harga, dll.)
     * @return \Illuminate\Support\Collection<int, Space>
     */
    public static function nearest(float $lat, float $lng, int $limit = 5, ?callable $scope = null, ?float $maxKm = null): \Illuminate\Support\Collection
    {
        $query = static::query()->withCoordinates()->where('is_available', true);
        if ($scope) {
            $scope($query);
        }

        return $query->get()
            ->each(fn (Space $s) => $s->setAttribute('distance_km', round(self::haversineKm($lat, $lng, $s->latitude, $s->longitude), 1)))
            ->when($maxKm !== null, fn ($c) => $c->filter(fn (Space $s) => $s->distance_km <= $maxKm))
            ->sortBy('distance_km')
            ->take($limit)
            ->values();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function blackouts(): HasMany
    {
        return $this->hasMany(SpaceBlackout::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function getImageUrlAttribute(): string
    {
        $image = $this->image;

        return str_starts_with($image, 'http') ? $image : asset('storage/'.$image);
    }

    /** @return list<string> */
    public function getGalleryUrlsAttribute(): array
    {
        $urls = collect([$this->image_url]);
        foreach ($this->gallery ?? [] as $g) {
            $urls->push(str_starts_with($g, 'http') ? $g : asset('storage/'.$g));
        }

        return $urls->unique()->values()->all();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category]['label'] ?? $this->category;
    }

    public function getLocationAttribute(): string
    {
        return "{$this->area} ({$this->district})";
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    public function getUnitLabelAttribute(): string
    {
        return ['jam' => 'jam', 'hari' => 'hari', 'bulan' => 'bulan'][$this->price_unit] ?? $this->price_unit;
    }

    /** Ruangan baru tanpa ulasan tidak punya rating (bukan rating palsu). */
    public function hasRating(): bool
    {
        return $this->rating_count > 0;
    }

    public function effectiveMaxDuration(): int
    {
        return $this->max_duration ?: match ($this->price_unit) {
            'jam' => 12,
            'bulan' => 12,
            default => 31,
        };
    }

    /** @return list<int> */
    public function openDays(): array
    {
        return $this->open_days === null ? [0, 1, 2, 3, 4, 5, 6] : array_map('intval', $this->open_days);
    }

    public function isOpenOnDay(CarbonInterface $day): bool
    {
        return in_array($day->dayOfWeek, $this->openDays(), true);
    }

    /** Hitung ulang rating & jumlah ulasan dari ulasan yang tampil. */
    public function refreshRating(): void
    {
        $q = $this->reviews()->where('status', 'published');
        $this->forceFill([
            'rating' => round((float) $q->avg('rating'), 1),
            'rating_count' => $q->count(),
        ])->save();
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $query
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['area'] ?? null, fn ($q, $v) => $q->where('area', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$v}%")
                    ->orWhere('district', 'like', "%{$v}%")
                    ->orWhere('description', 'like', "%{$v}%"));
            })
            ->when($filters['capacity'] ?? null, function ($q, $v) {
                if (preg_match('/^(\d+)-(\d+)$/', $v, $m)) {
                    $q->whereBetween('capacity', [(int) $m[1], (int) $m[2]]);
                }
            })
            ->when($filters['min_price'] ?? null, fn ($q, $v) => $q->where('price', '>=', (int) $v))
            ->when($filters['max_price'] ?? null, fn ($q, $v) => $q->where('price', '<=', (int) $v))
            ->when($filters['min_rating'] ?? null, fn ($q, $v) => $q->where('rating_count', '>', 0)->where('rating', '>=', (float) $v))
            ->when($filters['unit'] ?? null, fn ($q, $v) => $q->where('price_unit', $v));

        return $query;
    }

    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('rating_count')->orderByDesc('rating')->orderBy('name'),
        };
    }
}
