<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->default('VetCare');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedInteger('appointment_minutes')->default(30);
            $table->timestamps();
        });
        DB::table('clinic_settings')->insert(['name' => 'VetCare', 'appointment_minutes' => 30, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('medications', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('presentation', 100);
            $table->bigInteger('purchase_price_cents')->default(0);
            $table->integer('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('medication_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medication_id')->constrained()->noActionOnDelete();
            $table->foreignId('user_id')->constrained()->noActionOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->string('supplier', 150);
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('total_cents');
            $table->date('purchased_at');
            $table->string('batch_number', 50)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medication_id')->constrained()->noActionOnDelete();
            $table->foreignId('user_id')->constrained()->noActionOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->noActionOnDelete();
            $table->integer('quantity');
            $table->integer('balance');
            $table->string('reason', 255);
            $table->timestamps();
        });
        Schema::create('operation_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->noActionOnDelete();
            $table->string('digest', 64);
            $table->dateTime('expires_at')->index();
            $table->dateTime('consumed_at')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete();
            $table->string('action', 255);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->text('before_data')->nullable();
            $table->text('after_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'operation_confirmations', 'stock_movements', 'medication_purchases', 'medications', 'clinic_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
