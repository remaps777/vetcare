<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('dni', 30)->nullable();
            $table->string('phone', 30)->nullable();
        });
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX users_dni_unique ON users (dni) WHERE dni IS NOT NULL');
        } else {
            Schema::table('users', fn (Blueprint $table) => $table->unique('dni'));
        }
        Schema::create('worker_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('dni', 30)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->unique();
            $table->string('username', 50)->unique();
            $table->string('password')->nullable();
            $table->foreignId('requested_profile_id')->constrained('profiles')->noActionOnDelete();
            $table->foreignId('assigned_profile_id')->nullable()->constrained('profiles')->noActionOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->noActionOnDelete();
            $table->string('license_number', 50)->nullable();
            $table->enum('status', ['PENDING', 'ON_HOLD', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('user_id')->nullable()->constrained()->noActionOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_applications');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_dni_unique');
            $table->dropColumn(['first_name', 'last_name', 'dni', 'phone']);
        });
        // Anonymous audit entries remain valid when reverting this feature.
    }
};
