<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileId = DB::table('profiles')->where('code', 'ADMINISTRADOR')->value('id');
        foreach (['ver' => 'Ver solicitudes de trabajadores', 'aprobar' => 'Aprobar solicitudes de trabajadores', 'rechazar' => 'Rechazar, poner en espera y reconsiderar solicitudes'] as $action => $name) {
            $id = DB::table('permissions')->insertGetId(['code' => 'solicitudes_trabajador.'.$action, 'name' => $name, 'module' => 'Solicitudes de trabajadores', 'created_at' => now(), 'updated_at' => now()]);
            if ($profileId) {
                DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', ['solicitudes_trabajador.ver', 'solicitudes_trabajador.aprobar', 'solicitudes_trabajador.rechazar'])->pluck('id');
        DB::table('user_permission_overrides')->whereIn('permission_id', $ids)->delete();
        DB::table('profile_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
