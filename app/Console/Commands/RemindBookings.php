<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class RemindBookings extends Command
{
    protected $signature = 'bookings:remind';

    protected $description = 'Kirim pengingat H-7 dan H-1 untuk booking lunas yang akan datang (masing-masing sekali)';

    public function handle(BookingService $bookings): int
    {
        $week = 0;
        $day = 0;

        Booking::with('space.owner')->where('status', 'confirmed')->where('payment_status', 'paid')
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->whereNull('reminded_week_at')
            ->where('starts_at', '>', now()->addDay())
            ->each(function (Booking $b) use ($bookings, &$week) {
                $b->forceFill(['reminded_week_at' => now()])->save();
                $bookings->notifyBuyer($b, 'Pengingat: jadwal 7 hari lagi', ['Jadwal sewa Anda semakin dekat. Pastikan semua sudah siap.']);
                $week++;
            });

        Booking::with('space.owner')->where('status', 'confirmed')->where('payment_status', 'paid')
            ->whereBetween('starts_at', [now(), now()->addDay()])
            ->whereNull('reminded_day_at')
            ->each(function (Booking $b) use ($bookings, &$day) {
                $b->forceFill(['reminded_day_at' => now()])->save();
                $info = $b->space->checkin_info ? ['Info check-in: '.$b->space->checkin_info] : [];
                $bookings->notifyBuyer($b, 'Pengingat: jadwal besok', ['Jadwal sewa Anda dimulai dalam 24 jam.', ...$info]);
                $bookings->notifyOwner($b, 'Pengingat: tamu datang besok', ["{$b->name} akan memakai ruangan dalam 24 jam."]);
                $day++;
            });

        $this->info("Pengingat H-7: {$week}, H-1: {$day}.");

        return self::SUCCESS;
    }
}
