<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('area')->index();
            $table->string('district');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedInteger('price');
            $table->string('price_unit')->default('jam');
            $table->string('image');
            $table->json('gallery')->nullable();
            $table->text('description');
            $table->json('facilities')->nullable();
            $table->decimal('rating', 2, 1)->default(4.8);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
