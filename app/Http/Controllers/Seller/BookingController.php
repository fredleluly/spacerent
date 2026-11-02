<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public const STATUSES = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Belum Dibayar',
        'paid' => 'Lunas',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'refunded' => 'Dikembalikan',
    ];

    private function own(Booking $booking): Booking
    {
        abort_unless($booking->space && $booking->space->user_id === auth()->id(), 403);

        return $booking;
    }

    public function index(Request $request)
    {
        $bookings = Booking::with('space')
            ->whereHas('space', fn ($q) => $q->where('user_id', auth()->id()))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$v}%")
                ->orWhere('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('seller.bookings.index', ['bookings' => $bookings, 'statuses' => self::STATUSES, 'paymentStatuses' => self::PAYMENT_STATUSES]);
    }

    public function show(Booking $booking)
    {
        $this->own($booking);

        return view('seller.bookings.show', [
            'booking' => $booking->load('space'),
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'refundPercent' => RefundPolicy::percent($booking),
            'refundAmount' => RefundPolicy::amount($booking),
        ]);
    }

    public function cancel(Booking $booking)
    {
        $this->own($booking);

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return back()->with('error', 'Booking dengan status ini tidak bisa dibatalkan.');
        }

        $booking->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', $booking->payment_status === 'paid'
            ? 'Booking dibatalkan. Refund diproses oleh admin SpaceRent.'
            : 'Booking dibatalkan.');
    }

    public function complete(Booking $booking)
    {
        $this->own($booking);

        if ($booking->status !== 'confirmed' || $booking->payment_status !== 'paid') {
            return back()->with('error', 'Hanya booking terkonfirmasi dan lunas yang bisa diselesaikan.');
        }

        $booking->forceFill(['status' => 'completed'])->save();

        return back()->with('success', 'Booking ditandai selesai.');
    }
}
