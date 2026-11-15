<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Space;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aturan slot:
 * - 'jam'   : start = tanggal + jam mulai, end = start + N jam (harus dalam jam buka, sejajar grid 30 menit)
 * - 'hari'  : start = tanggal 00:00, end = start + N hari
 * - 'bulan' : start = tanggal 00:00, end = start + N bulan kalender (tanpa overflow)
 * Slot dipegang booking confirmed/completed, pending belum expire, atau menunggu persetujuan belum lewat tenggat.
 * Bentrok: existing.starts_at < new.end AND existing.end_at > new.starts_at (+ buffer). Back-to-back boleh.
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

    /**
     * Validasi aturan ruangan (jam buka, lead time, jendela maksimum, durasi, blokir).
     * Mengembalikan pesan error atau null bila lolos. Bentrok dengan booking lain dicek terpisah.
     */
    public function validate(Space $space, CarbonInterface $start, CarbonInterface $end, int $duration): ?string
    {
        $min = max(1, (int) $space->min_duration);
        $max = $space->effectiveMaxDuration();
        if ($duration < $min || $duration > $max) {
            return "Durasi sewa harus antara {$min} dan {$max} {$space->unit_label}.";
        }

        $lead = (int) $space->lead_time_hours;
        if ($start->lt(now()->addHours($lead))) {
            return $lead > 0
                ? "Pemesanan minimal {$lead} jam sebelum jadwal mulai."
                : 'Jadwal yang dipilih sudah lewat.';
        }

        $window = (int) $space->max_advance_days;
        if ($window > 0 && $start->gt(now()->addDays($window))) {
            return "Pemesanan hanya bisa dilakukan maksimal {$window} hari ke depan.";
        }

        if ($space->price_unit === 'jam') {
            $grid = (int) config('spacerent.slot_grid_minutes', 30);
            if (((int) $start->format('i')) % $grid !== 0) {
                return "Jam mulai harus kelipatan {$grid} menit.";
            }
            if (! $space->isOpenOnDay($start)) {
                return 'Ruangan tidak buka pada hari yang dipilih.';
            }
            $open = $start->copy()->setTimeFromTimeString($space->open_time);
            $close = $start->copy()->setTimeFromTimeString($space->close_time);
            if ($start->lt($open) || $end->gt($close)) {
                return "Jam operasional ruangan {$space->open_time} - {$space->close_time}. Pilih jam di dalam rentang tersebut.";
            }
        } elseif ($space->price_unit === 'hari') {
            for ($d = $start->copy()->startOfDay(); $d->lt($end); $d->addDay()) {
                if (! $space->isOpenOnDay($d)) {
                    return 'Rentang yang dipilih mencakup hari ketika ruangan tidak buka.';
                }
            }
        } elseif (! $space->isOpenOnDay($start)) {
            return 'Ruangan tidak buka pada tanggal mulai yang dipilih.';
        }

        if ($this->hitsBlackout($space, $start, $end)) {
            return 'Tanggal yang dipilih ditutup oleh pemilik ruangan.';
        }

        return null;
    }

    public function hitsBlackout(Space $space, CarbonInterface $start, CarbonInterface $end): bool
    {
        $firstDay = $start->copy()->startOfDay()->toDateString();
        $lastDay = $end->copy()->subSecond()->startOfDay()->toDateString();

        return $space->blackouts()
            ->whereDate('starts_on', '<=', $lastDay)
            ->whereDate('ends_on', '>=', $firstDay)
            ->exists();
    }

    public function isAvailable(Space $space, CarbonInterface $start, CarbonInterface $end, ?int $ignoreBookingId = null): bool
    {
        $buffer = $space->price_unit === 'jam' ? (int) $space->buffer_minutes : 0;

        return ! Booking::occupying()
            ->where('space_id', $space->id)
            ->when($ignoreBookingId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where('starts_at', '<', $end->copy()->addMinutes($buffer))
            ->where('end_at', '>', $start->copy()->subMinutes($buffer))
            ->exists();
    }

    /**
     * Slot jam pada satu hari untuk durasi tertentu.
     *
     * @return list<array{time:string, available:bool}>
     */
    public function slotsForDay(Space $space, CarbonInterface $day, int $duration): array
    {
        $day = Carbon::parse($day)->startOfDay();
        $grid = (int) config('spacerent.slot_grid_minutes', 30);
        $open = $day->copy()->setTimeFromTimeString($space->open_time);
        $close = $day->copy()->setTimeFromTimeString($space->close_time);
        $slots = [];

        for ($t = $open->copy(); $t->copy()->addHours($duration)->lte($close); $t->addMinutes($grid)) {
            $end = $t->copy()->addHours($duration);
            $ok = $this->validate($space, $t, $end, $duration) === null && $this->isAvailable($space, $t, $end);
            $slots[] = ['time' => $t->format('H:i'), 'available' => $ok];
        }

        return $slots;
    }

    /**
     * Tanggal yang tidak bisa dipilih (untuk kalender), pada ruangan harian/bulanan.
     *
     * @return list<string> Y-m-d
     */
    public function blockedDates(Space $space, CarbonInterface $from, CarbonInterface $to, bool $includeBookings = true): array
    {
        $blocked = collect();

        Booking::occupying()
            ->where('space_id', $space->id)
            ->when(! $includeBookings, fn ($q) => $q->whereRaw('1 = 0'))
            ->where('starts_at', '<', $to)->where('end_at', '>', $from)
            ->get(['starts_at', 'end_at'])
            ->each(function (Booking $b) use ($blocked, $from, $to) {
                for ($d = $b->starts_at->copy()->startOfDay(); $d->lt($b->end_at) && $d->lt($to); $d->addDay()) {
                    if ($d->gte($from->copy()->startOfDay())) {
                        $blocked->push($d->toDateString());
                    }
                }
            });

        $space->blackouts()
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->get()
            ->each(function ($b) use ($blocked, $from, $to) {
                for ($d = $b->starts_on->copy(); $d->lte($b->ends_on) && $d->lte($to); $d->addDay()) {
                    if ($d->gte($from->copy()->startOfDay())) {
                        $blocked->push($d->toDateString());
                    }
                }
            });

        return $blocked->unique()->sort()->values()->all();
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
