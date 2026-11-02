<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request, MidtransService $midtrans): JsonResponse
    {
        $payload = $request->all();

        if (! $midtrans->verifySignature($payload)) {
            Log::warning('Midtrans webhook: invalid signature', ['order_id' => $payload['order_id'] ?? null]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $booking = Booking::where('order_id', $payload['order_id'])->first();
        if (! $booking) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ((int) round((float) $payload['gross_amount']) !== (int) $booking->total) {
            Log::error('Midtrans webhook: amount mismatch', ['order_id' => $payload['order_id'], 'gross' => $payload['gross_amount'], 'total' => $booking->total]);

            return response()->json(['message' => 'Amount mismatch'], 400);
        }

        $midtrans->applyNotification($booking, $payload);

        return response()->json(['message' => 'OK']);
    }
}
