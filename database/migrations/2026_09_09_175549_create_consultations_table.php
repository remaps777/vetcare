<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->noActionOnDelete();
            $table->foreignId('doctor_id')->constrained('doctor_profiles')->noActionOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->nullOnDelete();
            $table->dateTime('consulted_at');
            $table->string('reason', 255);
            $table->text('diagnosis');
            $table->text('treatment')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
