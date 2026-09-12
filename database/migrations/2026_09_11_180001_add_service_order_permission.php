<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'code' => 'ordenes_atencion.crear',
            'name' => 'Crear órdenes de atención',
            'module' => 'Órdenes de atención',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['DOCTOR', 'ADMINISTRADOR'] as $profileCode) {
            $profileId = DB::table('profiles')->where('code', $profileCode)->value('id');
            if ($profileId && ! DB::table('profile_permissions')->where(['profile_id' => $profileId, 'permission_id' => $permissionId])->exists()) {
                DB::table('profile_permissions')->insert([
                    'profile_id' => $profileId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'ordenes_atencion.crear')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('profile_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('user_permission_overrides')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
