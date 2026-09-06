<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_services', function (Blueprint $table) {

            if (!Schema::hasColumn('fixed_services', 'operator_id')) {
                $table->foreignId('operator_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('operators')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('fixed_services', 'route_id')) {
                $table->foreignId('route_id')
                    ->nullable()
                    ->after('operator_id')
                    ->constrained('routes')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('fixed_services', 'bus_id')) {
                $table->foreignId('bus_id')
                    ->nullable()
                    ->after('route_id')
                    ->constrained('buses')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('fixed_services', 'service_name')) {
                $table->string('service_name', 150)
                    ->nullable()
                    ->after('bus_id');
            }

            if (!Schema::hasColumn('fixed_services', 'starting_time')) {
                $table->time('starting_time')
                    ->nullable()
                    ->after('service_name');
            }

            if (!Schema::hasColumn('fixed_services', 'return_time')) {
                $table->time('return_time')
                    ->nullable()
                    ->after('starting_time');
            }
        });
    }

    public function down(): void
    {
        /*
         * Do not remove these columns automatically.
         *
         * They are now part of the current
         * Master Route + Fixed Service architecture.
         */
    }
};