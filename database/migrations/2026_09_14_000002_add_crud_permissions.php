<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['citas.editar', 'Editar citas', 'Citas'],
            ['citas.eliminar', 'Eliminar citas', 'Citas'],
            ['consultas.editar', 'Editar consultas', 'Consultas'],
            ['consultas.eliminar', 'Eliminar consultas', 'Consultas'],
            ['propietarios.crear', 'Crear propietarios', 'Propietarios'],
            ['propietarios.editar', 'Editar propietarios', 'Propietarios'],
            ['propietarios.eliminar', 'Eliminar propietarios', 'Propietarios'],
            ['mascotas.crear', 'Crear mascotas', 'Mascotas'],
            ['mascotas.editar', 'Editar mascotas', 'Mascotas'],
            ['mascotas.eliminar', 'Eliminar mascotas', 'Mascotas'],
        ];

        foreach ($permissions as [$code, $name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'module' => $module, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $adminId = DB::table('profiles')->where('code', 'ADMINISTRADOR')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('code', array_column($permissions, 0))->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('profile_permissions')->updateOrInsert([
                'profile_id' => $adminId,
                'permission_id' => $permissionId,
            ], []);
        }
    }

    public function down(): void
    {
        $codes = ['citas.editar', 'citas.eliminar', 'consultas.editar', 'consultas.eliminar', 'propietarios.crear', 'propietarios.editar', 'propietarios.eliminar', 'mascotas.crear', 'mascotas.editar', 'mascotas.eliminar'];
        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('profile_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
