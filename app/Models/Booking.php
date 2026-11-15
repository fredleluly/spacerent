<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    /** Status siklus sewa. */
    public const STATUSES = [
        'awaiting_approval' => 'Menunggu persetujuan',
        'pending' => 'Menunggu pembayaran',
        'confirmed' => 'Dikonfirmasi',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'no_show' => 'Tidak hadir',
    ];

    /** Status uang. */
    public const PAYMENT_STATUSES = [
        'unpaid' => 'Belum dibayar',
        'paid' => 'Lunas',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'refund_pending' => 'Menunggu refund',
        'refunded' => 'Dikembalikan',
    ];

    /** Transisi status yang sah. Selain ini ditolak. */
    public const TRANSITIONS = [
        'awaiting_approval' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled', 'no_show'],
        'completed' => [],
        'cancelled' => ['confirmed'], // hanya pembayaran terlambat saat slot masih kosong (lihat BookingService)
        'no_show' => [],
    ];

    protected $fillable = [
        'code', 'space_id', 'name', 'email', 'phone', 'company', 'date',
        'start_time', 'duration', 'guests', 'total', 'notes', 'status',
        'payment_status', 'user_id', 'expires_at', 'order_id', 'snap_token', 'payment_type', 'paid_at',
        'midtrans_payload', 'starts_at', 'end_at', 'subtotal', 'cleaning_fee', 'service_fee',
        'cancellation_policy', 'approval_deadline_at', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'refund_amount', 'refund_status', 'reschedule_count', 'reminded_week_at', 'reminded_day_at',
        'commission_percent', 'payout_id',
    ];

    protected static function booted(): void
    {
        // Menjaga invarian: setiap booking punya rentang waktu terukur (data lama/impor tanpa starts_at tidak merusak slot).
        static::saving(function (Booking $b) {
            if (($b->starts_at === null || $b->end_at === null) && $b->date && $b->space_id) {
                $space = Space::withTrashed()->find($b->space_id);
                if ($space) {
                    [$start, $end] = app(\App\Services\AvailabilityService::class)
                        ->range($space, $b->date, $b->start_time, max(1, (int) $b->duration));
                    $b->starts_at = $b->starts_at ?? $start;
                    $b->end_at = $b->end_at ?? ($b->starts_at->gt($start) ? $b->starts_at->copy()->addSeconds($start->diffInSeconds($end)) : $end);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'approval_deadline_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminded_week_at' => 'datetime',
            'reminded_day_at' => 'datetime',
            'midtrans_payload' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPaymentLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isPayable(): bool
    {
        return $this->status === 'pending'
            && $this->payment_status === 'unpaid'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === 'awaiting_approval'
            && $this->approval_deadline_at !== null
            && $this->approval_deadline_at->isFuture();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['awaiting_approval', 'pending', 'confirmed'], true);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isPast();
    }

    public function hasEnded(): bool
    {
        return $this->end_at !== null && $this->end_at->isPast();
    }

    public function isCancellable(): bool
    {
        return $this->isActive() && ! $this->hasStarted();
    }

    public function isReviewable(): bool
    {
        return $this->status === 'completed'
            && $this->review === null
            && $this->end_at !== null
            && $this->end_at->copy()->addDays((int) config('spacerent.review_window_days', 30))->isFuture();
    }

    /** Slot yang sedang ditahan/dipakai booking ini. */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', ['confirmed', 'completed'])
                ->orWhere(fn (Builder $p) => $p->where('status', 'pending')->where('expires_at', '>', now()))
                ->orWhere(fn (Builder $p) => $p->where('status', 'awaiting_approval')->where('approval_deadline_at', '>', now()));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class)->withTrashed();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'SR-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
