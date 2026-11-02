<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@test.id', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function booking(array $attrs = []): Booking
    {
        static $n = 0;
        $n++;
        $space = Space::first() ?? Space::create([
            'name' => 'Ruang A', 'slug' => 'ruang-a', 'category' => 'meeting-room', 'area' => 'Jakarta Selatan',
            'district' => 'Kuningan', 'capacity' => 10, 'price' => 100000, 'price_unit' => 'jam',
            'image' => 'x.jpg', 'description' => 'desc',
        ]);

        $b = new Booking([
            'code' => 'SR-T'.str_pad($n, 5, '0', STR_PAD_LEFT), 'space_id' => $space->id, 'name' => 'Budi',
            'email' => 'b@test.id', 'phone' => '0812', 'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00', 'duration' => 2, 'guests' => 5, 'total' => 200000,
        ]);
        $b->forceFill(array_merge(['status' => 'pending', 'payment_status' => 'unpaid', 'starts_at' => now()->addDays(5)], $attrs))->save();

        return $b->fresh();
    }

    public function test_filter_by_payment_status(): void
    {
        $paid = $this->booking(['payment_status' => 'paid']);
        $unpaid = $this->booking();

        $res = $this->actingAs($this->admin())->get(route('admin.bookings.index', ['payment_status' => 'paid']));
        $res->assertOk()->assertSee($paid->code)->assertDontSee($unpaid->code);
    }

    public function test_cancel_refund_complete_flow(): void
    {
        $admin = $this->admin();
        $b = $this->booking(['status' => 'confirmed', 'payment_status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.bookings.cancel', $b))->assertSessionHas('success');
        $this->assertSame('cancelled', $b->fresh()->status);

        $this->actingAs($admin)->get(route('admin.bookings.show', $b))->assertSee('Perlu refund')->assertSee('Rp 200.000');

        $this->post(route('admin.bookings.refund', $b))->assertSessionHas('success');
        $this->assertSame('refunded', $b->fresh()->payment_status);

        $c = $this->booking(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->post(route('admin.bookings.complete', $c));
        $this->assertSame('completed', $c->fresh()->status);
    }

    public function test_invalid_transitions_rejected(): void
    {
        $this->actingAs($this->admin());
        $unpaid = $this->booking();
        $this->post(route('admin.bookings.refund', $unpaid))->assertSessionHas('error');
        $this->post(route('admin.bookings.complete', $unpaid))->assertSessionHas('error');
        $this->assertSame('unpaid', $unpaid->fresh()->payment_status);
        $this->assertSame('pending', $unpaid->fresh()->status);

        $done = $this->booking(['status' => 'completed', 'payment_status' => 'paid']);
        $this->post(route('admin.bookings.cancel', $done))->assertSessionHas('error');
        $this->assertSame('completed', $done->fresh()->status);
    }

    public function test_dashboard_shows_revenue(): void
    {
        $this->booking(['payment_status' => 'paid', 'status' => 'confirmed', 'paid_at' => now(), 'total' => 1234000]);
        $this->booking(['payment_status' => 'unpaid', 'total' => 999000]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Rp 1.234.000');
    }
}
