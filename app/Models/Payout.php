<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    public const STATUSES = ['requested' => 'Diproses', 'paid' => 'Sudah dicairkan', 'rejected' => 'Ditolak'];

    protected $fillable = [
        'user_id', 'amount', 'bookings_count', 'status', 'bank_name', 'bank_account_number', 'bank_account_name',
        'reference', 'note', 'requested_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
