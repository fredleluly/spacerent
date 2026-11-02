<?php

namespace App\Services;

use App\Mail\BookingPaid;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = (string) config('midtrans.server_key');
        Config::$clientKey = (string) config('midtrans.client_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /** Buat (atau pakai ulang) Snap token untuk booking. */
    public function createSnapToken(Booking $booking): string
    {
        $expiresAt = $booking->expires_at ? Carbon::parse($booking->expires_at) : null;

        if ($booking->snap_token && $booking->order_id && (! $expiresAt || $expiresAt->isFuture())) {
            return $booking->snap_token;
        }

        $minutes = $expiresAt
            ? max(1, (int) ceil(now()->diffInSeconds($expiresAt, false) / 60))
            : (int) config('midtrans.expiry_minutes', 30);

        $orderId = $booking->code.'-'.now()->format('ymdHis').random_int(10, 99);
        $total = (int) $booking->total;
        $space = $booking->space;

        $params = [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $total],
            'customer_details' => [
                'first_name' => $booking->name,
                'email' => $booking->email,
                'phone' => $booking->phone,
            ],
            'item_details' => [[
                'id' => 'space-'.$booking->space_id,
                'price' => $total,
                'quantity' => 1,
                'name' => mb_substr($space?->name ?? 'Sewa Ruangan', 0, 50),
            ]],
            'expiry' => ['unit' => 'minute', 'duration' => $minutes],
        ];

        $token = Snap::getSnapToken($params);

        $booking->forceFill(['order_id' => $orderId, 'snap_token' => $token])->save();

        return $token;
    }

    public function verifySignature(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $k) {
            if (! isset($payload[$k])) {
                return false;
            }
        }
        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('midtrans.server_key'));

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    /** @return array{payment_status:string,status:?string}|null null = tidak ada perubahan (pending) */
    public function mapStatus(?string $transactionStatus, ?string $fraudStatus = null, ?string $paymentType = null): array
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge'
                ? ['payment_status' => 'unpaid', 'status' => null]
                : ($fraudStatus === 'deny'
                    ? ['payment_status' => 'failed', 'status' => 'cancelled']
                    : ['payment_status' => 'paid', 'status' => 'confirmed']),
            'settlement' => ['payment_status' => 'paid', 'status' => 'confirmed'],
            'expire' => ['payment_status' => 'expired', 'status' => 'cancelled'],
            'deny', 'cancel', 'failure' => ['payment_status' => 'failed', 'status' => 'cancelled'],
            default => ['payment_status' => 'unpaid', 'status' => null], // pending, authorize, dll
        };
    }

    /**
     * Terapkan notifikasi Midtrans secara idempoten. Mengembalikan true bila booking baru saja menjadi paid.
     */
    public function applyNotification(Booking $booking, array $payload): bool
    {
        $becamePaid = false;

        DB::transaction(function () use ($booking, $payload, &$becamePaid) {
            $locked = Booking::whereKey($booking->getKey())->lockForUpdate()->first();
            $map = $this->mapStatus($payload['transaction_status'] ?? null, $payload['fraud_status'] ?? null, $payload['payment_type'] ?? null);

            $update = ['midtrans_payload' => $payload];
            if (! empty($payload['payment_type'])) {
                $update['payment_type'] = $payload['payment_type'];
            }

            if ($locked->payment_status === 'paid' || $locked->payment_status === 'refunded') {
                // jangan pernah menurunkan status paid
            } elseif ($map['payment_status'] === 'paid') {
                $update += [
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'paid_at' => ! empty($payload['settlement_time']) ? Carbon::parse($payload['settlement_time'], 'Asia/Jakarta') : now(),
                ];
                $becamePaid = true;
            } elseif (in_array($map['payment_status'], ['failed', 'expired'], true)) {
                $update += ['payment_status' => $map['payment_status'], 'status' => 'cancelled'];
            }

            $locked->forceFill($update)->save();
        });

        $booking->refresh();

        if ($becamePaid) {
            try {
                Mail::to($booking->email)->send(new BookingPaid($booking));
            } catch (\Throwable $e) {
                Log::warning('BookingPaid mail failed: '.$e->getMessage());
            }
        }

        return $becamePaid;
    }
}
