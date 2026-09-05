<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->string('passenger_name')
                ->nullable()
                ->change();

            $table->string('nic')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->string('passenger_name')
                ->nullable(false)
                ->change();

            $table->string('nic')
                ->nullable(false)
                ->change();
        });
    }
};