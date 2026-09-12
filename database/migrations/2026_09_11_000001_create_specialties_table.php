<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('specialties')) {
            Schema::create('specialties', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('doctor_profiles') && ! Schema::hasColumn('doctor_profiles', 'specialty_id')) {
            Schema::table('doctor_profiles', function (Blueprint $table): void {
                $table->foreignId('specialty_id')->nullable()->after('license_number')->constrained('specialties')->nullOnDelete();
            });
        }

        foreach ([
            'Medicina general veterinaria',
            'Cirugía veterinaria',
            'Dermatología veterinaria',
            'Medicina interna',
            'Traumatología y ortopedia veterinaria',
            'Oftalmología veterinaria',
            'Cardiología veterinaria',
            'Diagnóstico por imágenes',
            'Animales menores',
            'Medicina preventiva',
        ] as $name) {
            if (! DB::table('specialties')->where('name', $name)->exists()) {
                DB::table('specialties')->insert([
                    'name' => $name,
                    'description' => 'Especialidad veterinaria de referencia para pruebas y configuración inicial.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('doctor_profiles') && Schema::hasColumn('doctor_profiles', 'specialty_id')) {
            Schema::table('doctor_profiles', function (Blueprint $table): void {
                $table->dropForeign(['specialty_id']);
                $table->dropColumn('specialty_id');
            });
        }

        Schema::dropIfExists('specialties');
    }
};
