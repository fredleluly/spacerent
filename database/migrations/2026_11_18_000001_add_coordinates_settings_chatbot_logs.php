<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_line')->nullable(); // alamat lengkap untuk peta dan chatbot
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false); // nilai terenkripsi (mis. API key)
            $table->timestamps();
        });

        Schema::create('chatbot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->string('provider', 20);                // rules|gemini|ollama
            $table->json('tools')->nullable();             // nama tool yang dipanggil
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('is_error')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_logs');
        Schema::dropIfExists('settings');
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['latitude', 'longitude', 'address_line']);
        });
    }
};
