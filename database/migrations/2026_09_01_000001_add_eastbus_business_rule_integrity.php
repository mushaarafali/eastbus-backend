<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            DB::statement("
                ALTER TABLE bookings
                MODIFY status ENUM(
                    'pending',
                    'confirmed',
                    'cancelled',
                    'completed',
                    'no_show'
                ) NOT NULL DEFAULT 'pending'
            ");

            Schema::table('bookings', function (Blueprint $table) {
                if (!Schema::hasColumn('bookings', 'hold_expires_at')) {
                    $table->timestamp('hold_expires_at')->nullable()->after('status');
                }

                if (!Schema::hasColumn('bookings', 'ticket_token')) {
                    $table->uuid('ticket_token')->nullable()->unique()->after('payment_status');
                }

                if (!Schema::hasColumn('bookings', 'ticket_status')) {
                    $table->enum('ticket_status', ['valid', 'used', 'cancelled'])
                        ->nullable()
                        ->after('ticket_token');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'payment_method')) {
                    $table->string('payment_method', 30)->nullable();
                }

                if (!Schema::hasColumn('payments', 'transaction_reference')) {
                    $table->string('transaction_reference', 80)->nullable()->unique();
                }

                if (!Schema::hasColumn('payments', 'card_last4')) {
                    $table->string('card_last4', 4)->nullable();
                }

                if (!Schema::hasColumn('payments', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable();
                }

                if (!Schema::hasColumn('payments', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Conservative rollback: existing booking/payment data is not removed automatically.
    }
};
