<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    use HasFactory;

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

    protected $fillable = [
        'name', 'slug', 'category', 'area', 'district', 'capacity', 'price',
        'price_unit', 'image', 'gallery', 'description', 'facilities',
        'rating', 'is_available', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'facilities' => 'array',
            'is_available' => 'boolean',
            'rating' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getImageUrlAttribute(): string
    {
        $image = $this->image;

        return str_starts_with($image, 'http') ? $image : asset('storage/'.$image);
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

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
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
            });
    }
}
