<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('owners')->noActionOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->noActionOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctor_profiles')->nullOnDelete();
            $table->string('origin', 30);
            $table->string('status', 30)->default('OPEN');
            $table->string('payment_status', 30)->default('PENDING');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX service_orders_appointment_unique ON service_orders (appointment_id) WHERE appointment_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
