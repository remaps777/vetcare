<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->string('distributor_name', 150)->nullable()->after('presentation');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('distributor_name', 150)->nullable()->after('presentation');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->dropColumn('distributor_name');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('distributor_name');
        });
    }
};
