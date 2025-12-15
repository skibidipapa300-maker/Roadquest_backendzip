<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id('otp_id');
            $table->unsignedBigInteger('user_id');
            $table->string('otp_code', 6);
            $table->enum('type', ['activation', 'reset']);
            $table->boolean('is_used')->default(false);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};

