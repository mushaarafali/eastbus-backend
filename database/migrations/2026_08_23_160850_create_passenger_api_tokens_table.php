<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passenger_api_tokens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string(
                'token_hash',
                64
            )->unique();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'passenger_api_tokens'
        );
    }
};