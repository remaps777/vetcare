<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $inventoryId = DB::table('profiles')->where('code', 'INVENTARIO')->value('id');
        $warehouseId = DB::table('profiles')->where('code', 'ALMACEN')->value('id');

        if (! $inventoryId || ! $warehouseId) {
            return;
        }

        foreach (DB::table('profile_permissions')->where('profile_id', $warehouseId)->pluck('permission_id') as $permissionId) {
            if (! DB::table('profile_permissions')->where('profile_id', $inventoryId)->where('permission_id', $permissionId)->exists()) {
                DB::table('profile_permissions')->insert([
                    'profile_id' => $inventoryId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        DB::table('users')->where('profile_id', $warehouseId)->update(['profile_id' => $inventoryId]);
        DB::table('worker_applications')->where('requested_profile_id', $warehouseId)->update(['requested_profile_id' => $inventoryId]);
        DB::table('worker_applications')->where('assigned_profile_id', $warehouseId)->update(['assigned_profile_id' => $inventoryId]);
        DB::table('profile_permissions')->where('profile_id', $warehouseId)->delete();
        DB::table('profiles')->where('id', $warehouseId)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('profiles') || DB::table('profiles')->where('code', 'ALMACEN')->exists()) {
            return;
        }

        DB::table('profiles')->insert([
            'code' => 'ALMACEN',
            'name' => 'Almacén',
            'description' => 'Perfil legado separado de inventario.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
