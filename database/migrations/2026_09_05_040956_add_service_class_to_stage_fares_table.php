<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_fares', function (Blueprint $table) {
            $table->string('service_class', 30)
                ->default('normal')
                ->after('id');

            $table->date('effective_from')
                ->nullable()
                ->after('fare');

            $table->dropUnique(['stage_no']);

            $table->unique(
                ['service_class', 'stage_no'],
                'stage_fares_class_stage_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stage_fares', function (Blueprint $table) {
            $table->dropUnique('stage_fares_class_stage_unique');

            $table->dropColumn([
                'service_class',
                'effective_from',
            ]);

            $table->unique('stage_no');
        });
    }
};