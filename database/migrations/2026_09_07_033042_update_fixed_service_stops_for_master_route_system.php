<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_service_stops', function (Blueprint $table) {

            if (!Schema::hasColumn('fixed_service_stops', 'route_stop_id')) {
                $table->foreignId('route_stop_id')
                    ->nullable()
                    ->after('fixed_service_id')
                    ->constrained('route_stops')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('fixed_service_stops', 'direction')) {
                $table->string('direction', 20)
                    ->default('starting')
                    ->after('route_stop_id');
            }

            if (!Schema::hasColumn('fixed_service_stops', 'stop_order')) {
                $table->unsignedInteger('stop_order')
                    ->default(1)
                    ->after('direction');
            }

            if (!Schema::hasColumn('fixed_service_stops', 'arrival_time')) {
                $table->time('arrival_time')
                    ->nullable()
                    ->after('stop_order');
            }

            if (!Schema::hasColumn('fixed_service_stops', 'departure_time')) {
                $table->time('departure_time')
                    ->nullable()
                    ->after('arrival_time');
            }

            if (!Schema::hasColumn('fixed_service_stops', 'boarding_allowed')) {
                $table->boolean('boarding_allowed')
                    ->default(true)
                    ->after('departure_time');
            }

            if (!Schema::hasColumn('fixed_service_stops', 'dropoff_allowed')) {
                $table->boolean('dropoff_allowed')
                    ->default(true)
                    ->after('boarding_allowed');
            }
        });
    }

    public function down(): void
    {
        // Keep existing data safe.
        // Do not automatically remove these columns on rollback.
    }
};