<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('route_booking_stops')) {
            Schema::dropIfExists('route_booking_stops');
        }
    }

    public function down(): void
    {
        /*
         * Legacy table is intentionally not recreated.
         *
         * The current EastBus architecture uses:
         *
         * routes
         * route_stops
         * fixed_services
         * fixed_service_stops
         */
    }
};