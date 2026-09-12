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

        $profileId = DB::table('profiles')->where('code', 'ALMACEN')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'productos.ver')->value('id');

        if ($profileId && $permissionId) {
            DB::table('profile_permissions')->where('profile_id', $profileId)->where('permission_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $profileId = DB::table('profiles')->where('code', 'ALMACEN')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'productos.ver')->value('id');

        if ($profileId && $permissionId && ! DB::table('profile_permissions')->where('profile_id', $profileId)->where('permission_id', $permissionId)->exists()) {
            DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'permission_id' => $permissionId]);
        }
    }
};
