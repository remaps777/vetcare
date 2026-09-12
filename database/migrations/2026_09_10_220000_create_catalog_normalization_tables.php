<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table): void {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('business_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('applies_to', 20);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'applies_to']);
            $table->index(['applies_to', 'is_active']);
        });

        foreach ([
            ['Alimento', 'PRODUCT'], ['Accesorio', 'PRODUCT'], ['Juguete', 'PRODUCT'],
            ['Higiene', 'PRODUCT'], ['Cuidado', 'PRODUCT'], ['Antipulgas', 'BOTH'], ['Suplemento nutricional', 'PRODUCT'],
            ['Antibiótico', 'MEDICATION'], ['Analgésico', 'MEDICATION'], ['Antiinflamatorio', 'MEDICATION'],
            ['Antiparasitario', 'BOTH'], ['Antiséptico', 'MEDICATION'], ['Antifúngico', 'MEDICATION'], ['Oftálmico', 'MEDICATION'],
        ] as [$name, $appliesTo]) {
            DB::table('item_types')->insert(['name' => $name, 'applies_to' => $appliesTo, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['products', 'medications'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('distributor_id')->nullable()->after('id')->constrained('distributors')->nullOnDelete();
                $table->foreignId('item_type_id')->nullable()->after('name')->constrained('item_types')->nullOnDelete();
                $table->decimal('presentation_quantity', 12, 3)->nullable()->after('presentation');
                $table->string('presentation_unit', 30)->nullable()->after('presentation_quantity');
                $table->index(['distributor_id', 'item_type_id']);
            });
        }

        $types = DB::table('item_types')->get();
        foreach (['products' => 'PRODUCT'] as $tableName => $scope) {
            foreach (DB::table($tableName)->whereNotNull('type')->get(['id', 'type']) as $record) {
                $match = $types->first(fn ($type) => $type->name === $record->type && in_array($type->applies_to, [$scope, 'BOTH'], true));
                if ($match) {
                    DB::table($tableName)->where('id', $record->id)->update(['item_type_id' => $match->id]);
                }
            }
        }

        $permissions = [
            ['distribuidores.ver', 'Ver distribuidores', 'Configuración'],
            ['distribuidores.crear', 'Crear distribuidores', 'Configuración'],
            ['distribuidores.editar', 'Editar distribuidores', 'Configuración'],
            ['tipos_articulo.ver', 'Ver tipos de artículo', 'Configuración'],
            ['tipos_articulo.crear', 'Crear tipos de artículo', 'Configuración'],
            ['tipos_articulo.editar', 'Editar tipos de artículo', 'Configuración'],
        ];
        foreach ($permissions as [$code, $name, $module]) {
            if (! DB::table('permissions')->where('code', $code)->exists()) {
                DB::table('permissions')->insert(['code' => $code, 'name' => $name, 'module' => $module, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $permissionIds = DB::table('permissions')->whereIn('code', array_column($permissions, 0))->pluck('id', 'code');
        foreach (['ADMINISTRADOR', 'INVENTARIO'] as $profileCode) {
            $profileId = DB::table('profiles')->where('code', $profileCode)->value('id');
            foreach ($permissionIds as $permissionId) {
                if ($profileId && ! DB::table('profile_permissions')->where(['profile_id' => $profileId, 'permission_id' => $permissionId])->exists()) {
                    DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'permission_id' => $permissionId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'medications'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropForeign(['distributor_id']);
                    $table->dropForeign(['item_type_id']);
                    $table->dropColumn(['distributor_id', 'item_type_id', 'presentation_quantity', 'presentation_unit']);
                });
            }
        }
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('distributors');
    }
};
