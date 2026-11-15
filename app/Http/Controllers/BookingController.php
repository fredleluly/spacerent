<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Space;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, Space $space, BookingService $bookings)
    {
        abort_unless($space->is_available, 404);

        $u = $request->user();
        $request->mergeIfMissing(array_filter(['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone]));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{9,20}$/'],
            'company' => ['nullable', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => [$space->price_unit === 'jam' ? 'required' : 'nullable', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'min:'.max(1, (int) $space->min_duration), 'max:'.$space->effectiveMaxDuration()],
            'guests' => ['required', 'integer', 'min:1', 'max:'.$space->capacity],
            'notes' => ['nullable', 'string', 'max:500'],
            'agree' => ['accepted'],
        ], [
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'guests.max' => "Kapasitas maksimal ruangan ini {$space->capacity} orang.",
            'duration.min' => "Durasi minimal {$space->min_duration} {$space->unit_label}.",
            'duration.max' => "Durasi maksimal {$space->effectiveMaxDuration()} {$space->unit_label}.",
            'date.date_format' => 'Pilih tanggal yang valid.',
            'agree.accepted' => 'Setujui syarat dan kebijakan pembatalan untuk melanjutkan.',
        ]);

        try {
            $booking = $bookings->create($u, $space, $data);
        } catch (BookingException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('booking.show', $booking);
    }

    /** Ketersediaan: slot per hari (ruangan per jam) atau tanggal yang tertutup (harian/bulanan). */
    public function availability(Request $request, Space $space, AvailabilityService $availability)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:400'],
        ]);

        $day = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        $duration = (int) $request->query('duration', max(1, (int) $space->min_duration));
        $window = min(max(1, (int) $space->max_advance_days), 400);
        $isHour = $space->price_unit === 'jam';
        $to = $isHour ? $day->copy()->addDay() : $day->copy()->addDays(62);

        return response()->json([
            'unit' => $space->price_unit,
            'open_time' => $space->open_time,
            'close_time' => $space->close_time,
            'open_days' => $space->openDays(),
            'min_duration' => max(1, (int) $space->min_duration),
            'max_duration' => $space->effectiveMaxDuration(),
            'lead_time_hours' => (int) $space->lead_time_hours,
            'max_advance_days' => $window,
            'taken' => $availability->takenRanges($space, $day, $to)->values(),
            'slots' => $isHour ? $availability->slotsForDay($space, $day, $duration) : [],
            'blocked' => $availability->blockedDates($space, today(), today()->addDays($window), ! $isHour),
        ]);
    }

    public function show(Booking $booking)
    {
        $user = request()->user();
        $booking->load(['space.owner', 'review.photos']);

        abort_unless(
            $user && (
                $booking->user_id === $user->id
                || $user->isAdmin()
                || ($user->isSeller() && $booking->space && $booking->space->user_id === $user->id)
            ),
            403
        );

        return view('booking.show', ['booking' => $booking]);
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        try {
            $bookings->cancel($booking, 'buyer', $data['reason'] ?? null);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $booking->refund_amount > 0
            ? 'Booking dibatalkan. Refund Rp '.number_format($booking->refund_amount, 0, ',', '.').' sedang diproses.'
            : 'Booking dibatalkan.');
    }

    public function reschedule(Request $request, Booking $booking, BookingService $bookings)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => [$booking->space->price_unit === 'jam' ? 'required' : 'nullable', 'date_format:H:i'],
        ]);

        try {
            $bookings->reschedule($booking, $data['date'], $data['start_time'] ?? null);
        } catch (BookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jadwal berhasil diubah.');
    }
}
