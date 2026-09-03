<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id(); $table->string('name',120)->index(); $table->string('district',100)->nullable();
                $table->string('province',100)->nullable(); $table->decimal('latitude',10,7); $table->decimal('longitude',10,7);
                $table->boolean('is_active')->default(true); $table->timestamps();
                $table->unique(['name','latitude','longitude'],'locations_name_coordinates_unique');
            });
        }
        if (Schema::hasTable('route_stops')) {
            Schema::table('route_stops', function (Blueprint $table) {
                if (!Schema::hasColumn('route_stops','booking_radius_km')) $table->decimal('booking_radius_km',5,2)->default(20)->after('longitude');
            });
        }
        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table) {
                if (!Schema::hasColumn('trips','booking_closed_at')) $table->timestamp('booking_closed_at')->nullable()->after('started_at');
                if (!Schema::hasColumn('trips','seat_snapshot_json')) $table->longText('seat_snapshot_json')->nullable()->after('booking_closed_at');
                if (!Schema::hasColumn('trips','trip_start_booked_seats')) $table->unsignedSmallInteger('trip_start_booked_seats')->nullable()->after('seat_snapshot_json');
                if (!Schema::hasColumn('trips','trip_start_available_seats')) $table->unsignedSmallInteger('trip_start_available_seats')->nullable()->after('trip_start_booked_seats');
            });
        }
    }
    public function down(): void {}
};
