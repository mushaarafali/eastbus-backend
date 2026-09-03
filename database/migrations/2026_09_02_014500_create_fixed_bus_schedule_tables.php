<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_services', function (Blueprint $table) {
            $table->id();
            $table->string('bus_name', 150);
            $table->string('bus_number', 50)->nullable();
            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->string('contact_number_1', 30)->nullable();
            $table->string('contact_number_2', 30)->nullable();
            $table->string('contact_number_3', 30)->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_service_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixed_service_id');
            $table->enum('direction', ['starting', 'return']);
            $table->string('stop_name', 150);
            $table->unsignedInteger('stop_order');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->timestamps();

            $table->foreign('fixed_service_id')
                ->references('id')
                ->on('fixed_services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_service_stops');
        Schema::dropIfExists('fixed_services');
    }
};
