<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouse_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->noActionOnDelete();
            $table->string('item_type', 50);
            $table->unsignedBigInteger('item_id');
            $table->bigInteger('quantity')->default(0);
            $table->unsignedBigInteger('minimum_stock')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'item_type', 'item_id']);
            $table->index(['item_type', 'item_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('medication_id')->nullable()->change();
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained()->noActionOnDelete();
            $table->string('item_type', 50)->nullable()->after('warehouse_id');
            $table->unsignedBigInteger('item_id')->nullable()->after('item_type');
            $table->string('movement_type', 30)->nullable()->after('item_id');
            $table->integer('balance_before')->nullable()->after('quantity');
            $table->integer('balance_after')->nullable()->after('balance_before');
            $table->string('reference_type', 100)->nullable()->after('reason');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(['item_type', 'item_id']);
            $table->index(['warehouse_id', 'created_at']);
        });

        DB::table('warehouses')->insert([
            ['code' => 'CLINIC', 'name' => 'Almacén Clínica', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'STORE', 'name' => 'Almacén Tienda', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $clinicId = DB::table('warehouses')->where('code', 'CLINIC')->value('id');
        foreach (['medication' => 'medications', 'product' => 'products'] as $itemType => $table) {
            DB::table($table)->select(['id', 'stock', 'minimum_stock'])->orderBy('id')->get()->each(function (object $item) use ($clinicId, $itemType): void {
                DB::table('warehouse_stocks')->insert([
                    'warehouse_id' => $clinicId,
                    'item_type' => $itemType,
                    'item_id' => $item->id,
                    'quantity' => $item->stock,
                    'minimum_stock' => $item->minimum_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['item_type', 'item_id']);
            $table->dropIndex(['warehouse_id', 'created_at']);
            $table->dropColumn(['warehouse_id', 'item_type', 'item_id', 'movement_type', 'balance_before', 'balance_after', 'reference_type', 'reference_id']);
        });
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
    }
};
