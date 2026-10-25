<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Space;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, Space $space)
    {
        abort_unless($space->is_available, 404);

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

        $booking = Booking::create($data + [
            'code' => Booking::generateCode(),
            'space_id' => $space->id,
            'total' => $space->price * $data['duration'],
        ]);

        return redirect()->route('booking.show', $booking);
    }

    public function show(Booking $booking)
    {
        return view('booking.show', ['booking' => $booking->load('space')]);
    }
}
