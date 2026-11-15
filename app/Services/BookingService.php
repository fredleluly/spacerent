<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Mail\BookingNotice;
use App\Mail\BookingPaid;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Support\RefundPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu pintu untuk seluruh siklus booking. Controller tidak boleh mengubah status langsung.
 * Invarian: satu slot hanya dipegang satu booking; confirmed => paid; paid + cancelled => ada status refund.
 */
class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    /** @return array{subtotal:int, cleaning_fee:int, service_fee:int, total:int} */
    public function quote(Space $space, int $duration): array
    {
        $subtotal = (int) $space->price * $duration;
        $cleaning = (int) $space->cleaning_fee;
        $service = (int) round(($subtotal + $cleaning) * (float) config('spacerent.service_fee_percent', 5) / 100);

        return [
            'subtotal' => $subtotal,
            'cleaning_fee' => $cleaning,
            'service_fee' => $service,
            'total' => $subtotal + $cleaning + $service,
        ];
    }

    /** @param array<string,mixed> $data name,email,phone,company,date,start_time,duration,guests,notes */
    public function create(User $user, Space $space, array $data): Booking
    {
        if (! $space->is_available || $space->trashed()) {
            throw new BookingException('Ruangan ini sedang tidak menerima pemesanan.');
        }

        $duration = (int) $data['duration'];
        [$start, $end] = $this->availability->range($space, $data['date'], $data['start_time'] ?? null, $duration);

        if ($error = $this->availability->validate($space, $start, $end, $duration)) {
            throw new BookingException($error);
        }

        // Klik ganda / kirim ulang: kembalikan booking aktif yang identik, jangan buat baru.
        $duplicate = Booking::where('user_id', $user->id)->where('space_id', $space->id)
            ->where('starts_at', $start)->where('end_at', $end)->occupying()->first();
        if ($duplicate) {
            return $duplicate;
        }

        $cap = (int) config('spacerent.max_pending_per_user', 3);
        $open = Booking::where('user_id', $user->id)
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('status', 'pending')->where('expires_at', '>', now()))
                ->orWhere(fn ($p) => $p->where('status', 'awaiting_approval')->where('approval_deadline_at', '>', now())))
            ->count();
        if ($open >= $cap) {
            throw new BookingException("Anda masih punya {$open} booking yang belum selesai. Selesaikan pembayarannya atau tunggu kedaluwarsa sebelum memesan lagi.");
        }

        $quote = $this->quote($space, $duration);
        $instant = (bool) $space->instant_book;

        $lock = Cache::lock('space-booking-'.$space->id, 10);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new BookingException('Sistem sedang sibuk. Coba lagi sebentar.');
        }

        try {
            $booking = DB::transaction(function () use ($user, $space, $data, $start, $end, $duration, $quote, $instant) {
                if (! $this->availability->isAvailable($space, $start, $end)) {
                    throw new BookingException('Slot sudah terisi pada waktu yang Anda pilih. Silakan pilih waktu lain.');
                }

                return Booking::create([
                    'code' => Booking::generateCode(),
                    'user_id' => $user->id,
                    'space_id' => $space->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'company' => $data['company'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'guests' => (int) $data['guests'],
                    'duration' => $duration,
                    'date' => $start->toDateString(),
                    'start_time' => $space->price_unit === 'jam' ? $start->format('H:i') : null,
                    'starts_at' => $start,
                    'end_at' => $end,
                    'status' => $instant ? 'pending' : 'awaiting_approval',
                    'payment_status' => 'unpaid',
                    'expires_at' => $instant ? now()->addMinutes((int) config('midtrans.expiry_minutes', 30)) : null,
                    'approval_deadline_at' => $instant ? null : now()->addHours((int) config('spacerent.approval_hours', 24)),
                    'cancellation_policy' => $space->cancellation_policy ?: 'moderate',
                    'commission_percent' => (float) config('spacerent.commission_percent', 10),
                ] + $quote);
            });
        } finally {
            $lock->release();
        }

        if (! $instant) {
            $this->notifyOwner($booking, 'Permintaan booking baru', [
                "{$booking->name} meminta jadwal untuk {$space->name}. Setujui atau tolak sebelum ".$booking->approval_deadline_at->translatedFormat('d F Y H:i').'.',
            ], 'Tinjau Permintaan', route('seller.bookings.show', $booking));
        }

        return $booking;
    }

    // ---------- Persetujuan penjual ----------

    public function approve(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $b) {
            if (! $b->isAwaitingApproval()) {
                throw new BookingException('Permintaan ini sudah tidak bisa disetujui (kedaluwarsa atau sudah diproses).');
            }
            $b->forceFill([
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) config('midtrans.expiry_minutes', 30)),
                'approval_deadline_at' => null,
            ])->save();
            $this->notifyBuyer($b, 'Permintaan disetujui, silakan bayar', [
                'Pemilik menyetujui permintaan Anda. Selesaikan pembayaran dalam '.config('midtrans.expiry_minutes', 30).' menit agar jadwal tidak dilepas.',
            ], 'Bayar Sekarang');

            return $b;
        });
    }

    public function reject(Booking $booking, ?string $reason = null): Booking
    {
        return $this->locked($booking, function (Booking $b) use ($reason) {
            if ($b->status !== 'awaiting_approval') {
                throw new BookingException('Permintaan ini sudah diproses.');
            }
            $this->markCancelled($b, 'seller', $reason ?: 'Permintaan ditolak pemilik');
            $this->notifyBuyer($b, 'Permintaan ditolak', ['Mohon maaf, pemilik tidak dapat menerima jadwal ini. Anda bisa memilih jadwal atau ruangan lain.'], 'Cari Ruangan Lain', route('home'));

            return $b;
        });
    }

    // ---------- Pembatalan & refund ----------

    /** @param 'buyer'|'seller'|'admin'|'system' $actor */
    public function cancel(Booking $booking, string $actor, ?string $reason = null): Booking
    {
        $result = $this->locked($booking, function (Booking $b) use ($actor, $reason) {
            $allowed = in_array($actor, ['admin', 'system'], true) ? $b->isActive() : $b->isCancellable();
            if (! $allowed) {
                throw new BookingException($b->hasStarted() && $b->isActive()
                    ? 'Booking yang sudah berjalan tidak bisa dibatalkan.'
                    : 'Booking dengan status ini tidak bisa dibatalkan.');
            }

            $quote = RefundPolicy::quote($b, $actor);
            $this->markCancelled($b, $actor, $reason);

            if ($b->payment_status === 'paid' && $quote['amount'] > 0) {
                $b->forceFill([
                    'payment_status' => 'refund_pending',
                    'refund_amount' => $quote['amount'],
                    'refund_status' => 'pending',
                ])->save();
            }

            return $b;
        });

        $this->releaseGatewayTransaction($result);

        $by = ['buyer' => 'pembeli', 'seller' => 'pemilik', 'admin' => 'admin SpaceRent', 'system' => 'sistem'][$actor] ?? $actor;
        $money = $result->refund_amount > 0
            ? 'Refund Rp '.number_format($result->refund_amount, 0, ',', '.').' sedang diproses.'
            : ($result->payment_status === 'paid' ? 'Tidak ada pengembalian dana sesuai kebijakan pembatalan.' : 'Anda belum dikenai biaya.');
        $lines = ["Booking dibatalkan oleh {$by}.".($reason ? " Alasan: {$reason}." : ''), $money];

        if ($actor !== 'buyer') {
            $this->notifyBuyer($result, 'Booking dibatalkan', $lines);
        }
        if ($actor !== 'seller') {
            $this->notifyOwner($result, 'Booking dibatalkan', $lines, 'Lihat Booking', route('seller.bookings.show', $result));
        }

        return $result;
    }

    public function markRefunded(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $b) {
            if ($b->refund_status !== 'pending') {
                throw new BookingException('Booking ini tidak sedang menunggu refund.');
            }
            $b->forceFill(['payment_status' => 'refunded', 'refund_status' => 'refunded'])->save();
            $this->notifyBuyer($b, 'Refund diproses', ['Pengembalian dana Rp '.number_format($b->refund_amount, 0, ',', '.').' telah diproses ke metode pembayaran Anda.']);

            return $b;
        });
    }

    // ---------- Pembayaran ----------

    /**
     * Pembayaran diterima. Aman terhadap pembayaran terlambat: bila slot sudah diambil orang lain atau
     * booking sudah dibatalkan manual, uang dicatat sebagai refund_pending, bukan menghidupkan booking.
     *
     * @return 'confirmed'|'refund_pending'|'ignored'
     */
    public function settlePayment(Booking $booking, ?Carbon $paidAt = null, ?string $paymentType = null): string
    {
        $outcome = 'ignored';

        $fresh = DB::transaction(function () use ($booking, $paidAt, $paymentType, &$outcome) {
            $b = Booking::withTrashed()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (in_array($b->payment_status, ['paid', 'refund_pending', 'refunded'], true)) {
                return $b;
            }

            $revivable = $b->status === 'pending' || ($b->status === 'cancelled' && $b->cancelled_by === 'system');
            $slotFree = $this->availability->isAvailable($b->space, $b->starts_at, $b->end_at, $b->id)
                && ! $this->availability->hitsBlackout($b->space, $b->starts_at, $b->end_at);

            if ($revivable && ! $b->trashed() && $slotFree && $b->end_at->isFuture()) {
                $b->forceFill([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'paid_at' => $paidAt ?? now(),
                    'payment_type' => $paymentType ?? $b->payment_type,
                    'cancelled_at' => null, 'cancelled_by' => null, 'cancel_reason' => null,
                ])->save();
                $outcome = 'confirmed';

                return $b;
            }

            $b->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'refund_pending',
                'paid_at' => $paidAt ?? now(),
                'payment_type' => $paymentType ?? $b->payment_type,
                'cancelled_at' => $b->cancelled_at ?? now(),
                'cancelled_by' => $b->cancelled_by ?? 'system',
                'cancel_reason' => $b->cancel_reason ?? 'Pembayaran diterima setelah jadwal tidak lagi tersedia',
                'refund_amount' => (int) $b->total,
                'refund_status' => 'pending',
            ])->save();
            $outcome = 'refund_pending';

            return $b;
        });

        $booking->setRawAttributes($fresh->getAttributes(), true);

        if ($outcome === 'confirmed') {
            try {
                Mail::to($fresh->email)->send(new BookingPaid($fresh->loadMissing('space')));
            } catch (\Throwable $e) {
                Log::warning('BookingPaid mail failed: '.$e->getMessage());
            }
            $this->notifyOwner($fresh, 'Booking baru terkonfirmasi', ["{$fresh->name} telah membayar. Jadwal terkonfirmasi."], 'Lihat Booking', route('seller.bookings.show', $fresh));
        } elseif ($outcome === 'refund_pending') {
            $this->notifyBuyer($fresh, 'Pembayaran Anda akan dikembalikan', [
                'Pembayaran diterima, tetapi jadwal sudah tidak tersedia. Dana Rp '.number_format($fresh->refund_amount, 0, ',', '.').' akan dikembalikan sepenuhnya.',
            ]);
        }

        return $outcome;
    }

    /** Pembayaran gagal/kedaluwarsa dari gateway. Tidak pernah menurunkan booking yang sudah dibayar. */
    public function failPayment(Booking $booking, string $paymentStatus): void
    {
        DB::transaction(function () use ($booking, $paymentStatus) {
            $b = Booking::withTrashed()->whereKey($booking->getKey())->lockForUpdate()->first();
            if ($b->payment_status !== 'unpaid') {
                return;
            }
            if ($b->status === 'pending') {
                $b->forceFill([
                    'status' => 'cancelled', 'payment_status' => $paymentStatus,
                    'cancelled_at' => now(), 'cancelled_by' => 'system', 'cancel_reason' => 'Pembayaran gagal atau kedaluwarsa',
                ])->save();
            } elseif ($b->status === 'cancelled') {
                $b->forceFill(['payment_status' => $paymentStatus])->save();
            }
        });
        $booking->refresh();
    }

    // ---------- Selesai, tidak hadir, jadwal ulang ----------

    public function complete(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $b) {
            if ($b->status !== 'confirmed' || $b->payment_status !== 'paid') {
                throw new BookingException('Hanya booking terkonfirmasi dan lunas yang bisa diselesaikan.');
            }
            if (! $b->hasStarted()) {
                throw new BookingException('Booking belum dimulai, belum bisa ditandai selesai.');
            }
            $b->forceFill(['status' => 'completed'])->save();
            $this->notifyBuyer($b, 'Bagaimana pengalaman Anda?', ['Terima kasih sudah memakai ruangan ini. Bagikan ulasan dan foto agar membantu penyewa lain.'], 'Tulis Ulasan', route('booking.show', $b).'#ulasan');

            return $b;
        });
    }

    public function markNoShow(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $b) {
            if ($b->status !== 'confirmed' || $b->payment_status !== 'paid' || ! $b->hasStarted()) {
                throw new BookingException('Tandai tidak hadir hanya untuk booking lunas yang sudah dimulai.');
            }
            $b->forceFill(['status' => 'no_show'])->save();

            return $b;
        });
    }

    public function reschedule(Booking $booking, string $date, ?string $startTime): Booking
    {
        $space = $booking->space;
        $lock = Cache::lock('space-booking-'.$space->id, 10);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new BookingException('Sistem sedang sibuk. Coba lagi sebentar.');
        }

        try {
            $result = $this->locked($booking, function (Booking $b) use ($space, $date, $startTime) {
                if ($b->status !== 'confirmed' || $b->payment_status !== 'paid') {
                    throw new BookingException('Hanya booking lunas dan terkonfirmasi yang bisa dijadwal ulang.');
                }
                if ($b->reschedule_count >= (int) config('spacerent.max_reschedules', 1)) {
                    throw new BookingException('Jadwal booking ini sudah pernah diubah. Batas perubahan sudah habis.');
                }
                $minHours = (int) config('spacerent.reschedule_min_hours', 24);
                if (now()->floatDiffInHours($b->starts_at, false) < $minHours) {
                    throw new BookingException("Jadwal hanya bisa diubah minimal {$minHours} jam sebelum mulai.");
                }

                [$start, $end] = $this->availability->range($space, $date, $startTime, (int) $b->duration);
                if ($error = $this->availability->validate($space, $start, $end, (int) $b->duration)) {
                    throw new BookingException($error);
                }
                if (! $this->availability->isAvailable($space, $start, $end, $b->id)) {
                    throw new BookingException('Jadwal baru sudah terisi. Jadwal lama Anda tetap aman.');
                }

                $b->forceFill([
                    'date' => $start->toDateString(),
                    'start_time' => $space->price_unit === 'jam' ? $start->format('H:i') : null,
                    'starts_at' => $start, 'end_at' => $end,
                    'reschedule_count' => $b->reschedule_count + 1,
                    'reminded_week_at' => null, 'reminded_day_at' => null,
                ])->save();

                return $b;
            });
        } finally {
            $lock->release();
        }

        $this->notifyOwner($result, 'Jadwal booking diubah', ["{$result->name} memindahkan jadwal booking."], 'Lihat Booking', route('seller.bookings.show', $result));
        $this->notifyBuyer($result, 'Jadwal berhasil diubah', ['Jadwal baru Anda sudah terkonfirmasi.']);

        return $result;
    }

    // ---------- Internal ----------

    private function markCancelled(Booking $b, string $actor, ?string $reason): void
    {
        $b->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $actor,
            'cancel_reason' => $reason,
        ])->save();
    }

    private function locked(Booking $booking, \Closure $fn): Booking
    {
        $result = DB::transaction(function () use ($booking, $fn) {
            $b = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            return $fn($b);
        });
        $booking->setRawAttributes($result->getAttributes(), true);

        return $booking;
    }

    /** Batalkan transaksi yang masih terbuka di gateway agar tidak bisa dibayar setelah booking mati. */
    private function releaseGatewayTransaction(Booking $b): void
    {
        if ($b->order_id && $b->payment_status === 'unpaid') {
            try {
                app(MidtransService::class)->cancelTransaction($b->order_id);
            } catch (\Throwable $e) {
                Log::info('Midtrans cancel skipped: '.$e->getMessage());
            }
        }
    }

    /** @param list<string> $lines */
    public function notifyBuyer(Booking $b, string $title, array $lines, ?string $label = 'Lihat Booking', ?string $url = null): void
    {
        $this->send($b->email, $b, $title, $lines, $label, $url);
    }

    /** @param list<string> $lines */
    public function notifyOwner(Booking $b, string $title, array $lines, ?string $label = 'Lihat Booking', ?string $url = null): void
    {
        $email = $b->space?->owner?->email;
        if ($email) {
            $this->send($email, $b, $title, $lines, $label, $url);
        }
    }

    /** @param list<string> $lines */
    private function send(string $to, Booking $b, string $title, array $lines, ?string $label, ?string $url): void
    {
        try {
            Mail::to($to)->send(new BookingNotice($b->loadMissing('space'), $title, $lines, $label, $url));
        } catch (\Throwable $e) {
            Log::warning('Booking notice mail failed: '.$e->getMessage());
        }
    }
}
