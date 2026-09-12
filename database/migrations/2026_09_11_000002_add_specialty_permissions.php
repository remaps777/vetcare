<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionCodes = [
            ['especialidades.ver', 'Ver especialidades', 'Configuración'],
            ['especialidades.crear', 'Crear especialidades', 'Configuración'],
            ['especialidades.editar', 'Editar especialidades', 'Configuración'],
        ];

        foreach ($permissionCodes as [$code, $name, $module]) {
            if (! DB::table('permissions')->where('code', $code)->exists()) {
                DB::table('permissions')->insert([
                    'code' => $code,
                    'name' => $name,
                    'module' => $module,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $profileId = DB::table('profiles')->where('code', 'ADMINISTRADOR')->value('id');
        if ($profileId) {
            foreach ($permissionCodes as [$code]) {
                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                if ($permissionId && ! DB::table('profile_permissions')->where(['profile_id' => $profileId, 'permission_id' => $permissionId])->exists()) {
                    DB::table('profile_permissions')->insert([
                        'profile_id' => $profileId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $codes = ['especialidades.ver', 'especialidades.crear', 'especialidades.editar'];
        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('profile_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
