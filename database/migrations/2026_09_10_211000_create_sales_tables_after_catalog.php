<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table): void {
                $table->id();
                $table->string('sale_number', 30)->unique();
                $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
                $table->foreignId('seller_user_id')->constrained('users')->noActionOnDelete();
                $table->string('status', 30)->default('COMPLETED');
                $table->string('payment_method', 30)->nullable();
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->dateTime('sold_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sale_items')) {
            Schema::create('sale_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->noActionOnDelete();
                $table->unsignedInteger('quantity');
                $table->unsignedBigInteger('unit_price_cents');
                $table->unsignedBigInteger('subtotal_cents');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
