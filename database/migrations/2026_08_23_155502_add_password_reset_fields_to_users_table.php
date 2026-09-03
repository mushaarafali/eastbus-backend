<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'password_reset_otp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string(
                    'password_reset_otp',
                    6
                )->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'password_reset_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp(
                    'password_reset_expires_at'
                )->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_reset_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(
                    'password_reset_expires_at'
                );
            });
        }

        if (Schema::hasColumn('users', 'password_reset_otp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(
                    'password_reset_otp'
                );
            });
        }
    }
};