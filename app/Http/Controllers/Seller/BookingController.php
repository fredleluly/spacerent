<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;

class BookingController extends Controller
{
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

        return view('seller.bookings.index', [
            'bookings' => $bookings,
            'statuses' => Booking::STATUSES,
            'paymentStatuses' => Booking::PAYMENT_STATUSES,
        ]);
    }

    public function show(Booking $booking)
    {
        $this->own($booking);

        return view('seller.bookings.show', [
            'booking' => $booking->load(['space', 'review.photos']),
            'paymentStatuses' => Booking::PAYMENT_STATUSES,
            'refundPercent' => 100,
            'refundAmount' => RefundPolicy::quote($booking, 'seller')['amount'],
        ]);
    }

    public function approve(Booking $booking, BookingService $bookings)
    {
        return $this->run(fn () => $bookings->approve($this->own($booking)), 'Permintaan disetujui. Pembeli diminta membayar.');
    }

    public function reject(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->run(fn () => $bookings->reject($this->own($booking), $data['reason'] ?? null), 'Permintaan ditolak.');
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $paid = $booking->payment_status === 'paid';

        return $this->run(
            fn () => $bookings->cancel($this->own($booking), 'seller', $data['reason'] ?? null),
            $paid ? 'Booking dibatalkan. Pembeli akan menerima refund penuh.' : 'Booking dibatalkan.'
        );
    }

    public function complete(Booking $booking, BookingService $bookings)
    {
        return $this->run(fn () => $bookings->complete($this->own($booking)), 'Booking ditandai selesai.');
    }

    public function noShow(Booking $booking, BookingService $bookings)
    {
        return $this->run(fn () => $bookings->markNoShow($this->own($booking)), 'Booking ditandai tidak hadir.');
    }

    private function run(\Closure $action, string $success)
    {
        try {
            $action();
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
