<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id('car_id');
            $table->string('make', 50);
            $table->string('model', 50);
            $table->integer('year');
            $table->string('license_plate', 20)->unique();
            $table->string('category', 50);
            $table->enum('transmission', ['automatic', 'manual']);
            $table->enum('fuel_type', ['gasoline', 'diesel', 'electric']);
            $table->integer('seat_capacity')->nullable();
            $table->decimal('daily_rate', 10, 2);
            $table->string('image_url')->nullable();
            $table->enum('status', ['available', 'rented', 'maintenance'])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};

