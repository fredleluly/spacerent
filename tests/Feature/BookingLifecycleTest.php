<?php

namespace Tests\Feature;

use App\Mail\BookingNotice;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\Space;
use App\Models\SpaceBlackout;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Study case siklus hidup booking ala Airbnb dasar: jauh hari, persetujuan, batal, refund, jadwal ulang, ulasan, rating. */
class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $other;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 03:00:00');
        Mail::fake();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'phone' => '081234567890']);
        $this->other = User::factory()->create(['role' => 'buyer', 'phone' => '081234567891']);
        $this->seller = User::factory()->create(['role' => 'seller']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function space(array $o = []): Space
    {
        static $n = 0;
        $n++;

        return Space::forceCreate($o + [
            'name' => "Ruang $n", 'slug' => "ruang-$n", 'category' => 'meeting-room', 'area' => 'Jakarta Pusat', 'district' => 'X',
            'capacity' => 10, 'price' => 100000, 'price_unit' => 'jam', 'image' => 'x.jpg', 'description' => 'd',
            'is_available' => true, 'user_id' => $this->seller->id,
        ]);
    }

    private function book(Space $s, array $o = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->buyer)->post(route('booking.store', $s), $o + [
            'name' => 'Budi', 'email' => 'b@e.com', 'phone' => '081234567890', 'date' => '2026-11-25',
            'start_time' => '10:00', 'duration' => 2, 'guests' => 2, 'agree' => 1,
        ]);
    }

    /** Booking langsung di DB. $start string 'Y-m-d H:i'. */
    private function booking(Space $s, string $status, string $pay, string $start, int $hours = 2, array $o = []): Booking
    {
        $st = Carbon::parse($start);
        $sub = 100000 * $hours;
        $service = (int) round($sub * 0.05);

        return Booking::forceCreate($o + [
            'code' => Booking::generateCode(), 'space_id' => $s->id, 'user_id' => $this->buyer->id, 'name' => 'Budi', 'email' => 'b@e.com',
            'phone' => '0812', 'date' => $st->toDateString(), 'start_time' => $st->format('H:i'), 'duration' => $hours, 'guests' => 2,
            'subtotal' => $sub, 'cleaning_fee' => 0, 'service_fee' => $service, 'total' => $sub + $service,
            'status' => $status, 'payment_status' => $pay, 'starts_at' => $st, 'end_at' => $st->copy()->addHours($hours),
            'expires_at' => $status === 'pending' ? now()->addMinutes(20) : null, 'cancellation_policy' => $s->cancellation_policy ?? 'moderate',
            'paid_at' => $pay === 'paid' ? now()->subDay() : null,
        ]);
    }

    // ---------- Booking jauh hari ----------

    public function test_L01_booking_far_ahead_within_window_is_accepted(): void
    {
        $s = $this->space();
        $this->book($s, ['date' => '2027-05-12'])->assertSessionHasNoErrors();
        $this->assertSame('2027-05-12', Booking::first()->starts_at->toDateString());
    }

    public function test_L02_booking_beyond_advance_window_is_rejected(): void
    {
        $s = $this->space(['max_advance_days' => 30]);
        $this->book($s, ['date' => '2027-02-01'])->assertSessionHasErrors('date');
        $this->assertSame(0, Booking::count());
    }

    public function test_L03_price_change_after_booking_does_not_affect_existing_booking(): void
    {
        $s = $this->space();
        $this->book($s);
        $s->forceFill(['price' => 999999])->save();
        $this->assertSame(200000, (int) Booking::first()->subtotal);
    }

    public function test_L04_blackout_blocks_booking_and_cannot_cover_active_booking(): void
    {
        $s = $this->space();
        SpaceBlackout::create(['space_id' => $s->id, 'starts_on' => '2026-11-25', 'ends_on' => '2026-11-26']);
        $this->book($s)->assertSessionHasErrors('date');

        $s2 = $this->space();
        $this->book($s2);
        $this->actingAs($this->seller)->post(route('seller.blackouts.store', $s2), ['starts_on' => '2026-11-25', 'ends_on' => '2026-11-25'])
            ->assertSessionHasErrors('starts_on');
        $this->assertSame(0, $s2->blackouts()->count());
    }

    public function test_L05_closed_weekday_and_hours_are_enforced(): void
    {
        $s = $this->space(['open_days' => [1, 2, 3, 4, 5], 'open_time' => '09:00', 'close_time' => '17:00']);
        $this->book($s, ['date' => '2026-11-28'])->assertSessionHasErrors('date');   // Sabtu
        $this->book($s, ['date' => '2026-11-25', 'start_time' => '16:00', 'duration' => 2])->assertSessionHasErrors('date'); // lewat jam tutup
        $this->book($s, ['date' => '2026-11-25', 'start_time' => '09:00'])->assertSessionHasNoErrors();
    }

    // ---------- Mode persetujuan ----------

    public function test_L06_approval_flow_approve_then_payable(): void
    {
        $s = $this->space(['instant_book' => false]);
        $this->book($s);
        $b = Booking::first();
        $this->assertSame('awaiting_approval', $b->status);
        $this->assertNull($b->expires_at);

        $this->actingAs($this->buyer)->postJson(route('booking.pay', $b))->assertStatus(422);

        $this->actingAs($this->seller)->post(route('seller.bookings.approve', $b))->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('pending', $b->status);
        $this->assertTrue($b->isPayable());
    }

    public function test_L07_reject_frees_slot_and_other_seller_cannot_decide(): void
    {
        $s = $this->space(['instant_book' => false]);
        $this->book($s);
        $b = Booking::first();

        $stranger = User::factory()->create(['role' => 'seller']);
        $this->actingAs($stranger)->post(route('seller.bookings.approve', $b))->assertForbidden();

        $this->actingAs($this->seller)->post(route('seller.bookings.reject', $b), ['reason' => 'Sedang renovasi'])->assertSessionHas('success');
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->book($s, [], $this->other)->assertSessionHasNoErrors();
    }

    public function test_L08_unanswered_request_expires(): void
    {
        $s = $this->space(['instant_book' => false]);
        $this->book($s);
        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('bookings:expire')->assertSuccessful();
        $b = Booking::first();
        $this->assertSame('cancelled', $b->status);
        $this->assertSame('system', $b->cancelled_by);
    }

    // ---------- Batal dan refund ----------

    public function test_L09_moderate_policy_tiers(): void
    {
        $s = $this->space(['cancellation_policy' => 'moderate']);

        $far = $this->booking($s, 'confirmed', 'paid', '2026-11-20 10:00');           // ~103 jam
        $this->actingAs($this->buyer)->post(route('booking.cancel', $far));
        $far->refresh();
        $this->assertSame((int) $far->total, (int) $far->refund_amount);
        $this->assertSame('refund_pending', $far->payment_status);

        $mid = $this->booking($s, 'confirmed', 'paid', '2026-11-17 18:00');           // ~39 jam
        $this->post(route('booking.cancel', $mid));
        $mid->refresh();
        $this->assertSame(100000, (int) $mid->refund_amount); // 50% dari sewa 200.000, biaya layanan tidak dikembalikan

        $near = $this->booking($s, 'confirmed', 'paid', '2026-11-16 13:00');          // 10 jam
        $this->post(route('booking.cancel', $near));
        $near->refresh();
        $this->assertSame('cancelled', $near->status);
        $this->assertSame(0, (int) $near->refund_amount);
        $this->assertSame('paid', $near->payment_status);
    }

    public function test_L10_flexible_and_strict_policies(): void
    {
        $flex = $this->booking($this->space(['cancellation_policy' => 'flexible']), 'confirmed', 'paid', '2026-11-17 10:00'); // 31 jam
        $this->actingAs($this->buyer)->post(route('booking.cancel', $flex));
        $this->assertSame((int) $flex->fresh()->total, (int) $flex->fresh()->refund_amount);

        $strict = $this->booking($this->space(['cancellation_policy' => 'strict']), 'confirmed', 'paid', '2026-11-21 10:00'); // ~127 jam
        $this->post(route('booking.cancel', $strict));
        $this->assertSame(100000, (int) $strict->fresh()->refund_amount); // 50%
    }

    public function test_L11_seller_or_admin_cancellation_always_refunds_fully(): void
    {
        $s = $this->space(['cancellation_policy' => 'strict']);
        $b = $this->booking($s, 'confirmed', 'paid', '2026-11-16 12:00'); // sangat dekat
        $this->actingAs($this->seller)->post(route('seller.bookings.cancel', $b));
        $b->refresh();
        $this->assertSame((int) $b->total, (int) $b->refund_amount);
        $this->assertSame('refund_pending', $b->payment_status);
        $this->assertSame('seller', $b->cancelled_by);
    }

    public function test_L12_only_owner_cancels_and_not_after_start(): void
    {
        $s = $this->space();
        $b = $this->booking($s, 'confirmed', 'paid', '2026-11-20 10:00');
        $this->actingAs($this->other)->post(route('booking.cancel', $b))->assertForbidden();

        $started = $this->booking($s, 'confirmed', 'paid', '2026-11-16 02:00', 4);
        $this->actingAs($this->buyer)->post(route('booking.cancel', $started))->assertSessionHas('error');
        $this->assertSame('confirmed', $started->fresh()->status);
    }

    public function test_L13_cancel_unpaid_pending_just_releases_slot(): void
    {
        $s = $this->space();
        $this->book($s);
        $b = Booking::first();
        $this->post(route('booking.cancel', $b))->assertSessionHas('success');
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(0, (int) $b->fresh()->refund_amount);
        $this->book($s, [], $this->other)->assertSessionHasNoErrors();
    }

    // ---------- Jadwal ulang ----------

    public function test_L14_reschedule_once_to_free_slot(): void
    {
        $s = $this->space();
        $b = $this->booking($s, 'confirmed', 'paid', '2026-11-25 10:00');
        $this->actingAs($this->buyer)->post(route('booking.reschedule', $b), ['date' => '2026-11-27', 'start_time' => '14:00'])->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('2026-11-27 14:00:00', $b->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $b->reschedule_count);

        $this->post(route('booking.reschedule', $b), ['date' => '2026-11-28', 'start_time' => '14:00'])->assertSessionHas('error');
        $this->assertSame('2026-11-27', $b->fresh()->starts_at->toDateString());
    }

    public function test_L15_reschedule_to_taken_slot_or_too_late_fails_and_keeps_original(): void
    {
        $s = $this->space();
        $this->booking($s, 'confirmed', 'paid', '2026-11-27 14:00', 2, ['user_id' => $this->other->id]);
        $b = $this->booking($s, 'confirmed', 'paid', '2026-11-25 10:00');
        $this->actingAs($this->buyer)->post(route('booking.reschedule', $b), ['date' => '2026-11-27', 'start_time' => '15:00'])->assertSessionHas('error');
        $this->assertSame('2026-11-25', $b->fresh()->starts_at->toDateString());

        $soon = $this->booking($s, 'confirmed', 'paid', '2026-11-16 20:00');
        $this->post(route('booking.reschedule', $soon), ['date' => '2026-11-30', 'start_time' => '10:00'])->assertSessionHas('error');
    }

    // ---------- Ulasan ----------

    private function completed(Space $s, ?User $u = null): Booking
    {
        return $this->booking($s, 'completed', 'paid', '2026-11-10 10:00', 2, ['user_id' => ($u ?? $this->buyer)->id]);
    }

    public function test_L16_review_rules_completed_only_once_owner_only(): void
    {
        $s = $this->space();
        $confirmed = $this->booking($s, 'confirmed', 'paid', '2026-11-25 10:00');
        $this->actingAs($this->buyer)->post(route('reviews.store', $confirmed), ['rating' => 5])->assertSessionHas('error');
        $this->assertSame(0, Review::count());

        $done = $this->completed($s);
        $this->actingAs($this->other)->post(route('reviews.store', $done), ['rating' => 5])->assertForbidden();
        $this->actingAs($this->buyer)->post(route('reviews.store', $done), ['rating' => 4, 'comment' => 'Nyaman'])->assertSessionHas('success');
        $this->post(route('reviews.store', $done), ['rating' => 1])->assertSessionHas('error');
        $this->assertSame(1, Review::count());
    }

    public function test_L17_review_photos_limits_and_mime(): void
    {
        Storage::fake('public');
        $s = $this->space();
        $done = $this->completed($s);

        $this->actingAs($this->buyer)->post(route('reviews.store', $done), [
            'rating' => 5, 'photos' => [UploadedFile::fake()->create('x.php', 10, 'application/x-php')],
        ])->assertSessionHasErrors('photos.0');

        $five = array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg"), range(1, 5));
        $this->post(route('reviews.store', $done), ['rating' => 5, 'photos' => $five])->assertSessionHasErrors('photos');
        $this->assertSame(0, Review::count());

        $two = [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')];
        $this->post(route('reviews.store', $done), ['rating' => 5, 'comment' => 'Bagus', 'photos' => $two])->assertSessionHas('success');
        $review = Review::first();
        $this->assertCount(2, $review->photos);
        Storage::disk('public')->assertExists($review->photos->first()->path);
    }

    public function test_L18_anonymous_review_hides_identity_publicly_but_keeps_it(): void
    {
        $s = $this->space();
        $this->buyer->forceFill(['name' => 'Rahasia Pembeli'])->save();
        $done = $this->completed($s);
        $this->actingAs($this->buyer)->post(route('reviews.store', $done), ['rating' => 5, 'comment' => 'Mantap', 'is_anonymous' => 1]);

        $this->assertSame($this->buyer->id, Review::first()->user_id);
        $this->app['auth']->guard('web')->logout(); // lihat sebagai tamu: nama pembeli tidak boleh tampil
        $this->get(route('spaces.show', $s))->assertSee('Pengguna SpaceRent')->assertDontSee('Rahasia Pembeli');
    }

    public function test_L19_rating_is_computed_and_new_space_has_none(): void
    {
        $s = $this->space();
        $this->get(route('spaces.show', $s))->assertSee('Baru');
        $this->assertFalse($s->fresh()->hasRating());

        $a = $this->completed($s);
        $b = $this->completed($s, $this->other);
        $this->actingAs($this->buyer)->post(route('reviews.store', $a), ['rating' => 5]);
        $this->actingAs($this->other)->post(route('reviews.store', $b), ['rating' => 3]);
        $s->refresh();
        $this->assertSame(4.0, $s->rating);
        $this->assertSame(2, $s->rating_count);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.reviews.toggle', Review::where('rating', 3)->first()));
        $s->refresh();
        $this->assertSame(5.0, $s->rating);
        $this->assertSame(1, $s->rating_count);
    }

    public function test_L20_seller_replies_once_and_xss_is_escaped(): void
    {
        $s = $this->space();
        $done = $this->completed($s);
        $this->actingAs($this->buyer)->post(route('reviews.store', $done), ['rating' => 5, 'comment' => '<script>alert(1)</script>']);
        $review = Review::first();

        $this->actingAs($this->seller)->post(route('seller.reviews.reply', $review), ['reply' => 'Terima kasih'])->assertSessionHas('success');
        $this->post(route('seller.reviews.reply', $review), ['reply' => 'Lagi'])->assertSessionHas('error');
        $this->assertSame('Terima kasih', $review->fresh()->reply);

        $this->get(route('spaces.show', $s))->assertDontSee('<script>alert(1)</script>', false)->assertSee('Terima kasih');
    }

    // ---------- Anti penyalahgunaan dan sistem ----------

    public function test_L21_pending_cap_prevents_slot_hoarding(): void
    {
        $s = $this->space();
        foreach (['08:00', '10:00', '12:00', '14:00', '16:00'] as $t) {
            $this->book($s, ['start_time' => $t, 'duration' => 1]);
        }
        $this->assertSame(3, Booking::where('user_id', $this->buyer->id)->count());
    }

    public function test_L22_reminders_sent_once(): void
    {
        $s = $this->space();
        $this->booking($s, 'confirmed', 'paid', '2026-11-16 20:00'); // 17 jam lagi
        $this->artisan('bookings:remind')->assertSuccessful();
        $this->artisan('bookings:remind')->assertSuccessful();
        Mail::assertSent(BookingNotice::class, fn ($m) => str_contains($m->title, 'besok'));
        $this->assertNotNull(Booking::first()->reminded_day_at);
    }

    public function test_L23_complete_command_marks_done_and_requests_review(): void
    {
        $s = $this->space();
        $b = $this->booking($s, 'confirmed', 'paid', '2026-11-15 10:00');
        $this->artisan('bookings:complete')->assertSuccessful();
        $this->assertSame('completed', $b->fresh()->status);
        Mail::assertSent(BookingNotice::class, fn ($m) => str_contains($m->title, 'pengalaman'));
        $this->assertTrue($b->fresh()->isReviewable());
    }

    public function test_L24_late_payment_revives_system_cancelled_booking_only_if_slot_free(): void
    {
        $s = $this->space();
        $expired = $this->booking($s, 'cancelled', 'expired', '2026-11-25 10:00', 2, ['cancelled_by' => 'system', 'order_id' => 'O1']);
        $this->assertSame('confirmed', $this->settle($expired));

        $taken = $this->booking($s, 'cancelled', 'expired', '2026-11-26 10:00', 2, ['cancelled_by' => 'system', 'order_id' => 'O2']);
        $this->booking($s, 'confirmed', 'paid', '2026-11-26 10:00', 2, ['user_id' => $this->other->id]);
        $this->assertSame('refund_pending', $this->settle($taken));
        $this->assertSame((int) $taken->fresh()->total, (int) $taken->fresh()->refund_amount);
    }

    private function settle(Booking $b): string
    {
        $out = app(BookingService::class)->settlePayment($b);

        return $out === 'confirmed' ? $b->fresh()->status : $out;
    }

    public function test_L25_admin_cannot_archive_paid_or_active_booking_and_refund_requires_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $s = $this->space();
        $paid = $this->booking($s, 'confirmed', 'paid', '2026-11-25 10:00');
        $this->actingAs($admin)->delete(route('admin.bookings.destroy', $paid))->assertSessionHas('error');
        $this->assertNotSoftDeleted('bookings', ['id' => $paid->id]);

        $this->post(route('admin.bookings.refund', $paid))->assertSessionHas('error');
        $this->post(route('admin.bookings.cancel', $paid));
        $this->assertSame('refund_pending', $paid->fresh()->payment_status);
        $this->post(route('admin.bookings.refund', $paid))->assertSessionHas('success');
        $this->assertSame('refunded', $paid->fresh()->payment_status);
    }

    public function test_L26_favorites_toggle_and_list(): void
    {
        $s = $this->space();
        $this->actingAs($this->buyer)->post(route('favorites.toggle', $s));
        $this->assertSame(1, Favorite::count());
        $this->get(route('buyer.favorites'))->assertSee($s->name);
        $this->post(route('favorites.toggle', $s));
        $this->assertSame(0, Favorite::count());
    }

    public function test_L27_availability_endpoint_returns_unavailable_slots_and_blocked_dates(): void
    {
        $s = $this->space(['buffer_minutes' => 30]);
        $this->booking($s, 'confirmed', 'paid', '2026-11-25 10:00');
        SpaceBlackout::create(['space_id' => $s->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

        $json = $this->getJson(route('spaces.availability', $s).'?date=2026-11-25&duration=1')->assertOk()->json();
        $slots = collect($json['slots'])->keyBy('time');
        $this->assertFalse($slots['10:00']['available']);
        $this->assertFalse($slots['12:00']['available']);   // tepat setelah selesai, belum ada jeda 30 menit
        $this->assertTrue($slots['12:30']['available']);    // jeda tepat 30 menit sudah cukup
        $this->assertFalse($slots['09:00']['available']);   // 09:00-10:00 menempel sebelum booking, tanpa jeda
        $this->assertContains('2026-12-01', $json['blocked']);
    }

    public function test_L28_homepage_date_filter_hides_closed_spaces_and_sorts(): void
    {
        $open = $this->space(['name' => 'Ruang Buka']);
        $closed = $this->space(['name' => 'Ruang Tutup']);
        SpaceBlackout::create(['space_id' => $closed->id, 'starts_on' => '2026-11-25', 'ends_on' => '2026-11-25']);

        $this->get(route('home', ['date' => '2026-11-25']))->assertSee('Ruang Buka')->assertDontSee('Ruang Tutup');
        $this->get(route('home', ['sort' => 'price_desc']))->assertOk();
    }

    public function test_L29_deleted_space_keeps_booking_history_visible(): void
    {
        $s = $this->space();
        $b = $this->booking($s, 'completed', 'paid', '2026-11-10 10:00');
        $s->delete();
        $this->actingAs($this->buyer)->get(route('booking.show', $b))->assertOk()->assertSee($s->name);
    }
}
