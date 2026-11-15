<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function space(?User $owner = null): Space
    {
        return Space::forceCreate([
            'name' => 'Ruang '.uniqid(), 'slug' => 'r-'.uniqid(), 'category' => 'meeting-room', 'area' => 'Jakarta Pusat',
            'district' => 'X', 'capacity' => 10, 'price' => 100000, 'price_unit' => 'jam', 'image' => 'x.jpg',
            'description' => 'd', 'is_available' => true, 'user_id' => $owner?->id,
        ]);
    }

    private function booking(Space $s, User $buyer, string $code = 'SR-AAA111'): Booking
    {
        return Booking::forceCreate([
            'code' => $code, 'space_id' => $s->id, 'user_id' => $buyer->id, 'name' => 'B', 'email' => 'b@e.com',
            'phone' => '0812345678', 'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'duration' => 1,
            'guests' => 1, 'total' => 100000, 'status' => 'pending', 'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function reg(array $o = []): array
    {
        return $o + [
            'name' => 'Andi', 'email' => 'andi@example.com', 'phone' => '081234567890',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'buyer',
        ];
    }

    public function test_register_buyer_and_seller(): void
    {
        $this->post('/daftar', $this->reg())->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertSame('buyer', User::where('email', 'andi@example.com')->first()->role);
        $this->post('/keluar');

        $this->post('/daftar', $this->reg(['email' => 'sel@example.com', 'role' => 'seller']))->assertRedirect(route('seller.dashboard'));
        $this->assertSame('seller', User::where('email', 'sel@example.com')->first()->role);
    }

    public function test_cannot_register_as_admin(): void
    {
        $this->post('/daftar', $this->reg(['role' => 'admin']))->assertSessionHasErrors('role');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_and_logout(): void
    {
        $u = $this->user('buyer');
        $this->post('/masuk', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/masuk', ['email' => $u->email, 'password' => 'password'])->assertRedirect(route('buyer.bookings.index'));
        $this->assertAuthenticatedAs($u);
        $this->post('/keluar')->assertRedirect(route('home'));
        $this->assertGuest();

        $a = $this->user('admin');
        $this->post('/masuk', ['email' => $a->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_redirect_param_is_same_host_only(): void
    {
        $u = $this->user('buyer');
        $this->post('/masuk', ['email' => $u->email, 'password' => 'password', 'redirect' => 'https://evil.test/x'])
            ->assertRedirect(route('buyer.bookings.index'));
        $this->post('/keluar');
        $this->post('/masuk', ['email' => $u->email, 'password' => 'password', 'redirect' => '/ruangan/abc'])
            ->assertRedirect('/ruangan/abc');
    }

    public function test_guest_booking_redirected_to_login(): void
    {
        $s = $this->space();
        $this->post(route('booking.store', $s), [])->assertRedirect(route('login'));
        $this->get(route('spaces.show', $s))->assertSee('Masuk untuk memesan');
    }

    public function test_login_ignores_stale_intended_url_of_another_role(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'password' => 'secret123']);

        // Tamu membuka /admin lebih dulu (Laravel mengingatnya sebagai tujuan setelah login).
        $this->get('/admin/dashboard')->assertRedirect();
        $this->post('/masuk', ['email' => $seller->email, 'password' => 'secret123'])
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_seller_cannot_book_and_sees_note(): void
    {
        $seller = $this->user('seller');
        $s = $this->space($seller);
        $this->actingAs($seller)->post(route('booking.store', $s), [])->assertForbidden();
        $this->actingAs($seller)->get(route('spaces.show', $s))->assertSee('Hanya akun pembeli');
    }

    public function test_booking_show_authorization(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $b = $this->booking($this->space($seller), $buyer);

        $this->actingAs($other)->get(route('booking.show', $b))->assertForbidden();
        $this->actingAs($buyer)->get(route('booking.show', $b))->assertOk();
        $this->actingAs($seller)->get(route('booking.show', $b))->assertOk();
        $this->actingAs($this->user('seller'))->get(route('booking.show', $b))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('booking.show', $b))->assertOk();
    }

    public function test_role_areas_are_guarded(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->actingAs($buyer)->get('/penjual/dashboard')->assertForbidden();
        $this->actingAs($buyer)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($seller)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_buyer_orders_list_only_own(): void
    {
        $seller = $this->user('seller');
        $me = $this->user('buyer');
        $other = $this->user('buyer');
        $s = $this->space($seller);
        $this->booking($s, $me, 'SR-MINE01');
        $this->booking($s, $other, 'SR-OTHER1');

        $this->actingAs($me)->get(route('buyer.bookings.index'))
            ->assertOk()->assertSee('SR-MINE01')->assertDontSee('SR-OTHER1');
    }

    public function test_buyer_can_update_profile(): void
    {
        $me = $this->user('buyer');
        $this->actingAs($me)->put(route('buyer.profile'), ['name' => 'Baru', 'phone' => '081299998888'])->assertSessionHas('success');
        $this->assertSame('Baru', $me->fresh()->name);
    }
}
