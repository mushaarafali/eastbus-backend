<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('fixed_service_id')
                ->nullable()
                ->after('route_id')
                ->constrained('fixed_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign([
                'fixed_service_id'
            ]);

            $table->dropColumn(
                'fixed_service_id'
            );
        });
    }
};