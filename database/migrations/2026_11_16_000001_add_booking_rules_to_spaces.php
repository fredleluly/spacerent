<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->string('open_time', 5)->default('08:00');
            $table->string('close_time', 5)->default('22:00');
            $table->json('open_days')->nullable();            // [0..6], 0 = Minggu; null = setiap hari
            $table->unsignedSmallInteger('min_duration')->default(1);
            $table->unsignedSmallInteger('max_duration')->nullable();
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('lead_time_hours')->default(2);
            $table->unsignedSmallInteger('max_advance_days')->default(365);
            $table->boolean('instant_book')->default(true);
            $table->string('cancellation_policy', 20)->default('moderate'); // flexible|moderate|strict
            $table->unsignedInteger('cleaning_fee')->default(0);
            $table->text('rules')->nullable();
            $table->text('checkin_info')->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->softDeletes();
        });

        // Rating lama adalah angka statis buatan; ulasan nyata yang menentukan rating mulai sekarang.
        DB::table('spaces')->update(['rating' => 0]);
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['open_time', 'close_time', 'open_days', 'min_duration', 'max_duration', 'buffer_minutes',
                'lead_time_hours', 'max_advance_days', 'instant_book', 'cancellation_policy', 'cleaning_fee', 'rules',
                'checkin_info', 'rating_count']);
        });
    }
};
