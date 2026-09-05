<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->unsignedInteger('fare_stage_no')
                ->nullable()
                ->after('stop_order');

            $table->decimal('distance_from_origin', 8, 2)
                ->nullable()
                ->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn([
                'fare_stage_no',
                'distance_from_origin',
            ]);
        });
    }
};