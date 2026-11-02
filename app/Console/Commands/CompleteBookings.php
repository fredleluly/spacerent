<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class CompleteBookings extends Command
{
    protected $signature = 'bookings:complete';

    protected $description = 'Tandai booking confirmed & lunas yang sudah selesai sebagai completed';

    public function handle(): int
    {
        $n = Booking::where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->where('end_at', '<', now())
            ->update(['status' => 'completed']);

        $this->info("{$n} booking selesai.");

        return self::SUCCESS;
    }
}
