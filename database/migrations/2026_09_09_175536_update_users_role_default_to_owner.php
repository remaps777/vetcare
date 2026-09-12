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
        // 1. Migrar cualquier usuario existente con RECEPCIONISTA a OWNER
        DB::table('users')->where('role', 'RECEPCIONISTA')->update(['role' => 'OWNER']);

        // 2. Manejo de default constraint según el motor de base de datos
        if (DB::getDriverName() === 'sqlsrv') {
            $constraints = DB::select("
                SELECT dc.name
                FROM sys.default_constraints dc
                JOIN sys.columns c ON dc.parent_object_id = c.object_id AND dc.parent_column_id = c.column_id
                WHERE dc.parent_object_id = OBJECT_ID('users') AND c.name = 'role'
            ");

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
            }

            DB::statement("ALTER TABLE [users] ADD CONSTRAINT [DF_users_role_owner] DEFAULT 'OWNER' FOR [role]");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 30)->default('OWNER')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            $constraints = DB::select("
                SELECT dc.name
                FROM sys.default_constraints dc
                JOIN sys.columns c ON dc.parent_object_id = c.object_id AND dc.parent_column_id = c.column_id
                WHERE dc.parent_object_id = OBJECT_ID('users') AND c.name = 'role'
            ");

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE [users] DROP CONSTRAINT [{$constraint->name}]");
            }

            DB::statement("ALTER TABLE [users] ADD CONSTRAINT [DF_users_role_recepcionista] DEFAULT 'RECEPCIONISTA' FOR [role]");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 30)->default('RECEPCIONISTA')->change();
            });
        }
    }
};
