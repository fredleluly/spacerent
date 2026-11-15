<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

if (! class_exists('Midtrans\Config')) {
    // Stub: paket midtrans/midtrans-php belum terpasang di vendor/ lokal.
    eval('namespace Midtrans; class Config { public static $serverKey, $clientKey, $isProduction, $isSanitized, $is3ds; }
          class Snap { public static function getSnapToken($p) { return "tok"; } }
          class Transaction { public static function status($id) { return []; } }');
}

/**
 * PROBE: setiap test menyatakan perilaku BENAR menurut bisnis booking.
 * Test yang gagal = bug / gap pada kode saat ini.
 */
class BookingScenarioProbeTest extends TestCase
{
    use RefreshDatabase;

    private string $key = 'SB-key';
    private User $a;
    private User $b;
    private User $seller;
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 03:00:00');
        config(['midtrans.server_key' => $this->key]);
        $this->a = User::factory()->create(['role' => 'buyer']);
        $this->b = User::factory()->create(['role' => 'buyer']);
        $this->seller = User::factory()->create(['role' => 'seller']);
        $this->day = '2026-11-20';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function space(string $unit = 'jam', array $o = []): Space
    {
        static $n = 0;
        $n++;

        return Space::forceCreate($o + [
            'name' => "S$n", 'slug' => "s-$n", 'category' => 'meeting-room', 'area' => 'Jakarta Pusat',
            'district' => 'X', 'capacity' => 10, 'price' => 100000, 'price_unit' => $unit, 'image' => 'x.jpg',
            'description' => 'd', 'is_available' => true, 'user_id' => $this->seller->id,
        ]);
    }

    private function book(User $u, Space $s, array $o = [])
    {
        return $this->actingAs($u)->post(route('booking.store', $s), $o + [
            'name' => 'N', 'email' => 'n@e.com', 'phone' => '081234567890',
            'date' => $this->day, 'start_time' => '10:00', 'duration' => 2, 'guests' => 2, 'agree' => 1,
        ]);
    }

    private function raw(Space $s, string $status, string $pay, string $start, string $end, ?User $u = null, $exp = null): Booking
    {
        return Booking::forceCreate([
            'code' => Booking::generateCode(), 'space_id' => $s->id, 'user_id' => ($u ?? $this->b)->id,
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0812345678',
            'date' => substr($start, 0, 10), 'start_time' => substr($start, 11, 5), 'duration' => 1, 'guests' => 1,
            'total' => 200000, 'status' => $status, 'payment_status' => $pay, 'starts_at' => $start, 'end_at' => $end,
            'expires_at' => $exp, 'order_id' => 'OID-'.uniqid(),
        ]);
    }

    private function hook(Booking $b, string $status, string $code = '200')
    {
        $p = ['order_id' => $b->order_id, 'status_code' => $code, 'gross_amount' => '200000.00',
            'transaction_status' => $status, 'payment_type' => 'qris', 'fraud_status' => 'accept'];
        $p['signature_key'] = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$this->key);

        return $this->postJson('/midtrans/notification', $p);
    }

    // ---------- A. Slot per jam ----------

    public function test_S01_two_buyers_same_day_different_hours_both_ok(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['start_time' => '09:00'])->assertSessionHasNoErrors();
        $this->book($this->b, $s, ['start_time' => '13:00'])->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());
    }

    public function test_S02_same_slot_second_buyer_rejected(): void
    {
        $s = $this->space();
        $this->book($this->a, $s);
        $this->book($this->b, $s)->assertSessionHasErrors('date');
        $this->assertSame(1, Booking::count());
    }

    public function test_S03_partial_overlap_start_inside_rejected(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['start_time' => '10:00', 'duration' => 3]);
        $this->book($this->b, $s, ['start_time' => '12:00'])->assertSessionHasErrors('date');
    }

    public function test_S04_new_booking_wraps_existing_rejected(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['start_time' => '11:00', 'duration' => 1]);
        $this->book($this->b, $s, ['start_time' => '09:00', 'duration' => 5])->assertSessionHasErrors('date');
    }

    public function test_S05_back_to_back_both_directions_ok(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['start_time' => '10:00', 'duration' => 2]);
        $this->book($this->b, $s, ['start_time' => '12:00', 'duration' => 1])->assertSessionHasNoErrors();
        $this->book($this->b, $s, ['start_time' => '08:00', 'duration' => 2])->assertSessionHasNoErrors();
        $this->assertSame(3, Booking::count());
    }

    public function test_S06_same_buyer_double_books_same_slot_rejected(): void
    {
        $s = $this->space();
        $first = $this->book($this->a, $s);
        $this->book($this->a, $s)->assertRedirect(); // kirim ulang = booking yang sama, bukan booking ganda
        $this->assertSame(1, Booking::where('user_id', $this->a->id)->count());
        $this->book($this->a, $s, ['start_time' => '11:00'])->assertSessionHasErrors('date'); // geser sebagian = bentrok dengan diri sendiri
    }

    public function test_S07_cancelled_expired_failed_free_the_slot(): void
    {
        $s = $this->space();
        $this->raw($s, 'cancelled', 'failed', "$this->day 10:00:00", "$this->day 12:00:00");
        $this->raw($s, 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", null, now()->subMinute());
        $this->book($this->a, $s)->assertSessionHasNoErrors();
    }

    public function test_S08_other_space_same_time_ok(): void
    {
        $this->book($this->a, $this->space());
        $this->book($this->b, $this->space())->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());
    }

    public function test_S09_pending_slot_released_after_30_minutes_for_next_buyer(): void
    {
        $s = $this->space();
        $this->book($this->a, $s);
        Carbon::setTestNow(now()->addMinutes(31));
        $this->book($this->b, $s)->assertSessionHasNoErrors();
    }

    // ---------- B. Aturan waktu / jam operasional ----------

    public function test_S10_cannot_book_crossing_midnight(): void
    {
        $this->book($this->a, $this->space(), ['start_time' => '22:00', 'duration' => 4])->assertSessionHasErrors();
    }

    public function test_S11_cannot_book_at_03_00_outside_operating_hours(): void
    {
        $this->book($this->a, $this->space(), ['start_time' => '03:00'])->assertSessionHasErrors();
    }

    public function test_S12_cannot_book_hour_already_passed_today(): void
    {
        Carbon::setTestNow('2026-11-16 14:00:00');
        $this->book($this->a, $this->space(), ['date' => '2026-11-16', 'start_time' => '09:00'])->assertSessionHasErrors();
    }

    public function test_S13_lead_time_minimum_for_start_in_5_minutes(): void
    {
        Carbon::setTestNow('2026-11-16 09:00:00');
        $this->book($this->a, $this->space(), ['date' => '2026-11-16', 'start_time' => '09:05'])->assertSessionHasErrors();
    }

    public function test_S14_start_time_must_align_to_slot_grid(): void
    {
        $this->book($this->a, $this->space(), ['start_time' => '10:17'])->assertSessionHasErrors();
    }

    public function test_S15_buffer_cleaning_time_between_bookings(): void
    {
        $s = $this->space('jam', ['buffer_minutes' => 30]);
        $this->book($this->a, $s, ['start_time' => '10:00', 'duration' => 2]);
        $this->book($this->b, $s, ['start_time' => '12:00', 'duration' => 1])->assertSessionHasErrors();
    }

    public function test_S16_buyer_cannot_hoard_many_pending_bookings(): void
    {
        $s = $this->space();
        foreach (['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'] as $t) {
            $this->book($this->a, $s, ['start_time' => $t, 'duration' => 1]);
        }
        $this->assertLessThanOrEqual(3, Booking::where('user_id', $this->a->id)->count());
    }

    // ---------- C. Validasi input ----------

    public function test_S17_invalid_inputs_rejected(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['duration' => 0])->assertSessionHasErrors('duration');
        $this->book($this->a, $s, ['duration' => 13])->assertSessionHasErrors('duration');
        $this->book($this->a, $s, ['start_time' => null])->assertSessionHasErrors('start_time');
        $this->book($this->a, $s, ['guests' => 11])->assertSessionHasErrors('guests');
        $this->book($this->a, $s, ['date' => '2026-11-10'])->assertSessionHasErrors('date');
        $this->assertSame(0, Booking::count());
    }

    public function test_S18_client_cannot_inject_total_or_status(): void
    {
        $s = $this->space();
        $this->book($this->a, $s, ['total' => 1, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $b = Booking::first();
        $this->assertSame(200000, (int) $b->subtotal);
        $this->assertSame(210000, (int) $b->total); // sewa 200.000 + biaya layanan 5%
        $this->assertSame('pending', $b->status);
        $this->assertSame('unpaid', $b->payment_status);
    }

    public function test_S19_unavailable_space_cannot_be_booked(): void
    {
        $this->book($this->a, $this->space('jam', ['is_available' => false]))->assertNotFound();
    }

    // ---------- D. Harian & bulanan ----------

    public function test_S20_daily_overlap_and_adjacent(): void
    {
        $s = $this->space('hari');
        $this->book($this->a, $s, ['date' => '2026-11-20', 'duration' => 3, 'start_time' => null]);
        $this->book($this->b, $s, ['date' => '2026-11-22', 'duration' => 1, 'start_time' => null])->assertSessionHasErrors('date');
        $this->book($this->b, $s, ['date' => '2026-11-23', 'duration' => 1, 'start_time' => null])->assertSessionHasNoErrors();
    }

    public function test_S21_monthly_end_of_month_no_overflow(): void
    {
        $s = $this->space('bulan');
        $this->book($this->a, $s, ['date' => '2026-12-31', 'duration' => 2, 'start_time' => null])->assertSessionHasNoErrors();
        $b = Booking::first();
        $this->assertSame('2027-02-28', $b->end_at->toDateString());
        $this->book($this->b, $s, ['date' => '2027-02-28', 'duration' => 1, 'start_time' => null])->assertSessionHasNoErrors();
    }

    // ---------- E. Zona waktu ----------

    public function test_S22_app_timezone_is_wib_for_indonesian_users(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    // ---------- F. Otorisasi ----------

    public function test_S23_buyer_cannot_view_others_booking(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->b, now()->addMinutes(20));
        $this->actingAs($this->a)->get(route('booking.show', $bk))->assertForbidden();
    }

    public function test_S24_buyer_cannot_start_payment_for_others_booking(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->b, now()->addMinutes(20));
        $this->actingAs($this->a)->postJson(route('booking.pay', $bk))->assertForbidden();
    }

    public function test_S25_buyer_cannot_sync_finish_of_others_booking(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->b, now()->addMinutes(20));
        $this->actingAs($this->a)->get(route('booking.finish', $bk))->assertForbidden();
    }

    public function test_S26_other_seller_cannot_cancel_booking(): void
    {
        $bk = $this->raw($this->space(), 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00");
        $other = User::factory()->create(['role' => 'seller']);
        $this->actingAs($other)->post(route('seller.bookings.cancel', $bk))->assertForbidden();
    }

    // ---------- G. Pembayaran / webhook (race & late payment) ----------

    public function test_S27_pay_for_cancelled_or_expired_booking_blocked(): void
    {
        $s = $this->space();
        $c = $this->raw($s, 'cancelled', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(20));
        $this->actingAs($this->a)->postJson(route('booking.pay', $c))->assertStatus(422);
        $e = $this->raw($s, 'pending', 'unpaid', "$this->day 14:00:00", "$this->day 15:00:00", $this->a, now()->subMinute());
        $this->actingAs($this->a)->postJson(route('booking.pay', $e))->assertStatus(422);
    }

    public function test_S28_late_settlement_after_slot_retaken_must_not_double_book(): void
    {
        $s = $this->space();
        $old = $this->raw($s, 'cancelled', 'expired', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $new = $this->raw($s, 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->b);
        $this->hook($old, 'settlement');
        $confirmed = Booking::where('space_id', $s->id)->where('status', 'confirmed')->count();
        $this->assertSame(1, $confirmed, 'Dua booking confirmed pada slot yang sama');
    }

    public function test_S29_settlement_on_seller_cancelled_booking_must_not_resurrect_it(): void
    {
        $bk = $this->raw($this->space(), 'cancelled', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(10));
        $this->hook($bk, 'settlement');
        $this->assertNotSame('confirmed', $bk->fresh()->status);
    }

    public function test_S30_webhook_duplicate_idempotent(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(10));
        $this->hook($bk, 'settlement')->assertOk();
        $paidAt = $bk->fresh()->paid_at;
        $this->hook($bk, 'settlement')->assertOk();
        $this->assertEquals($paidAt, $bk->fresh()->paid_at);
    }

    public function test_S31_capture_challenge_stays_waiting(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(10));
        $p = ['order_id' => $bk->order_id, 'status_code' => '201', 'gross_amount' => '200000.00', 'transaction_status' => 'capture', 'fraud_status' => 'challenge'];
        $p['signature_key'] = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$this->key);
        $this->postJson('/midtrans/notification', $p)->assertOk();
        $this->assertSame('unpaid', $bk->fresh()->payment_status);
    }

    public function test_S32_pending_unpaid_with_gateway_pending_not_expired_early(): void
    {
        // VA/QRIS sudah dibuat (pending di Midtrans) dan belum lewat expires_at -> tetap menahan slot
        $s = $this->space();
        $this->raw($s, 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(5));
        $this->artisan('bookings:expire');
        $this->book($this->b, $s)->assertSessionHasErrors('date');
    }

    // ---------- H. Admin / seller state machine ----------

    public function test_S33_admin_cannot_revive_cancelled_booking_over_taken_slot(): void
    {
        $s = $this->space();
        $dead = $this->raw($s, 'cancelled', 'expired', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $this->raw($s, 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->b);
        $admin = User::factory()->create(['role' => 'admin']);
        // Admin tidak lagi punya dropdown status bebas: rute pengubah status sudah dihapus.
        $this->assertFalse(Route::has('admin.bookings.update'));
        $this->actingAs($admin)->patch('/admin/bookings/'.$dead->code, ['status' => 'confirmed'])->assertStatus(405);
        $this->assertNotSame('confirmed', $dead->fresh()->status);
    }

    public function test_S34_admin_cannot_confirm_unpaid_booking(): void
    {
        $bk = $this->raw($this->space(), 'pending', 'unpaid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a, now()->addMinutes(10));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch('/admin/bookings/'.$bk->code, ['status' => 'confirmed'])->assertStatus(405);
        $this->assertNotSame('confirmed', $bk->fresh()->status);
    }

    public function test_S35_admin_cannot_delete_paid_booking(): void
    {
        $bk = $this->raw($this->space(), 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('admin.bookings.destroy', $bk));
        $this->assertDatabaseHas('bookings', ['id' => $bk->id]);
    }

    public function test_S36_seller_cannot_complete_before_start(): void
    {
        $bk = $this->raw($this->space(), 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $this->actingAs($this->seller)->post(route('seller.bookings.complete', $bk));
        $this->assertSame('confirmed', $bk->fresh()->status);
    }

    public function test_S37_seller_cancel_of_paid_booking_records_refund_obligation(): void
    {
        $bk = $this->raw($this->space(), 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $this->actingAs($this->seller)->post(route('seller.bookings.cancel', $bk));
        $this->assertContains($bk->fresh()->payment_status, ['refund_pending', 'refunded'], 'Booking dibatalkan tapi uang tetap "paid" tanpa tanda refund');
    }

    public function test_S38_deleting_space_with_paid_bookings_is_blocked(): void
    {
        $s = $this->space();
        $this->raw($s, 'confirmed', 'paid', "$this->day 10:00:00", "$this->day 12:00:00", $this->a);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('admin.spaces.destroy', $s));
        $this->assertSame(1, Booking::count(), 'Booking lunas ikut terhapus bersama ruangan');
    }

    // ---------- I. Fitur yang seharusnya ada (route) ----------

    public function test_S39_buyer_can_cancel_own_booking_route_exists(): void
    {
        $this->assertTrue(Route::has('booking.cancel'));
    }

    public function test_S40_review_routes_exist(): void
    {
        $this->assertTrue(Route::has('reviews.store'));
    }

    public function test_S41_seller_can_block_dates_route_exists(): void
    {
        $this->assertTrue(Route::has('seller.blackouts.store'));
    }

    public function test_S42_reschedule_route_exists(): void
    {
        $this->assertTrue(Route::has('booking.reschedule'));
    }

    public function test_S43_space_rating_is_not_a_manually_editable_column(): void
    {
        $this->assertFalse(in_array('rating', (new Space)->getFillable(), true), 'Rating masih kolom statis yang bisa diisi manual');
    }
}
