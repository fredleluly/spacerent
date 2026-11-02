<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
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

    /** Persentase refund berdasarkan jam tersisa sebelum sewa. */
    public static function refundPercent(Booking $booking): int
    {
        $start = $booking->starts_at
            ? Carbon::parse($booking->starts_at)
            : Carbon::parse($booking->date->toDateString().' '.($booking->start_time ?: '00:00'));
        $hours = now()->diffInHours($start, false);

        return $hours >= 48 ? 100 : ($hours >= 24 ? 50 : 0);
    }

    public function index(Request $request)
    {
        $bookings = Booking::with('space')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$v}%")
                ->orWhere('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.bookings.index', ['bookings' => $bookings, 'statuses' => self::STATUSES, 'paymentStatuses' => self::PAYMENT_STATUSES]);
    }

    public function show(Booking $booking)
    {
        return view('admin.bookings.show', [
            'booking' => $booking->load('space'),
            'statuses' => self::STATUSES,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'refundPercent' => self::refundPercent($booking),
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
        ]);

        $booking->update($data);

        return back()->with('success', 'Status booking diperbarui.');
    }

    public function cancel(Booking $booking)
    {
        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return back()->with('error', 'Booking dengan status ini tidak bisa dibatalkan.');
        }

        $booking->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', $booking->payment_status === 'paid'
            ? 'Booking dibatalkan. Booking sudah dibayar: perlu refund manual.'
            : 'Booking dibatalkan.');
    }

    public function refund(Booking $booking)
    {
        if ($booking->payment_status !== 'paid' || $booking->status !== 'cancelled') {
            return back()->with('error', 'Hanya booking lunas yang sudah dibatalkan yang bisa ditandai refunded.');
        }

        $booking->forceFill(['payment_status' => 'refunded'])->save();

        return back()->with('success', 'Booking ditandai sudah dikembalikan (refunded).');
    }

    public function complete(Booking $booking)
    {
        if ($booking->status !== 'confirmed' || $booking->payment_status !== 'paid') {
            return back()->with('error', 'Hanya booking terkonfirmasi dan lunas yang bisa diselesaikan.');
        }

        $booking->forceFill(['status' => 'completed'])->save();

        return back()->with('success', 'Booking ditandai selesai.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking berhasil dihapus.');
    }
}
