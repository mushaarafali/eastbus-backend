<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_service_stops', function (Blueprint $table) {
            $table->boolean('boarding_allowed')
                ->default(false)
                ->after('departure_time');

            $table->boolean('dropoff_allowed')
                ->default(false)
                ->after('boarding_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_service_stops', function (Blueprint $table) {
            $table->dropColumn([
                'boarding_allowed',
                'dropoff_allowed',
            ]);
        });
    }
};