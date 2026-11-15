<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Space;
use App\Models\SpaceBlackout;
use Illuminate\Http\Request;

class BlackoutController extends Controller
{
    public function store(Request $request, Space $space)
    {
        abort_unless($space->user_id === auth()->id(), 403);

        $data = $request->validate([
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:150'],
        ], [
            'starts_on.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'ends_on.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
        ]);

        $from = \Carbon\Carbon::parse($data['starts_on'])->startOfDay();
        $to = \Carbon\Carbon::parse($data['ends_on'])->addDay()->startOfDay();

        $conflicts = Booking::where('space_id', $space->id)
            ->occupying()->where('starts_at', '<', $to)->where('end_at', '>', $from)->count();
        if ($conflicts > 0) {
            return back()->withErrors(['starts_on' => "Ada {$conflicts} booking aktif pada rentang tanggal ini. Batalkan atau selesaikan booking tersebut lebih dulu."])->withInput();
        }

        $space->blackouts()->create($data);

        return back()->with('success', 'Tanggal ditutup. Pembeli tidak bisa memesan pada rentang ini.');
    }

    public function destroy(SpaceBlackout $blackout)
    {
        abort_unless($blackout->space && $blackout->space->user_id === auth()->id(), 403);
        $blackout->delete();

        return back()->with('success', 'Penutupan dibuka kembali.');
    }
}
