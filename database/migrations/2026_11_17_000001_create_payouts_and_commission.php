<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_name', 60)->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_account_name', 100)->nullable();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // penjual
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('bookings_count')->default(0);
            $table->string('status', 12)->default('requested'); // requested|paid|rejected
            $table->string('bank_name', 60);
            $table->string('bank_account_number', 40);
            $table->string('bank_account_name', 100);
            $table->string('reference')->nullable();            // no. referensi transfer dari admin
            $table->string('note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(10); // snapshot saat dipesan
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropConstrainedForeignId('payout_id'));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('commission_percent'));
        Schema::dropIfExists('payouts');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['bank_name', 'bank_account_number', 'bank_account_name']));
    }
};
