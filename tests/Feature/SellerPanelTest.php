<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email): User
    {
        $u = new User(['name' => ucfirst($role), 'email' => $email, 'password' => 'secret123']);
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    private function space(User $owner, string $name): Space
    {
        return Space::create([
            'name' => $name, 'slug' => str($name)->slug(), 'category' => 'meeting-room', 'area' => 'Jakarta Selatan',
            'district' => 'Kuningan', 'capacity' => 10, 'price' => 100000, 'price_unit' => 'jam',
            'image' => 'x.jpg', 'description' => 'desc', 'user_id' => $owner->id,
        ]);
    }

    private function booking(Space $space, array $attrs = []): Booking
    {
        static $n = 0;
        $n++;
        $b = new Booking([
            'code' => 'SR-S'.str_pad($n, 5, '0', STR_PAD_LEFT), 'space_id' => $space->id, 'name' => 'Budi'.$n,
            'email' => 'b@test.id', 'phone' => '0812', 'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00', 'duration' => 2, 'guests' => 5, 'total' => 200000,
        ]);
        $b->forceFill(array_merge(['status' => 'pending', 'payment_status' => 'unpaid', 'starts_at' => now()->addDays(5)], $attrs))->save();

        return $b->fresh();
    }

    private function setup2(): array
    {
        $a = $this->user('seller', 'a@test.id');
        $b = $this->user('seller', 'b@test.id');

        return [$a, $b, $this->space($a, 'Ruang Alfa'), $this->space($b, 'Ruang Beta')];
    }

    public function test_access_control(): void
    {
        $this->get('/penjual/dashboard')->assertRedirect();
        $this->actingAs($this->user('buyer', 'buy@test.id'))->get('/penjual/dashboard')->assertForbidden();
        $this->actingAs($this->user('seller', 's@test.id'))->get('/penjual/dashboard')->assertOk();
    }

    public function test_sees_only_own_spaces_and_bookings(): void
    {
        [$a, $b, $sa, $sb] = $this->setup2();
        $ba = $this->booking($sa);
        $bb = $this->booking($sb);

        $this->actingAs($a)->get(route('seller.spaces.index'))->assertSee('Ruang Alfa')->assertDontSee('Ruang Beta');
        $this->get(route('seller.bookings.index'))->assertSee($ba->code)->assertDontSee($bb->code);
        $this->get(route('seller.bookings.show', $ba))->assertOk();
        $this->get(route('seller.bookings.show', $bb))->assertForbidden();
    }

    public function test_cannot_modify_other_sellers_space(): void
    {
        [$a, $b, $sa, $sb] = $this->setup2();

        $this->actingAs($a)->get(route('seller.spaces.edit', $sb))->assertForbidden();
        $this->put(route('seller.spaces.update', $sb), ['name' => 'Hack'])->assertForbidden();
        $this->delete(route('seller.spaces.destroy', $sb))->assertForbidden();
        $this->assertDatabaseHas('spaces', ['id' => $sb->id, 'name' => 'Ruang Beta']);
    }

    public function test_create_sets_owner_and_ignores_input_user_id(): void
    {
        [$a, $b] = $this->setup2();

        $this->actingAs($a)->post(route('seller.spaces.store'), [
            'name' => 'Ruang Baru', 'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => 'SCBD',
            'capacity' => 8, 'price' => 150000, 'price_unit' => 'jam', 'description' => 'desc',
            'facilities' => "WiFi\nAC", 'image_url' => 'https://example.com/a.jpg', 'is_available' => 1, 'user_id' => $b->id,
        ])->assertRedirect(route('seller.spaces.index'));

        $this->assertDatabaseHas('spaces', ['name' => 'Ruang Baru', 'user_id' => $a->id]);
    }

    public function test_update_and_delete_own_space(): void
    {
        [$a, , $sa] = $this->setup2();

        $this->actingAs($a)->put(route('seller.spaces.update', $sa), [
            'name' => 'Alfa Baru', 'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => 'SCBD',
            'capacity' => 8, 'price' => 150000, 'price_unit' => 'jam', 'description' => 'desc',
        ])->assertSessionHas('success');
        $this->assertSame('Alfa Baru', $sa->fresh()->name);
        $this->assertFalse($sa->fresh()->is_available);

        $this->actingAs($a)->delete(route('seller.spaces.destroy', $sa->fresh()))->assertSessionHas('success');
        $this->assertDatabaseMissing('spaces', ['id' => $sa->id]);
    }

    public function test_cannot_delete_space_with_upcoming_paid_booking(): void
    {
        [$a, , $sa] = $this->setup2();
        $this->booking($sa, ['status' => 'confirmed', 'payment_status' => 'paid']);

        $this->actingAs($a)->delete(route('seller.spaces.destroy', $sa))->assertSessionHas('error');
        $this->assertDatabaseHas('spaces', ['id' => $sa->id]);
    }

    public function test_dashboard_revenue_counts_only_own_paid(): void
    {
        [$a, , $sa, $sb] = $this->setup2();
        $this->booking($sa, ['status' => 'confirmed', 'payment_status' => 'paid', 'paid_at' => now(), 'total' => 300000]);
        $this->booking($sa, ['total' => 999000]);
        $this->booking($sb, ['status' => 'confirmed', 'payment_status' => 'paid', 'paid_at' => now(), 'total' => 777000]);

        $res = $this->actingAs($a)->get(route('seller.dashboard'))->assertOk();
        $res->assertSee('Rp 300.000')->assertDontSee('777.000');
    }

    public function test_cancel_and_complete_transitions(): void
    {
        [$a, $b, $sa, $sb] = $this->setup2();
        $paid = $this->booking($sa, ['status' => 'confirmed', 'payment_status' => 'paid']);
        $pending = $this->booking($sa);

        $this->actingAs($a)->post(route('seller.bookings.complete', $pending))->assertSessionHas('error');
        $this->assertSame('pending', $pending->fresh()->status);

        $this->post(route('seller.bookings.complete', $paid))->assertSessionHas('success');
        $this->assertSame('completed', $paid->fresh()->status);
        $this->post(route('seller.bookings.cancel', $paid))->assertSessionHas('error');

        $this->post(route('seller.bookings.cancel', $pending))->assertSessionHas('success');
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->post(route('seller.bookings.cancel', $pending))->assertSessionHas('error');

        $other = $this->booking($sb);
        $this->post(route('seller.bookings.cancel', $other))->assertForbidden();
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_cancelled_paid_shows_refund_note(): void
    {
        [$a, , $sa] = $this->setup2();
        $b = $this->booking($sa, ['status' => 'cancelled', 'payment_status' => 'paid']);

        $this->actingAs($a)->get(route('seller.bookings.show', $b))->assertSee('Refund diproses oleh admin SpaceRent');
    }
}
