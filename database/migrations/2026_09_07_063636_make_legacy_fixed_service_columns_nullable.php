<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE fixed_services
            MODIFY bus_name VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY bus_number VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY origin VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY destination VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY contact_number_1 VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY contact_number_2 VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE fixed_services
            MODIFY contact_number_3 VARCHAR(255) NULL
        ");
    }

    public function down(): void
    {
        /*
         * Do not make legacy fields required again.
         *
         * Current architecture uses:
         * operator_id
         * route_id
         * bus_id
         * service_name
         */
    }
};