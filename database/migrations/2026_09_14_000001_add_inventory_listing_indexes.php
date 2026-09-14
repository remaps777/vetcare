<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->index(['is_active', 'name']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['is_active', 'name']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'item_type', 'created_at']);
            $table->index(['item_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id', 'item_type', 'created_at']);
            $table->dropIndex(['item_type', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'name']);
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'name']);
        });
    }
};
