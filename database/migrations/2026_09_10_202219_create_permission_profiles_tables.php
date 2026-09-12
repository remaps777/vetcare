<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('module', 80);
            $table->timestamps();
        });

        Schema::create('profile_permissions', function (Blueprint $table): void {
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['profile_id', 'permission_id']);
        });

        Schema::create('user_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('effect', 10);
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('USER');
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->noActionOnDelete();
        });

        $profiles = [
            ['code' => 'PROPIETARIO', 'name' => 'Propietario', 'description' => 'Acceso a mascotas, citas, historial y tienda.'],
            ['code' => 'DOCTOR', 'name' => 'Doctor', 'description' => 'Atención clínica y consultas veterinarias.'],
            ['code' => 'INVENTARIO', 'name' => 'Inventario', 'description' => 'Administración de productos, compras y existencias.'],
            ['code' => 'ALMACEN', 'name' => 'Almacén', 'description' => 'Operaciones autorizadas de almacén y movimientos.'],
            ['code' => 'CAJA', 'name' => 'Caja', 'description' => 'Ventas y cobros del almacén tienda.'],
            ['code' => 'ADMINISTRADOR', 'name' => 'Administrador', 'description' => 'Administración general, usuarios y permisos.'],
        ];
        foreach ($profiles as $profile) {
            DB::table('profiles')->insert([...$profile, 'created_at' => now(), 'updated_at' => now()]);
        }

        $permissions = [
            ['code' => 'pacientes.ver', 'name' => 'Ver pacientes', 'module' => 'Pacientes'],
            ['code' => 'citas.ver', 'name' => 'Ver citas', 'module' => 'Citas'],
            ['code' => 'citas.crear', 'name' => 'Crear citas', 'module' => 'Citas'],
            ['code' => 'consultas.ver', 'name' => 'Ver consultas', 'module' => 'Consultas'],
            ['code' => 'consultas.crear', 'name' => 'Crear consultas', 'module' => 'Consultas'],
            ['code' => 'medicamentos.ver', 'name' => 'Ver medicamentos', 'module' => 'Medicamentos'],
            ['code' => 'medicamentos.dispensar', 'name' => 'Dispensar medicamentos', 'module' => 'Medicamentos'],
            ['code' => 'productos.ver', 'name' => 'Ver productos', 'module' => 'Productos'],
            ['code' => 'productos.crear', 'name' => 'Crear productos', 'module' => 'Productos'],
            ['code' => 'productos.editar', 'name' => 'Editar productos', 'module' => 'Productos'],
            ['code' => 'inventario.ver', 'name' => 'Ver inventario', 'module' => 'Inventario'],
            ['code' => 'inventario.ingreso', 'name' => 'Registrar ingresos', 'module' => 'Inventario'],
            ['code' => 'inventario.ajustar', 'name' => 'Ajustar inventario', 'module' => 'Inventario'],
            ['code' => 'inventario.movimientos.ver', 'name' => 'Ver movimientos', 'module' => 'Inventario'],
            ['code' => 'compras.ver', 'name' => 'Ver compras', 'module' => 'Compras'],
            ['code' => 'compras.crear', 'name' => 'Crear compras', 'module' => 'Compras'],
            ['code' => 'ventas.ver', 'name' => 'Ver ventas', 'module' => 'Ventas'],
            ['code' => 'ventas.crear', 'name' => 'Crear ventas', 'module' => 'Ventas'],
            ['code' => 'ventas.anular', 'name' => 'Anular ventas', 'module' => 'Ventas'],
            ['code' => 'usuarios.ver', 'name' => 'Ver usuarios', 'module' => 'Usuarios'],
            ['code' => 'usuarios.editar', 'name' => 'Editar usuarios', 'module' => 'Usuarios'],
            ['code' => 'usuarios.restaurar_password', 'name' => 'Restaurar contraseñas', 'module' => 'Usuarios'],
            ['code' => 'usuarios.cambiar_permisos', 'name' => 'Cambiar permisos', 'module' => 'Usuarios'],
            ['code' => 'configuracion.ver', 'name' => 'Ver configuración', 'module' => 'Configuración'],
            ['code' => 'configuracion.editar', 'name' => 'Editar configuración', 'module' => 'Configuración'],
            ['code' => 'catalogos.ver', 'name' => 'Ver catálogos', 'module' => 'Catálogos'],
            ['code' => 'catalogos.editar', 'name' => 'Editar catálogos', 'module' => 'Catálogos'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
        }

        $profilePermissions = [
            'PROPIETARIO' => ['pacientes.ver', 'citas.ver', 'consultas.ver', 'productos.ver'],
            'DOCTOR' => ['pacientes.ver', 'citas.ver', 'citas.crear', 'consultas.ver', 'consultas.crear', 'medicamentos.ver', 'medicamentos.dispensar', 'inventario.ver'],
            'INVENTARIO' => ['productos.ver', 'productos.crear', 'productos.editar', 'inventario.ver', 'inventario.ingreso', 'inventario.ajustar', 'inventario.movimientos.ver', 'compras.ver', 'compras.crear', 'medicamentos.ver'],
            'ALMACEN' => ['productos.ver', 'inventario.ver', 'inventario.ingreso', 'inventario.movimientos.ver'],
            'CAJA' => ['productos.ver', 'ventas.ver', 'ventas.crear'],
            'ADMINISTRADOR' => array_column($permissions, 'code'),
        ];
        foreach ($profilePermissions as $profileCode => $permissionCodes) {
            $profileId = DB::table('profiles')->where('code', $profileCode)->value('id');
            foreach ($permissionCodes as $permissionCode) {
                DB::table('profile_permissions')->insert([
                    'profile_id' => $profileId,
                    'permission_id' => DB::table('permissions')->where('code', $permissionCode)->value('id'),
                ]);
            }
        }

        DB::table('users')->orderBy('id')->get()->each(function (object $user): void {
            $profileCode = match ($user->role) {
                'ADMIN' => 'ADMINISTRADOR',
                'DOCTOR' => 'DOCTOR',
                default => 'PROPIETARIO',
            };
            DB::table('users')->where('id', $user->id)->update([
                'account_type' => $user->role === 'DOCTOR' ? 'DOCTOR' : 'USER',
                'profile_id' => DB::table('profiles')->where('code', $profileCode)->value('id'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['profile_id']);
            $table->dropColumn(['account_type', 'profile_id']);
        });
        Schema::dropIfExists('user_permission_overrides');
        Schema::dropIfExists('profile_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('profiles');
    }
};
