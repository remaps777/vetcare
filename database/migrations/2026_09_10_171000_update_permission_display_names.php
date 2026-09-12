<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $names = [
            'inventario.ingreso' => 'Entrada',
            'inventario.ajustar' => 'Salida y ajustes',
            'inventario.transferir' => 'Transferir',
            'inventario.movimientos.ver' => 'Ver movimientos',
            'ventas.crear' => 'Usar caja',
            'ventas.ver' => 'Ver ventas',
            'ventas.anular' => 'Anular ventas',
            'productos.ver' => 'Ver productos',
            'productos.crear' => 'Crear productos',
            'productos.editar' => 'Editar productos',
            'medicamentos.ver' => 'Ver medicamentos',
            'medicamentos.crear' => 'Crear medicamentos',
            'medicamentos.editar' => 'Editar medicamentos',
            'catalogos.ver' => 'Ver especies y razas',
            'catalogos.editar' => 'Administrar especies y razas',
        ];

        foreach ($names as $code => $name) {
            DB::table('permissions')->where('code', $code)->update(['name' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
