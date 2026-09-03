<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_feedback', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('passenger_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('trip_id');
            $table->unsignedBigInteger('bus_id');
            $table->unsignedBigInteger('operator_id')->nullable();

            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('punctuality_rating')->nullable();
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->unsignedTinyInteger('staff_rating')->nullable();
            $table->unsignedTinyInteger('comfort_rating')->nullable();
            $table->unsignedTinyInteger('safety_rating')->nullable();

            $table->boolean('travel_again')->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(
                ['passenger_id', 'booking_id'],
                'unique_passenger_booking_feedback'
            );

            $table->index('trip_id');
            $table->index('bus_id');
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_feedback');
    }
};