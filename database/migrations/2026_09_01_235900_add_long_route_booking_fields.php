<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            if (!Schema::hasColumn('route_stops', 'distance_from_origin_km')) {
                $table->decimal('distance_from_origin_km', 8, 2)->nullable()->after('longitude');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'primary_passenger_name')) {
                $table->string('primary_passenger_name', 150)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'primary_passenger_nic')) {
                $table->string('primary_passenger_nic', 20)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'boarding_stop')) {
                $table->string('boarding_stop', 150)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'dropoff_stop')) {
                $table->string('dropoff_stop', 150)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'journey_distance_km')) {
                $table->decimal('journey_distance_km', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('bookings', 'fare_per_seat')) {
                $table->decimal('fare_per_seat', 10, 2)->nullable();
            }
        });

        Schema::table('booking_passengers', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_passengers', 'gender')) {
                $table->enum('gender', ['male', 'female'])->nullable()->after('seat_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            if (Schema::hasColumn('booking_passengers', 'gender')) {
                $table->dropColumn('gender');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'primary_passenger_name',
                'primary_passenger_nic',
                'boarding_stop',
                'dropoff_stop',
                'journey_distance_km',
                'fare_per_seat',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('route_stops', function (Blueprint $table) {
            if (Schema::hasColumn('route_stops', 'distance_from_origin_km')) {
                $table->dropColumn('distance_from_origin_km');
            }
        });
    }
};
