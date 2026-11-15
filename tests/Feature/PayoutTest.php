<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payout;
use App\Models\Space;
use App\Models\User;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Komisi, saldo, masa tahan, penarikan, dan keputusan admin. */
class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 12:00:00');
        $this->seller = User::factory()->create(['role' => 'seller']);
        $this->buyer = User::factory()->create(['role' => 'buyer', 'phone' => '081234567890']);
        $this->space = Space::forceCreate([
            'name' => 'R', 'slug' => 'r', 'category' => 'meeting-room', 'area' => 'Jakarta Pusat', 'district' => 'X', 'capacity' => 5,
            'price' => 100000, 'price_unit' => 'jam', 'image' => 'x.jpg', 'description' => 'd', 'user_id' => $this->seller->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Sewa 1.000.000 + kebersihan 100.000 + layanan 55.000 = 1.155.000, komisi 10%. */
    private function booking(string $status, string $start, array $o = []): Booking
    {
        $st = Carbon::parse($start);

        return Booking::forceCreate($o + [
            'code' => Booking::generateCode(), 'space_id' => $this->space->id, 'user_id' => $this->buyer->id, 'name' => 'B', 'email' => 'b@e.com',
            'phone' => '0812', 'date' => $st->toDateString(), 'start_time' => $st->format('H:i'), 'duration' => 10, 'guests' => 1,
            'subtotal' => 1000000, 'cleaning_fee' => 100000, 'service_fee' => 55000, 'total' => 1155000, 'commission_percent' => 10,
            'status' => $status, 'payment_status' => 'paid', 'starts_at' => $st, 'end_at' => $st->copy()->addHours(10),
            'paid_at' => now()->subDays(20), 'cancellation_policy' => 'moderate',
        ]);
    }

    private function bank(): void
    {
        $this->seller->forceFill(['bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_name' => 'Penjual Demo'])->save();
    }

    public function test_P01_earning_excludes_buyer_service_fee_and_takes_commission(): void
    {
        $b = $this->booking('completed', '2026-11-10 08:00');
        $svc = app(PayoutService::class);
        $this->assertSame(1100000, $svc->retainedBase($b));
        $this->assertSame(110000, $svc->commission($b));
        $this->assertSame(990000, $svc->earning($b));
    }

    public function test_P02_new_booking_snapshots_commission_rate(): void
    {
        config(['spacerent.commission_percent' => 12]);
        $this->actingAs($this->buyer)->post(route('booking.store', $this->space), [
            'name' => 'B', 'email' => 'b@e.com', 'phone' => '081234567890', 'date' => '2026-11-25', 'start_time' => '10:00',
            'duration' => 2, 'guests' => 1, 'agree' => 1,
        ]);
        config(['spacerent.commission_percent' => 20]);
        $this->assertSame(12.0, (float) Booking::first()->commission_percent);
    }

    public function test_P03_hold_period_before_funds_become_available(): void
    {
        $this->booking('completed', '2026-11-16 00:00'); // selesai 10:00 tadi, masa tahan 1 hari belum lewat
        $this->booking('completed', '2026-11-10 08:00');  // sudah lewat
        $this->booking('confirmed', '2026-11-25 08:00');  // belum selesai
        $bal = app(PayoutService::class)->balances($this->seller);
        $this->assertSame(990000, $bal['available']);
        $this->assertSame(990000 * 2, $bal['upcoming']);
    }

    public function test_P04_cancelled_by_buyer_with_partial_refund_pays_seller_the_retained_part(): void
    {
        // refund 50% dari sewa+kebersihan = 550.000 (layanan tidak dikembalikan), penjual menahan 550.000 sebelum komisi
        $b = $this->booking('cancelled', '2026-11-20 08:00', ['payment_status' => 'refund_pending', 'refund_amount' => 550000, 'refund_status' => 'pending', 'cancelled_at' => now()->subDays(3), 'cancelled_by' => 'buyer']);
        $svc = app(PayoutService::class);
        $this->assertSame(550000, $svc->retainedBase($b));
        $this->assertSame(495000, $svc->earning($b));
    }

    public function test_P05_full_refund_or_seller_cancellation_earns_nothing(): void
    {
        $b = $this->booking('cancelled', '2026-11-20 08:00', ['payment_status' => 'refund_pending', 'refund_amount' => 1155000, 'cancelled_at' => now()->subDays(3), 'cancelled_by' => 'seller']);
        $this->assertSame(0, app(PayoutService::class)->earning($b));
        $this->assertCount(0, app(PayoutService::class)->eligible($this->seller));
    }

    public function test_P06_request_requires_bank_and_minimum(): void
    {
        $this->booking('completed', '2026-11-10 08:00');
        $this->actingAs($this->seller)->post(route('seller.payouts.request'))->assertSessionHas('error');
        $this->assertSame(0, Payout::count());

        $small = User::factory()->create(['role' => 'seller', 'bank_name' => 'BNI', 'bank_account_number' => '999999', 'bank_account_name' => 'X']);
        $this->actingAs($small)->post(route('seller.payouts.request'))->assertSessionHas('error'); // tanpa saldo
    }

    public function test_P07_request_locks_bookings_and_cannot_be_doubled(): void
    {
        $this->bank();
        $a = $this->booking('completed', '2026-11-10 08:00');
        $this->booking('completed', '2026-11-08 08:00');

        $this->actingAs($this->seller)->post(route('seller.payouts.request'))->assertSessionHas('success');
        $p = Payout::first();
        $this->assertSame(1980000, (int) $p->amount);
        $this->assertSame(2, $p->bookings_count);
        $this->assertSame($p->id, $a->fresh()->payout_id);
        $this->assertSame('BCA', $p->bank_name);

        $this->post(route('seller.payouts.request'))->assertSessionHas('error');
        $this->assertSame(1, Payout::count());
        $this->assertSame(0, app(PayoutService::class)->balances($this->seller)['available']);
        $this->assertSame(1980000, app(PayoutService::class)->balances($this->seller)['processing']);
    }

    public function test_P08_admin_marks_paid_and_reject_returns_balance(): void
    {
        $this->bank();
        $this->booking('completed', '2026-11-10 08:00');
        $this->actingAs($this->seller)->post(route('seller.payouts.request'));
        $p = Payout::first();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.payouts.reject', $p), ['note' => 'Rekening tidak valid'])->assertSessionHas('success');
        $this->assertSame('rejected', $p->fresh()->status);
        $this->assertSame(990000, app(PayoutService::class)->balances($this->seller)['available']);

        $this->post(route('admin.payouts.paid', $p), ['reference' => 'TRX1'])->assertSessionHas('error'); // sudah diproses

        $this->actingAs($this->seller)->post(route('seller.payouts.request'));
        $p2 = Payout::where('status', 'requested')->first();
        $this->actingAs($admin)->post(route('admin.payouts.paid', $p2), ['reference' => 'TRX2'])->assertSessionHas('success');
        $this->assertSame('paid', $p2->fresh()->status);
        $this->assertSame('TRX2', $p2->fresh()->reference);
        $this->assertSame(990000, app(PayoutService::class)->balances($this->seller)['paid']);
    }

    public function test_P09_access_control_and_pages_render(): void
    {
        $this->actingAs($this->buyer)->get(route('seller.payouts.index'))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.payouts.index'))->assertForbidden();

        $this->booking('completed', '2026-11-10 08:00');
        $this->actingAs($this->seller)->get(route('seller.payouts.index'))->assertOk()->assertSee('Saldo tersedia')->assertSee('Rp 990.000');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk();
    }

    public function test_P10_bank_validation(): void
    {
        $this->actingAs($this->seller)->post(route('seller.payouts.bank'), ['bank_name' => 'BCA', 'bank_account_number' => 'abc', 'bank_account_name' => 'X'])
            ->assertSessionHasErrors('bank_account_number');
        $this->post(route('seller.payouts.bank'), ['bank_name' => 'BCA', 'bank_account_number' => '123456789', 'bank_account_name' => 'Penjual'])->assertSessionHas('success');
        $this->assertSame('123456789', $this->seller->fresh()->bank_account_number);
    }
}
