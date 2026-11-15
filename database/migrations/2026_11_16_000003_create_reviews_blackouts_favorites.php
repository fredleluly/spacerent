<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');                 // inklusif
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'starts_on', 'ends_on']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('cleanliness')->nullable();
            $table->unsignedTinyInteger('accuracy')->nullable();
            $table->unsignedTinyInteger('location')->nullable();
            $table->unsignedTinyInteger('facilities')->nullable();
            $table->unsignedTinyInteger('value')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 12)->default('published'); // published|hidden
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedSmallInteger('reported_count')->default(0);
            $table->timestamps();
            $table->index(['space_id', 'status', 'created_at']);
        });

        Schema::create('review_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('review_photos');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('space_blackouts');
    }
};
