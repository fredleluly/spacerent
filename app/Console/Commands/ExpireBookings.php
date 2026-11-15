<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Batalkan booking yang tidak dibayar atau tidak disetujui sebelum tenggat';

    public function handle(BookingService $bookings): int
    {
        $unpaid = Booking::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('expires_at', '<', now())
            ->update([
                'status' => 'cancelled', 'payment_status' => 'expired',
                'cancelled_at' => now(), 'cancelled_by' => 'system', 'cancel_reason' => 'Batas waktu pembayaran habis',
            ]);

        $ignored = 0;
        Booking::with('space.owner')->where('status', 'awaiting_approval')
            ->where('approval_deadline_at', '<', now())
            ->each(function (Booking $b) use ($bookings, &$ignored) {
                $b->forceFill([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'system',
                    'cancel_reason' => 'Pemilik tidak merespons dalam batas waktu',
                ])->save();
                $bookings->notifyBuyer($b, 'Permintaan tidak direspons', ['Pemilik tidak merespons dalam batas waktu, jadi permintaan dibatalkan otomatis. Anda tidak dikenai biaya.'], 'Cari Ruangan Lain', route('home'));
                $ignored++;
            });

        $this->info("{$unpaid} booking kedaluwarsa, {$ignored} permintaan tanpa respons.");

        return self::SUCCESS;
    }
}
