<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_fares', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('stage_no')->unique();
            $table->decimal('fare', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_fares');
    }
};