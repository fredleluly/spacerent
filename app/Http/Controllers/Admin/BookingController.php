<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with('space')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->query('refund'), fn ($q) => $q->where('refund_status', 'pending'))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$v}%")
                ->orWhere('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'statuses' => Booking::STATUSES,
            'paymentStatuses' => Booking::PAYMENT_STATUSES,
            'refundPending' => Booking::where('refund_status', 'pending')->count(),
        ]);
    }

    public function show(Booking $booking)
    {
        return view('admin.bookings.show', [
            'booking' => $booking->load(['space', 'review']),
            'statuses' => Booking::STATUSES,
            'paymentStatuses' => Booking::PAYMENT_STATUSES,
        ]);
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->run(
            fn () => $bookings->cancel($booking, 'admin', $data['reason'] ?? null),
            $booking->payment_status === 'paid' ? 'Booking dibatalkan. Refund penuh menunggu diproses.' : 'Booking dibatalkan.'
        );
    }

    /** Tandai refund sudah dikirim (manual) atau jalankan lewat API Midtrans bila `via=gateway`. */
    public function refund(Request $request, Booking $booking, BookingService $bookings, MidtransService $midtrans)
    {
        if ($request->input('via') === 'gateway') {
            try {
                $midtrans->refund($booking);
            } catch (\Throwable $e) {
                return back()->with('error', 'Refund via Midtrans gagal: '.$e->getMessage().' Anda bisa menandainya manual setelah mengirim dana.');
            }
        }

        return $this->run(fn () => $bookings->markRefunded($booking), 'Refund ditandai selesai.');
    }

    public function complete(Booking $booking, BookingService $bookings)
    {
        return $this->run(fn () => $bookings->complete($booking), 'Booking ditandai selesai.');
    }

    public function noShow(Booking $booking, BookingService $bookings)
    {
        return $this->run(fn () => $bookings->markNoShow($booking), 'Booking ditandai tidak hadir.');
    }

    /** Hanya booking yang tidak pernah menerima uang yang boleh dihapus (soft delete). */
    public function destroy(Booking $booking)
    {
        if ($booking->paid_at !== null || in_array($booking->payment_status, ['paid', 'refund_pending', 'refunded'], true) || $booking->isActive()) {
            return back()->with('error', 'Booking aktif atau yang pernah dibayar tidak boleh dihapus. Batalkan dan selesaikan refund lebih dulu.');
        }

        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking diarsipkan.');
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
