<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Space;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(Request $request, Space $space, AvailabilityService $availability)
    {
        abort_unless($space->is_available, 404);

        $u = $request->user();
        $request->mergeIfMissing(array_filter(['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone]));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{9,20}$/'],
            'company' => ['nullable', 'string', 'max:100'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => [$space->price_unit === 'jam' ? 'required' : 'nullable', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'min:1', 'max:'.($space->price_unit === 'jam' ? 12 : 31)],
            'guests' => ['required', 'integer', 'min:1', 'max:'.$space->capacity],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'guests.max' => "Kapasitas maksimal ruangan ini {$space->capacity} orang.",
            'date.after_or_equal' => 'Tanggal tidak boleh sebelum hari ini.',
        ]);

        [$start, $end] = $availability->range($space, $data['date'], $data['start_time'] ?? null, (int) $data['duration']);

        $booking = DB::transaction(function () use ($request, $space, $availability, $start, $end, $data) {
            Space::whereKey($space->id)->lockForUpdate()->first();

            if (! $availability->isAvailable($space, $start, $end)) {
                return null;
            }

            return Booking::create($data + [
                'user_id' => $request->user()->id,
                'code' => Booking::generateCode(),
                'space_id' => $space->id,
                'total' => $space->price * $data['duration'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'starts_at' => $start,
                'end_at' => $end,
                'expires_at' => now()->addMinutes((int) config('midtrans.expiry_minutes', 30)),
            ]);
        });

        if (! $booking) {
            return back()->withInput()->withErrors([
                'date' => 'Slot sudah terisi pada waktu yang Anda pilih. Silakan pilih tanggal atau jam lain.',
            ]);
        }

        return redirect()->route('booking.show', $booking);
    }

    public function availability(Request $request, Space $space, AvailabilityService $availability)
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $from = Carbon::parse($request->query('date'))->startOfDay();
        // Daily/monthly spaces: look ahead 62 days so users see upcoming blocked periods.
        $to = $space->price_unit === 'jam' ? $from->copy()->addDay() : $from->copy()->addDays(62);

        return response()->json([
            'unit' => $space->price_unit,
            'taken' => $availability->takenRanges($space, $from, $to)->values(),
        ]);
    }

    public function show(Booking $booking)
    {
        $user = request()->user();
        $booking->load('space');

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
}
