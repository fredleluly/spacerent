<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status'); // unpaid/paid/failed/expired/refunded
            $table->timestamp('expires_at')->nullable();
            $table->string('order_id')->nullable()->unique();
            $table->string('snap_token')->nullable();
            $table->string('payment_type')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('midtrans_payload')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->index(['space_id', 'starts_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['space_id', 'starts_at', 'end_at']);
            $table->dropColumn(['payment_status', 'expires_at', 'order_id', 'snap_token', 'payment_type', 'paid_at', 'midtrans_payload', 'starts_at', 'end_at']);
        });
    }
};
