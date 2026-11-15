<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function pay(Request $request, Booking $booking, MidtransService $midtrans): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        if ($booking->payment_status === 'paid') {
            return response()->json(['message' => 'Booking sudah dibayar.'], 409);
        }

        if ($booking->status === 'awaiting_approval') {
            return response()->json(['message' => 'Booking ini menunggu persetujuan pemilik. Anda bisa membayar setelah disetujui.'], 422);
        }

        if (! $booking->isPayable()) {
            return response()->json(['message' => 'Booking tidak dapat dibayar (kedaluwarsa atau dibatalkan).'], 422);
        }

        try {
            $token = $midtrans->createSnapToken($booking);
        } catch (\Throwable $e) {
            Log::error('Midtrans snap token failed: '.$e->getMessage());

            return response()->json(['message' => 'Gagal menghubungi payment gateway. Coba lagi.'], 502);
        }

        return response()->json(['snap_token' => $token, 'client_key' => config('midtrans.client_key')]);
    }

    public function finish(Request $request, Booking $booking, MidtransService $midtrans): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        // Sinkronisasi opsional; sumber kebenaran tetap webhook.
        if ($booking->order_id && $booking->payment_status !== 'paid') {
            try {
                $status = \Midtrans\Transaction::status($booking->order_id);
                $payload = json_decode(json_encode($status), true);
                if (is_array($payload) && (int) round((float) ($payload['gross_amount'] ?? 0)) === (int) $booking->total) {
                    $midtrans->applyNotification($booking, $payload);
                }
            } catch (\Throwable $e) {
                Log::info('Midtrans status sync skipped: '.$e->getMessage());
            }
        }

        $booking->refresh();

        $flash = match (true) {
            $booking->payment_status === 'paid' => ['success', 'Pembayaran berhasil. Booking Anda telah dikonfirmasi.'],
            $booking->payment_status === 'refund_pending' => ['error', 'Pembayaran diterima tetapi jadwal sudah tidak tersedia. Dana Anda akan dikembalikan penuh.'],
            in_array($booking->payment_status, ['failed', 'expired'], true) => ['error', 'Pembayaran gagal atau kedaluwarsa.'],
            default => ['info', 'Pembayaran sedang diproses. Halaman akan diperbarui setelah pembayaran terkonfirmasi.'],
        };

        return redirect()->route('booking.show', $booking)->with('payment_flash', ['type' => $flash[0], 'message' => $flash[1]]);
    }
}
