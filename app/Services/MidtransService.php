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
            'item_details' => $this->itemDetails($booking, $space?->name ?? 'Sewa Ruangan'),
            'expiry' => ['unit' => 'minute', 'duration' => $minutes],
        ];

        $token = Snap::getSnapToken($params);

        $booking->forceFill(['order_id' => $orderId, 'snap_token' => $token])->save();

        return $token;
    }

    /** Rincian item; jumlahnya harus sama dengan gross_amount. Data lama tanpa rincian jadi satu item. */
    private function itemDetails(Booking $booking, string $name): array
    {
        $total = (int) $booking->total;
        $sub = (int) $booking->subtotal;
        $clean = (int) $booking->cleaning_fee;
        $service = (int) $booking->service_fee;

        if ($sub <= 0 || $sub + $clean + $service !== $total) {
            return [['id' => 'space-'.$booking->space_id, 'price' => $total, 'quantity' => 1, 'name' => mb_substr($name, 0, 50)]];
        }

        $items = [['id' => 'space-'.$booking->space_id, 'price' => $sub, 'quantity' => 1, 'name' => mb_substr('Sewa '.$name, 0, 50)]];
        if ($clean > 0) {
            $items[] = ['id' => 'cleaning', 'price' => $clean, 'quantity' => 1, 'name' => 'Biaya kebersihan'];
        }
        if ($service > 0) {
            $items[] = ['id' => 'service', 'price' => $service, 'quantity' => 1, 'name' => 'Biaya layanan'];
        }

        return $items;
    }

    /** Batalkan transaksi terbuka di gateway (best effort, dipanggil saat booking dibatalkan). */
    public function cancelTransaction(string $orderId): void
    {
        \Midtrans\Transaction::cancel($orderId);
    }

    /** Refund via API Midtrans (sandbox/produksi). Melempar exception bila gateway menolak. */
    public function refund(Booking $booking): void
    {
        \Midtrans\Transaction::refund($booking->order_id, [
            'refund_key' => $booking->code.'-refund',
            'amount' => (int) $booking->refund_amount,
            'reason' => 'Pembatalan booking '.$booking->code,
        ]);
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
        $map = $this->mapStatus($payload['transaction_status'] ?? null, $payload['fraud_status'] ?? null, $payload['payment_type'] ?? null);
        $bookings = app(BookingService::class);

        // Simpan jejak notifikasi terakhir (audit) tanpa mengubah status.
        $booking->forceFill(array_filter([
            'midtrans_payload' => $payload,
            'payment_type' => $payload['payment_type'] ?? null,
        ]))->save();

        if ($map['payment_status'] === 'paid') {
            $paidAt = ! empty($payload['settlement_time']) ? Carbon::parse($payload['settlement_time'], 'Asia/Jakarta') : now();

            return $bookings->settlePayment($booking, $paidAt, $payload['payment_type'] ?? null) === 'confirmed';
        }

        if (in_array($map['payment_status'], ['failed', 'expired'], true)) {
            $bookings->failPayment($booking, $map['payment_status']);
        }

        return false;
    }
}
