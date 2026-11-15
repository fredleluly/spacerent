<?php

namespace App\Console\Commands;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class CompleteBookings extends Command
{
    protected $signature = 'bookings:complete';

    protected $description = 'Tandai booking confirmed & lunas yang sudah lewat sebagai completed dan minta ulasan';

    public function handle(BookingService $bookings): int
    {
        $n = 0;

        Booking::where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->where('end_at', '<', now())
            ->each(function (Booking $b) use ($bookings, &$n) {
                try {
                    $bookings->complete($b);
                    $n++;
                } catch (BookingException) {
                    // dilewati; dicoba lagi pada putaran berikutnya
                }
            });

        $this->info("{$n} booking selesai.");

        return self::SUCCESS;
    }
}
