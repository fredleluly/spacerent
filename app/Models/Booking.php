<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'code', 'space_id', 'name', 'email', 'phone', 'company', 'date',
        'start_time', 'duration', 'guests', 'total', 'notes', 'status',
        'payment_status', 'user_id', 'expires_at', 'order_id', 'snap_token', 'payment_type', 'paid_at',
        'midtrans_payload', 'starts_at', 'end_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'midtrans_payload' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isPayable(): bool
    {
        return $this->status === 'pending'
            && $this->payment_status === 'unpaid'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'confirmed')
                ->orWhere(fn (Builder $p) => $p->where('status', 'pending')->where('expires_at', '>', now()));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'SR-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
