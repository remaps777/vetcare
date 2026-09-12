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
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('owners')->noActionOnDelete();
            $table->foreignId('species_id')->constrained('species')->noActionOnDelete();
            $table->foreignId('breed_id')->constrained('breeds')->noActionOnDelete();
            $table->string('name', 100);
            $table->string('sex', 10);
            $table->date('birth_date')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('color', 50)->nullable();
            $table->text('observations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
