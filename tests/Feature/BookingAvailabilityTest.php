<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'buyer']));
    }

    private function space(string $unit = 'jam'): Space
    {
        return Space::create([
            'name' => 'Test '.$unit, 'slug' => 'test-'.$unit, 'category' => 'meeting-room',
            'area' => 'Jakarta Pusat', 'district' => 'X', 'capacity' => 10, 'price' => 100000,
            'price_unit' => $unit, 'image' => 'x.jpg', 'description' => 'd', 'is_available' => true,
        ]);
    }

    private function payload(array $o = []): array
    {
        return $o + [
            'name' => 'Budi', 'email' => 'b@example.com', 'phone' => '081234567890',
            'date' => now()->addDays(3)->toDateString(), 'start_time' => '10:00',
            'duration' => 2, 'guests' => 2,
        ];
    }

    private function seedBooking(Space $s, string $status, $start, $end, $expires = null): Booking
    {
        return Booking::create([
            'code' => Booking::generateCode(), 'space_id' => $s->id, 'name' => 'A', 'email' => 'a@e.com',
            'phone' => '0812345678', 'date' => $start->toDateString(), 'duration' => 1, 'guests' => 1,
            'total' => 1, 'status' => $status, 'payment_status' => 'unpaid',
            'starts_at' => $start, 'end_at' => $end, 'expires_at' => $expires,
        ]);
    }

    public function test_hourly_conflict_rejected_and_adjacent_accepted(): void
    {
        $s = $this->space();
        $this->post(route('booking.store', $s), $this->payload())->assertRedirect();
        $this->assertSame(1, Booking::count());

        $this->post(route('booking.store', $s), $this->payload(['start_time' => '11:00']))
            ->assertSessionHasErrors('date');
        $this->assertSame(1, Booking::count());

        $this->post(route('booking.store', $s), $this->payload(['start_time' => '12:00']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());

        $b = Booking::first();
        $this->assertSame('pending', $b->status);
        $this->assertSame('unpaid', $b->payment_status);
        $this->assertTrue($b->isPayable());
        $this->assertSame(12, $b->end_at->hour);
    }

    public function test_daily_conflict_rejected(): void
    {
        $s = $this->space('hari');
        $d = now()->addDays(5);
        $this->post(route('booking.store', $s), $this->payload(['date' => $d->toDateString(), 'duration' => 3, 'start_time' => null]));
        $this->post(route('booking.store', $s), $this->payload(['date' => $d->copy()->addDays(2)->toDateString(), 'duration' => 1, 'start_time' => null]))
            ->assertSessionHasErrors('date');
        $this->post(route('booking.store', $s), $this->payload(['date' => $d->copy()->addDays(3)->toDateString(), 'duration' => 1, 'start_time' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());
    }

    public function test_expired_pending_frees_slot_and_command_cancels_it(): void
    {
        $s = $this->space();
        $start = now()->addDays(2)->setTime(10, 0);
        $b = $this->seedBooking($s, 'pending', $start, $start->copy()->addHours(2), now()->subMinute());

        $this->post(route('booking.store', $s), $this->payload(['date' => $start->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->artisan('bookings:expire')->assertSuccessful();
        $b->refresh();
        $this->assertSame('cancelled', $b->status);
        $this->assertSame('expired', $b->payment_status);
    }

    public function test_confirmed_blocks_and_complete_command(): void
    {
        $s = $this->space();
        $start = now()->subDays(2)->setTime(10, 0);
        $b = $this->seedBooking($s, 'confirmed', $start, $start->copy()->addHours(2));
        $b->update(['payment_status' => 'paid']);
        $this->artisan('bookings:complete')->assertSuccessful();
        $this->assertSame('completed', $b->fresh()->status);
    }

    public function test_availability_endpoint(): void
    {
        $s = $this->space();
        $start = now()->addDays(2)->setTime(10, 0);
        $this->seedBooking($s, 'pending', $start, $start->copy()->addHours(2), now()->addMinutes(10));
        $this->getJson(route('spaces.availability', $s).'?date='.$start->toDateString())
            ->assertOk()->assertJsonCount(1, 'taken');
    }
}
