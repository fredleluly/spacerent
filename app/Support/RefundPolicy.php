<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\Carbon;

class RefundPolicy
{
    /** Persentase refund berdasarkan jam tersisa sebelum sewa. */
    public static function percent(Booking $booking): int
    {
        $start = $booking->starts_at
            ? Carbon::parse($booking->starts_at)
            : Carbon::parse($booking->date->toDateString().' '.($booking->start_time ?: '00:00'));
        $hours = now()->diffInHours($start, false);

        return $hours >= 48 ? 100 : ($hours >= 24 ? 50 : 0);
    }

    public static function amount(Booking $booking): int
    {
        return (int) round($booking->total * self::percent($booking) / 100);
    }
}
