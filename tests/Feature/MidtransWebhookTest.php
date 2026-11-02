<?php

namespace Tests\Feature;

use App\Mail\BookingPaid;
use App\Models\Booking;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['midtrans.server_key' => $this->serverKey]);
    }

    private function booking(): Booking
    {
        $space = Space::forceCreate([
            'name' => 'Ruang Test', 'slug' => 'ruang-test', 'category' => 'meeting-room', 'area' => 'Jakarta Pusat',
            'district' => 'Sudirman', 'capacity' => 10, 'price' => 100000, 'price_unit' => 'jam', 'image' => 'x.jpg',
            'description' => 'x',
        ]);

        return Booking::forceCreate([
            'code' => 'SR-TEST01', 'space_id' => $space->id, 'name' => 'Budi', 'email' => 'budi@example.com',
            'phone' => '0812', 'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'duration' => 2,
            'guests' => 3, 'total' => 200000, 'status' => 'pending', 'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(30), 'order_id' => 'SR-TEST01-1',
        ]);
    }

    private function payload(array $over = [], bool $sign = true): array
    {
        $p = array_merge([
            'order_id' => 'SR-TEST01-1', 'status_code' => '200', 'gross_amount' => '200000.00',
            'transaction_status' => 'settlement', 'payment_type' => 'qris', 'fraud_status' => 'accept',
        ], $over);
        if ($sign) {
            $p['signature_key'] = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$this->serverKey);
        }

        return $p;
    }

    public function test_settlement_marks_paid_and_confirmed(): void
    {
        Mail::fake();
        $b = $this->booking();

        $this->postJson('/midtrans/notification', $this->payload())->assertOk();

        $b->refresh();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('qris', $b->payment_type);
        $this->assertNotNull($b->paid_at);
        Mail::assertSent(BookingPaid::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $b = $this->booking();
        $p = $this->payload();
        $p['signature_key'] = 'bad';

        $this->postJson('/midtrans/notification', $p)->assertForbidden();
        $this->assertSame('unpaid', $b->refresh()->payment_status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $b = $this->booking();

        $this->postJson('/midtrans/notification', $this->payload(['gross_amount' => '1000.00']))->assertStatus(400);
        $this->assertSame('unpaid', $b->refresh()->payment_status);
    }

    public function test_paid_is_never_downgraded_and_mail_sent_once(): void
    {
        Mail::fake();
        $b = $this->booking();

        $this->postJson('/midtrans/notification', $this->payload())->assertOk();
        $this->postJson('/midtrans/notification', $this->payload(['transaction_status' => 'pending']))->assertOk();
        $this->postJson('/midtrans/notification', $this->payload(['transaction_status' => 'expire']))->assertOk();
        $this->postJson('/midtrans/notification', $this->payload())->assertOk();

        $b->refresh();
        $this->assertSame('paid', $b->payment_status);
        $this->assertSame('confirmed', $b->status);
        Mail::assertSent(BookingPaid::class, 1);
    }

    public function test_expire_cancels_booking(): void
    {
        $b = $this->booking();

        $this->postJson('/midtrans/notification', $this->payload(['transaction_status' => 'expire', 'status_code' => '202']))->assertOk();

        $b->refresh();
        $this->assertSame('expired', $b->payment_status);
        $this->assertSame('cancelled', $b->status);
    }

    public function test_pending_keeps_booking_waiting_and_stores_payment_type(): void
    {
        $b = $this->booking();

        $this->postJson('/midtrans/notification', $this->payload(['transaction_status' => 'pending', 'status_code' => '201', 'payment_type' => 'bank_transfer']))->assertOk();

        $b->refresh();
        $this->assertSame('unpaid', $b->payment_status);
        $this->assertSame('pending', $b->status);
        $this->assertSame('bank_transfer', $b->payment_type);
    }

    public function test_unknown_order_id_returns_404(): void
    {
        $this->booking();

        $this->postJson('/midtrans/notification', $this->payload(['order_id' => 'NOPE-1']))->assertNotFound();
    }
}
