<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? Carbon::parse($request->query('month').'-01')->startOfMonth()
            : now()->startOfMonth();

        $spaces = auth()->user()->spaces()->orderBy('name')->get();
        $selected = $spaces->firstWhere('id', (int) $request->query('space'));

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $bookings = Booking::with('space')
            ->whereHas('space', fn ($q) => $q->where('user_id', auth()->id())->when($selected, fn ($s) => $s->whereKey($selected->id)))
            ->whereIn('status', ['awaiting_approval', 'pending', 'confirmed', 'completed'])
            ->where('starts_at', '<=', $gridEnd)->where('end_at', '>=', $gridStart)
            ->get();

        $blackouts = \App\Models\SpaceBlackout::with('space')
            ->whereHas('space', fn ($q) => $q->where('user_id', auth()->id())->when($selected, fn ($s) => $s->whereKey($selected->id)))
            ->whereDate('starts_on', '<=', $gridEnd)->whereDate('ends_on', '>=', $gridStart)->get();

        $byDay = [];
        foreach ($bookings as $b) {
            for ($d = $b->starts_at->copy()->startOfDay(); $d->lt($b->end_at); $d->addDay()) {
                if ($d->between($gridStart, $gridEnd)) {
                    $byDay[$d->toDateString()][] = [
                        'type' => $b->status,
                        'label' => ($b->start_time ? substr($b->start_time, 0, 5).' ' : '').$b->name,
                        'space' => $b->space->name,
                        'url' => route('seller.bookings.show', $b),
                    ];
                }
            }
        }
        foreach ($blackouts as $bo) {
            for ($d = $bo->starts_on->copy(); $d->lte($bo->ends_on); $d->addDay()) {
                if ($d->between($gridStart, $gridEnd)) {
                    $byDay[$d->toDateString()][] = ['type' => 'blocked', 'label' => $bo->reason ?: 'Ditutup', 'space' => $bo->space->name, 'url' => null];
                }
            }
        }

        return view('seller.calendar', [
            'month' => $month, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd,
            'byDay' => $byDay, 'spaces' => $spaces, 'selected' => $selected,
            'blackouts' => \App\Models\SpaceBlackout::with('space')
                ->whereHas('space', fn ($q) => $q->where('user_id', auth()->id()))
                ->whereDate('ends_on', '>=', today())->orderBy('starts_on')->get(),
        ]);
    }
}
