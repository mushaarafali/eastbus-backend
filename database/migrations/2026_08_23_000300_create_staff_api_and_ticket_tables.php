<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('staff_api_tokens', function(Blueprint $t){$t->id();$t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();$t->string('token_hash',64)->unique();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
  Schema::create('booking_passengers', function(Blueprint $t){$t->id();$t->foreignId('booking_id')->constrained()->cascadeOnDelete();$t->string('passenger_name');$t->string('nic');$t->string('seat_number');$t->timestamp('checked_in_at')->nullable();$t->foreignId('checked_in_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();$t->timestamps();$t->unique(['booking_id','seat_number']);});
  Schema::create('tickets', function(Blueprint $t){$t->id();$t->foreignId('booking_id')->constrained()->cascadeOnDelete();$t->foreignId('booking_passenger_id')->nullable()->constrained('booking_passengers')->cascadeOnDelete();$t->string('ticket_code')->unique();$t->enum('status',['valid','used','cancelled'])->default('valid');$t->timestamp('used_at')->nullable();$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('tickets');Schema::dropIfExists('booking_passengers');Schema::dropIfExists('staff_api_tokens');}
};
