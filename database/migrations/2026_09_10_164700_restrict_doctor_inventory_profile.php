<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $doctorId = DB::table('profiles')->where('code', 'DOCTOR')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'inventario.ver')->value('id');

        if ($doctorId && $permissionId) {
            DB::table('profile_permissions')->where('profile_id', $doctorId)->where('permission_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $doctorId = DB::table('profiles')->where('code', 'DOCTOR')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'inventario.ver')->value('id');

        if ($doctorId && $permissionId && ! DB::table('profile_permissions')->where('profile_id', $doctorId)->where('permission_id', $permissionId)->exists()) {
            DB::table('profile_permissions')->insert(['profile_id' => $doctorId, 'permission_id' => $permissionId]);
        }
    }
};
