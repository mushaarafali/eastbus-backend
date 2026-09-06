<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('operator_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Do not automatically restore NOT NULL
        // because master routes may already exist.
    }
};