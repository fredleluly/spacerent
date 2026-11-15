<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    public const SUB_RATINGS = [
        'cleanliness' => 'Kebersihan',
        'accuracy' => 'Kesesuaian deskripsi',
        'location' => 'Lokasi',
        'facilities' => 'Fasilitas',
        'value' => 'Nilai dibanding harga',
    ];

    protected $fillable = [
        'booking_id', 'space_id', 'user_id', 'rating', 'cleanliness', 'accuracy', 'location', 'facilities',
        'value', 'comment', 'is_anonymous', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class);
    }

    /** Nama yang tampil ke publik; anonim menyembunyikan identitas (tetap tersimpan untuk moderasi). */
    public function getAuthorNameAttribute(): string
    {
        return $this->is_anonymous ? 'Pengguna SpaceRent' : ($this->user?->name ?? 'Pengguna SpaceRent');
    }

    public function isEditable(): bool
    {
        return $this->created_at->copy()->addDays((int) config('spacerent.review_edit_days', 7))->isFuture();
    }
}
