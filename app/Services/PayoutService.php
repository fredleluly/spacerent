<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pendapatan penjual = (sewa + biaya kebersihan yang ditahan) dikurangi komisi platform.
 * Biaya layanan yang dibayar pembeli sepenuhnya milik platform. Dana cair setelah masa tahan
 * (payout_hold_days) sejak sewa selesai, dan hanya bisa ditarik sekali per booking.
 */
class PayoutService
{
    /** Dasar yang menjadi hak penjual sebelum komisi, setelah memperhitungkan refund ke pembeli. */
    public function retainedBase(Booking $b): int
    {
        if (! $b->paid_at || $b->payment_status === 'unpaid') {
            return 0;
        }
        $base = $b->subtotal > 0 ? (int) ($b->subtotal + $b->cleaning_fee) : (int) $b->total;

        return match ($b->status) {
            'confirmed', 'completed', 'no_show' => $base,
            'cancelled' => max(0, $base - ($b->refund_amount >= $b->total ? $base : (int) $b->refund_amount)),
            default => 0,
        };
    }

    public function commission(Booking $b): int
    {
        return (int) round($this->retainedBase($b) * (float) $b->commission_percent / 100);
    }

    /** Bersih untuk penjual. */
    public function earning(Booking $b): int
    {
        return max(0, $this->retainedBase($b) - $this->commission($b));
    }

    /** Kapan dana booking ini boleh dicairkan; null bila belum final. */
    public function availableAt(Booking $b): ?Carbon
    {
        $hold = (int) config('spacerent.payout_hold_days', 1);

        return match ($b->status) {
            'completed', 'no_show' => $b->end_at?->copy()->addDays($hold),
            'cancelled' => $b->cancelled_at?->copy()->addDays($hold),
            default => null,
        };
    }

    /** @return Collection<int, Booking> booking milik penjual yang dananya sudah bisa ditarik. */
    public function eligible(User $seller): Collection
    {
        return $this->ownBookings($seller)
            ->whereNull('payout_id')
            ->whereIn('status', ['completed', 'no_show', 'cancelled'])
            ->get()
            ->filter(fn (Booking $b) => $this->earning($b) > 0 && ($at = $this->availableAt($b)) && $at->isPast())
            ->values();
    }

    /** @return array{available:int, upcoming:int, processing:int, paid:int} */
    public function balances(User $seller): array
    {
        $eligible = $this->eligible($seller);
        $eligibleIds = $eligible->pluck('id');

        $upcoming = $this->ownBookings($seller)
            ->whereNull('payout_id')->whereNotNull('paid_at')
            ->whereIn('status', ['confirmed', 'completed', 'no_show', 'cancelled'])
            ->get()
            ->reject(fn (Booking $b) => $eligibleIds->contains($b->id))
            ->sum(fn (Booking $b) => $this->earning($b));

        return [
            'available' => (int) $eligible->sum(fn (Booking $b) => $this->earning($b)),
            'upcoming' => (int) $upcoming,
            'processing' => (int) Payout::where('user_id', $seller->id)->where('status', 'requested')->sum('amount'),
            'paid' => (int) Payout::where('user_id', $seller->id)->where('status', 'paid')->sum('amount'),
        ];
    }

    public function request(User $seller): Payout
    {
        if (! $seller->bank_name || ! $seller->bank_account_number || ! $seller->bank_account_name) {
            throw new BookingException('Lengkapi data rekening bank terlebih dulu.', 'bank');
        }

        return DB::transaction(function () use ($seller) {
            User::whereKey($seller->id)->lockForUpdate()->first(); // serialkan permintaan penjual yang sama
            $eligible = $this->eligible($seller);
            $amount = (int) $eligible->sum(fn (Booking $b) => $this->earning($b));
            $min = (int) config('spacerent.payout_min', 50000);

            if ($eligible->isEmpty() || $amount < $min) {
                throw new BookingException('Saldo tersedia belum mencapai minimum penarikan Rp '.number_format($min, 0, ',', '.').'.', 'amount');
            }

            $payout = Payout::create([
                'user_id' => $seller->id, 'amount' => $amount, 'bookings_count' => $eligible->count(),
                'bank_name' => $seller->bank_name, 'bank_account_number' => $seller->bank_account_number,
                'bank_account_name' => $seller->bank_account_name, 'status' => 'requested', 'requested_at' => now(),
            ]);
            Booking::whereIn('id', $eligible->pluck('id'))->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    public function markPaid(Payout $payout, ?string $reference): Payout
    {
        return $this->decide($payout, fn (Payout $p) => $p->forceFill(['status' => 'paid', 'reference' => $reference, 'processed_at' => now()])->save());
    }

    /** Menolak mengembalikan booking ke saldo tersedia. */
    public function reject(Payout $payout, ?string $note): Payout
    {
        return $this->decide($payout, function (Payout $p) use ($note) {
            Booking::where('payout_id', $p->id)->update(['payout_id' => null]);
            $p->forceFill(['status' => 'rejected', 'note' => $note, 'processed_at' => now()])->save();
        });
    }

    private function decide(Payout $payout, \Closure $fn): Payout
    {
        DB::transaction(function () use ($payout, $fn) {
            $p = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'requested') {
                throw new BookingException('Permintaan penarikan ini sudah diproses.');
            }
            $fn($p);
            $payout->setRawAttributes($p->getAttributes(), true);
        });

        return $payout;
    }

    private function ownBookings(User $seller)
    {
        return Booking::whereHas('space', fn ($q) => $q->withTrashed()->where('user_id', $seller->id));
    }
}
