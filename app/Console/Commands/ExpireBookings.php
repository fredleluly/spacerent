<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Batalkan booking pending yang belum dibayar dan sudah melewati batas waktu';

    public function handle(): int
    {
        $n = Booking::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '<', now())
            ->update(['status' => 'cancelled', 'payment_status' => 'expired']);

        $this->info("{$n} booking kedaluwarsa.");

        return self::SUCCESS;
    }
}
