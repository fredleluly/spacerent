<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Space;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Slot rules:
 * - price_unit 'jam'  : start = date + start_time, end = start + N hours
 * - price_unit 'hari' : start = date 00:00,        end = start + N days
 * - price_unit 'bulan': start = date 00:00,        end = start + N calendar months
 * A booking occupies the slot when confirmed, or pending with expires_at in the future.
 * Overlap: existing.starts_at < new.end AND existing.end_at > new.starts_at
 * (back-to-back bookings are allowed).
 */
class AvailabilityService
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function range(Space $space, string|CarbonInterface $date, ?string $startTime, int $duration): array
    {
        $day = Carbon::parse($date)->startOfDay();

        if ($space->price_unit === 'jam') {
            $start = $day->copy()->setTimeFromTimeString($startTime ?: '00:00');

            return [$start, $start->copy()->addHours($duration)];
        }

        if ($space->price_unit === 'bulan') {
            return [$day, $day->copy()->addMonthsNoOverflow($duration)];
        }

        return [$day, $day->copy()->addDays($duration)];
    }

    public function isAvailable(Space $space, CarbonInterface $start, CarbonInterface $end): bool
    {
        return ! Booking::occupying()
            ->where('space_id', $space->id)
            ->where('starts_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }

    /** @return Collection<int, array{start: string, end: string}> */
    public function takenRanges(Space $space, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Booking::occupying()
            ->where('space_id', $space->id)
            ->where('starts_at', '<', $to)
            ->where('end_at', '>', $from)
            ->orderBy('starts_at')
            ->get(['starts_at', 'end_at'])
            ->map(fn (Booking $b) => [
                'start' => $b->starts_at->toIso8601String(),
                'end' => $b->end_at->toIso8601String(),
            ]);
    }
}
