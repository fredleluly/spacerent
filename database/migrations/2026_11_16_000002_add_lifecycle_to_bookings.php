<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('cleaning_fee')->default(0);
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->string('cancellation_policy', 20)->default('moderate'); // snapshot saat dipesan
            $table->timestamp('approval_deadline_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();   // buyer|seller|admin|system
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->string('refund_status', 20)->default('none'); // none|pending|refunded
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->timestamp('reminded_week_at')->nullable();
            $table->timestamp('reminded_day_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['subtotal', 'cleaning_fee', 'service_fee', 'cancellation_policy', 'approval_deadline_at',
                'cancelled_at', 'cancelled_by', 'cancel_reason', 'refund_amount', 'refund_status', 'reschedule_count',
                'reminded_week_at', 'reminded_day_at']);
        });
    }
};
