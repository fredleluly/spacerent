<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo: aturan pemesanan per ruangan, pembeli, riwayat sewa selesai, dan ulasan nyata.
 * Rating ruangan dihitung dari ulasan (bukan angka statis).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Space::all() as $space) {
            $space->forceFill($this->rulesFor($space))->save();
        }

        $buyers = collect([
            ['Raka Pratama', 'raka@contoh.id'], ['Intan Wulandari', 'intan@contoh.id'],
            ['Bayu Nugroho', 'bayu@contoh.id'], ['Maya Kusuma', 'maya@contoh.id'], ['Dimas Santoso', 'dimas@contoh.id'],
        ])->map(fn ($b) => User::updateOrCreate(
            ['email' => $b[1]],
            ['name' => $b[0], 'phone' => '0812'.random_int(10000000, 99999999), 'password' => 'demo12345', 'role' => 'buyer'],
        ));

        $comments = [
            [5, 'Ruangannya bersih dan rapi, proyektor langsung jalan. Presentasi klien kami lancar.', false],
            [5, 'Lokasi mudah dijangkau, pemilik cepat membalas pesan. Akan pesan lagi bulan depan.', false],
            [4, 'Fasilitas sesuai deskripsi. AC agak dingin di jam siang, selebihnya nyaman.', false],
            [5, 'Akustik sangat bagus untuk rekaman. Teknisinya membantu sejak awal.', true],
            [4, 'Harga sepadan. Parkir sedikit penuh saat jam makan siang.', false],
            [3, 'Ruangan baik, tetapi wifi sempat putus sebentar saat video call.', true],
            [5, 'Cocok untuk workshop tim 20 orang. Kursi dan meja mudah ditata ulang.', false],
        ];

        $i = 0;
        $spacesToReview = Review::exists() ? collect() : Space::where('is_available', true)->orderBy('id')->take(6)->get();
        foreach ($spacesToReview as $space) {
            for ($n = 0; $n < 3; $n++) {
                $buyer = $buyers[($i + $n) % $buyers->count()];
                [$rating, $text, $anon] = $comments[($i * 3 + $n) % count($comments)];
                $start = now()->subDays(12 + $i * 9 + $n * 17)->setTime(10, 0);
                $booking = $this->booking($space, $buyer, $start, 'completed', 'paid');

                $review = Review::create([
                    'booking_id' => $booking->id, 'space_id' => $space->id, 'user_id' => $buyer->id,
                    'rating' => $rating, 'cleanliness' => min(5, $rating + ($n % 2)), 'accuracy' => $rating,
                    'location' => 5, 'facilities' => max(3, $rating - ($n % 2)), 'value' => max(3, $rating - 1),
                    'comment' => $text, 'is_anonymous' => $anon, 'status' => 'published',
                ]);
                if ($n === 0) {
                    $review->forceFill(['reply' => 'Terima kasih atas ulasannya! Senang bisa membantu, sampai bertemu lagi.', 'replied_at' => now()->subDays(2)])->save();
                }
            }
            $space->refreshRating();
            $i++;
        }

        // Satu booking mendatang yang sudah dibayar agar kalender penjual tidak kosong.
        $demo = User::where('email', 'pembeli@spacerent.id')->first();
        $jam = Space::where('price_unit', 'jam')->where('instant_book', true)->first();
        if ($demo && $jam && ! Booking::where('user_id', $demo->id)->where('status', 'confirmed')->exists()) {
            $this->booking($jam, $demo, now()->addDays(6)->setTime(13, 0), 'confirmed', 'paid');
        }
    }

    private function rulesFor(Space $s): array
    {
        $base = ['open_time' => '08:00', 'close_time' => '22:00', 'open_days' => null, 'min_duration' => 1, 'buffer_minutes' => 0,
            'lead_time_hours' => 2, 'max_advance_days' => 365, 'instant_book' => true, 'cancellation_policy' => 'moderate', 'cleaning_fee' => 0,
            'rules' => "Dilarang merokok di dalam ruangan.\nJaga kebersihan dan rapikan kursi setelah dipakai.\nSuara musik mengikuti batas yang disepakati dengan pemilik.",
            'checkin_info' => 'Lapor ke resepsionis lobi dan sebutkan kode booking. Petugas akan mengantar ke ruangan.'];

        return match (true) {
            in_array($s->category, ['meeting-room', 'studio-podcast'], true) => array_merge($base, ['buffer_minutes' => 30, 'cleaning_fee' => 25000, 'max_duration' => 8]),
            $s->category === 'coworking' && $s->price_unit === 'hari' => array_merge($base, ['open_time' => '07:00', 'close_time' => '21:00', 'open_days' => [1, 2, 3, 4, 5, 6], 'cancellation_policy' => 'flexible', 'max_duration' => 14]),
            $s->category === 'coworking' => array_merge($base, ['open_days' => [1, 2, 3, 4, 5, 6], 'cancellation_policy' => 'flexible', 'max_duration' => 12, 'lead_time_hours' => 24]),
            $s->category === 'event-space' && $s->price_unit === 'hari' => array_merge($base, ['instant_book' => false, 'cancellation_policy' => 'strict', 'cleaning_fee' => 1500000, 'max_duration' => 7, 'lead_time_hours' => 72]),
            $s->category === 'event-space' => array_merge($base, ['cancellation_policy' => 'strict', 'cleaning_fee' => 150000, 'min_duration' => 2, 'max_duration' => 10, 'buffer_minutes' => 60]),
            default => array_merge($base, ['cancellation_policy' => 'strict', 'lead_time_hours' => 48, 'max_duration' => 12, 'open_days' => [1, 2, 3, 4, 5]]),
        };
    }

    private function booking(Space $space, User $buyer, $start, string $status, string $pay): Booking
    {
        $duration = max(1, (int) $space->min_duration);
        if ($space->price_unit !== 'jam') {
            $start = $start->copy()->startOfDay();
        }
        $end = match ($space->price_unit) {
            'jam' => $start->copy()->addHours($duration),
            'bulan' => $start->copy()->addMonthsNoOverflow($duration),
            default => $start->copy()->addDays($duration),
        };
        $sub = (int) $space->price * $duration;
        $service = (int) round(($sub + $space->cleaning_fee) * (float) config('spacerent.service_fee_percent', 5) / 100);

        return Booking::forceCreate([
            'code' => Booking::generateCode(), 'user_id' => $buyer->id, 'space_id' => $space->id,
            'name' => $buyer->name, 'email' => $buyer->email, 'phone' => $buyer->phone ?: '081200000009',
            'date' => $start->toDateString(), 'start_time' => $space->price_unit === 'jam' ? $start->format('H:i') : null,
            'duration' => $duration, 'guests' => min(4, $space->capacity), 'subtotal' => $sub, 'cleaning_fee' => $space->cleaning_fee,
            'service_fee' => $service, 'total' => $sub + $space->cleaning_fee + $service, 'status' => $status, 'payment_status' => $pay,
            'cancellation_policy' => $space->cancellation_policy, 'starts_at' => $start, 'end_at' => $end,
            'paid_at' => $start->copy()->subDays(5), 'payment_type' => 'qris', 'expires_at' => $start->copy()->subDays(5),
        ]);
    }
}
