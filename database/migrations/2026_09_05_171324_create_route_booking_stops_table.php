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
        Schema::create('route_booking_stops', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Route
            |--------------------------------------------------------------------------
            |
            | The route this booking point belongs to.
            |
            */
            $table->foreignId('route_id')
                ->constrained('routes')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Road Way Stop
            |--------------------------------------------------------------------------
            |
            | Booking points must come from the full road-way stops
            | already stored in route_stops.
            |
            */
            $table->foreignId('route_stop_id')
                ->constrained('route_stops')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Direction
            |--------------------------------------------------------------------------
            |
            | starting = Origin -> Destination
            | return   = Destination -> Origin
            |
            */
            $table->enum('direction', [
                'starting',
                'return',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Stop Order
            |--------------------------------------------------------------------------
            |
            | Order of this booking point within the selected direction.
            |
            */
            $table->unsignedInteger('stop_order');

            /*
            |--------------------------------------------------------------------------
            | Timetable
            |--------------------------------------------------------------------------
            |
            | Starting/Return timetable time for this booking point.
            |
            */
            $table->time('schedule_time');

            /*
            |--------------------------------------------------------------------------
            | NTC Fare Stage
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | Batticaloa   = 81
            | Akkaraipattu = 112
            |
            | Difference = abs(112 - 81) = 31
            |
            */
            $table->unsignedInteger('fare_stage_no');

            /*
            |--------------------------------------------------------------------------
            | Distance
            |--------------------------------------------------------------------------
            |
            | Used to enforce the minimum 50 km online-booking journey.
            |
            */
            $table->decimal(
                'distance_from_origin',
                8,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Passenger Booking Permission
            |--------------------------------------------------------------------------
            |
            | Allows future flexibility if a stop should only support
            | boarding or only support drop-off.
            |
            */
            $table->boolean('boarding_allowed')
                ->default(true);

            $table->boolean('dropoff_allowed')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Active Status
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes / Constraints
            |--------------------------------------------------------------------------
            */

            // A road-way stop can appear only once in each direction.
            $table->unique(
                [
                    'route_id',
                    'route_stop_id',
                    'direction',
                ],
                'route_booking_stop_direction_unique'
            );

            // Stop order must be unique inside each direction.
            $table->unique(
                [
                    'route_id',
                    'direction',
                    'stop_order',
                ],
                'route_booking_stop_order_unique'
            );

            $table->index([
                'route_id',
                'direction',
                'is_active',
            ]);

            $table->index('fare_stage_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'route_booking_stops'
        );
    }
};