<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->unique(
                ['passenger_id', 'booking_id'],
                'trip_feedback_passenger_booking_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->dropUnique(
                'trip_feedback_passenger_booking_unique'
            );
        });
    }
};