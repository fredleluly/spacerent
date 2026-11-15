<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\Carbon;

/**
 * Kebijakan batal ala Airbnb dasar. Tiap tier = [minimal jam sebelum mulai, persen refund].
 * Biaya layanan hanya dikembalikan bila refund 100%.
 */
class RefundPolicy
{
    public const POLICIES = [
        'flexible' => [
            'label' => 'Fleksibel',
            'summary' => 'Refund penuh sampai 24 jam sebelum jadwal.',
            'tiers' => [[24, 100]],
        ],
        'moderate' => [
            'label' => 'Moderat',
            'summary' => 'Refund penuh sampai 48 jam sebelum jadwal, 50% sampai 24 jam sebelumnya.',
            'tiers' => [[48, 100], [24, 50]],
        ],
        'strict' => [
            'label' => 'Ketat',
            'summary' => 'Refund penuh sampai 7 hari sebelum jadwal, 50% sampai 3 hari sebelumnya.',
            'tiers' => [[168, 100], [72, 50]],
        ],
    ];

    public static function label(string $policy): string
    {
        return self::POLICIES[$policy]['label'] ?? $policy;
    }

    public static function summary(string $policy): string
    {
        return self::POLICIES[$policy]['summary'] ?? '';
    }

    /** Persentase refund bila pembeli membatalkan sekarang. */
    public static function percent(Booking $booking, ?Carbon $at = null): int
    {
        $start = $booking->starts_at
            ? Carbon::parse($booking->starts_at)
            : Carbon::parse($booking->date->toDateString().' '.($booking->start_time ?: '00:00'));
        $hours = ($at ?? now())->floatDiffInHours($start, false);
        $tiers = self::POLICIES[$booking->cancellation_policy]['tiers'] ?? self::POLICIES['moderate']['tiers'];

        foreach ($tiers as [$minHours, $percent]) {
            if ($hours >= $minHours) {
                return $percent;
            }
        }

        return 0;
    }

    /**
     * Nominal refund. Pembatalan oleh penjual/admin/sistem selalu 100% dari total.
     *
     * @return array{percent:int, amount:int}
     */
    public static function quote(Booking $booking, string $actor = 'buyer'): array
    {
        if ($booking->payment_status !== 'paid') {
            return ['percent' => 0, 'amount' => 0];
        }
        if ($actor !== 'buyer') {
            return ['percent' => 100, 'amount' => (int) $booking->total];
        }

        $percent = self::percent($booking);
        $base = $booking->subtotal > 0 ? $booking->subtotal + $booking->cleaning_fee : (int) $booking->total;
        $amount = (int) round($base * $percent / 100) + ($percent === 100 ? (int) $booking->service_fee : 0);

        return ['percent' => $percent, 'amount' => $amount];
    }

    public static function amount(Booking $booking): int
    {
        return self::quote($booking)['amount'];
    }
}
