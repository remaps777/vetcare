<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'medicamentos.crear', 'name' => 'Crear medicamentos', 'module' => 'Medicamentos'],
            ['code' => 'medicamentos.editar', 'name' => 'Editar medicamentos', 'module' => 'Medicamentos'],
            ['code' => 'inventario.transferir', 'name' => 'Transferir inventario', 'module' => 'Inventario'],
        ];

        foreach ($permissions as $permission) {
            if (! DB::table('permissions')->where('code', $permission['code'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (['INVENTARIO', 'ADMINISTRADOR'] as $profileCode) {
            $profileId = DB::table('profiles')->where('code', $profileCode)->value('id');
            foreach (['medicamentos.crear', 'medicamentos.editar', 'inventario.transferir'] as $code) {
                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                if (! DB::table('profile_permissions')->where('profile_id', $profileId)->where('permission_id', $permissionId)->exists()) {
                    DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'permission_id' => $permissionId]);
                }

                $doctorId = DB::table('profiles')->where('code', 'DOCTOR')->value('id');
                $inventoryPermissionId = DB::table('permissions')->where('code', 'inventario.ver')->value('id');
                DB::table('profile_permissions')->where('profile_id', $doctorId)->where('permission_id', $inventoryPermissionId)->delete();
            }
        }
    }

    public function down(): void
    {
        $codes = ['medicamentos.crear', 'medicamentos.editar', 'inventario.transferir'];
        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('profile_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
