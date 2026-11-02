<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('buyer')->after('password'); // admin/seller/buyer
            $table->string('phone')->nullable()->after('email');
        });
        // Semua user yang sudah ada sebelum fitur ini adalah admin.
        DB::table('users')->update(['role' => 'admin']);

        Schema::table('spaces', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // penjual pemilik
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pembeli
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pembeli
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('spaces', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone']));
    }
};
