<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\Product;
use App\Models\Profile;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_exit_and_adjustment_are_recorded_per_warehouse(): void
    {
        $user = $this->inventoryUser();
        $warehouse = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $medication = $this->medication();
        $service = app(InventoryService::class);

        $service->registerEntry($warehouse, 'medication', $medication->id, 10, 'Compra inicial', $user);
        $service->registerExit($warehouse, 'medication', $medication->id, 3, 'Entrega autorizada', $user);
        $service->adjustStock($warehouse, 'medication', $medication->id, 'OUT', 2, 'Producto dañado', $user);

        $this->assertSame(5, $service->getStock($warehouse, 'medication', $medication->id));
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'ADJUSTMENT_OUT', 'balance_before' => 7, 'balance_after' => 5]);
    }

    public function test_exit_rejects_insufficient_stock_without_creating_movement(): void
    {
        $user = $this->inventoryUser();
        $warehouse = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $medication = $this->medication();

        $this->expectExceptionMessage('Stock insuficiente');
        app(InventoryService::class)->registerExit($warehouse, 'medication', $medication->id, 1, 'Salida', $user);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_transfer_creates_two_movements_atomically(): void
    {
        $user = $this->inventoryUser();
        $origin = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $destination = Warehouse::where('code', 'STORE')->firstOrFail();
        $medication = $this->medication();
        $service = app(InventoryService::class);
        $service->registerEntry($origin, 'medication', $medication->id, 8, 'Ingreso', $user);

        $service->transferStock($origin, $destination, 'medication', $medication->id, 5, 'Reposición de tienda', $user);

        $this->assertSame(3, $service->getStock($origin, 'medication', $medication->id));
        $this->assertSame(5, $service->getStock($destination, 'medication', $medication->id));
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'TRANSFER_OUT', 'balance_after' => 3]);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'TRANSFER_IN', 'balance_after' => 5]);
    }

    public function test_inventory_overview_lists_articles_without_store_rows(): void
    {
        $medication = $this->medication();
        $warehouse = Warehouse::where('code', 'CLINIC')->firstOrFail();
        app(InventoryService::class)->registerEntry($warehouse, 'medication', $medication->id, 6, 'Ingreso clínico', $this->inventoryUser());

        $item = app(InventoryService::class)->getInventoryOverview()->firstWhere('item_id', $medication->id);

        $this->assertSame(6, $item['clinic_stock']);
        $this->assertSame(0, $item['store_stock']);
        $this->assertSame(6, $item['total_stock']);
    }

    public function test_send_to_store_route_uses_the_quick_transfer(): void
    {
        $user = $this->inventoryUser();
        $origin = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $destination = Warehouse::where('code', 'STORE')->firstOrFail();
        $medication = $this->medication();
        app(InventoryService::class)->registerEntry($origin, 'medication', $medication->id, 8, 'Ingreso', $user);

        $this->actingAs($user)->post('/admin/inventory/send-to-store', [
            'item_type' => 'medication',
            'item_id' => $medication->id,
            'quantity' => 3,
            'reason' => 'Reposición tienda',
        ])->assertRedirect();

        $service = app(InventoryService::class);
        $this->assertSame(5, $service->getStock($origin, 'medication', $medication->id));
        $this->assertSame(3, $service->getStock($destination, 'medication', $medication->id));
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'TRANSFER_OUT', 'item_id' => $medication->id]);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'TRANSFER_IN', 'item_id' => $medication->id]);
    }

    public function test_inventory_sidebar_has_one_inventory_entry(): void
    {
        $user = $this->inventoryUser();

        $response = $this->actingAs($user)->get('/admin/inventory');

        $response->assertOk();
        $this->assertStringContainsString('/admin/inventory"', $response->getContent());
        $this->assertStringNotContainsString('Inventario central', $response->getContent());
        $this->assertStringNotContainsString('Ver existencias', $response->getContent());
        $this->assertStringNotContainsString('id="inventoryProducts"', $response->getContent());
        $this->assertStringNotContainsString('id="inventoryMedications"', $response->getContent());
        $this->assertStringNotContainsString('Ajustar stock', $response->getContent());
        $this->assertStringNotContainsString('inventoryAdjustmentModal', $response->getContent());
        $this->assertStringContainsString('Configuración', $response->getContent());
        $this->assertStringContainsString('/admin/products"', $response->getContent());
        $this->assertStringContainsString('/admin/medications"', $response->getContent());
        $this->assertStringContainsString('Tienda', $response->getContent());
    }

    public function test_inventory_stocks_and_movements_are_separate_data_table_sections(): void
    {
        $user = $this->inventoryUser();

        $stocks = $this->actingAs($user)->get('/admin/inventory/stocks');
        $movements = $this->actingAs($user)->get('/admin/inventory/movements');

        $stocks->assertOk()->assertSee('data-datatable', false)->assertSee('Existencias', false);
        $movements->assertOk()->assertSee('data-datatable', false)->assertSee('Movimientos', false);
    }

    public function test_inventory_catalog_creates_product_without_legacy_stock_and_lists_it(): void
    {
        $user = $this->inventoryUser();

        $this->actingAs($user)->post('/admin/products', [
            'code' => 'COL-001',
            'name' => 'Collar antipulgas',
            'type' => 'Antipulgas',
            'presentation' => 'Unidad',
            'distributor_name' => 'VetPharma Perú',
            'sale_price' => '40.00',
            'minimum_stock' => 5,
            'is_active' => 1,
        ])->assertRedirect();

        $product = Product::where('code', 'COL-001')->firstOrFail();
        $this->assertSame(0, $product->stock);
        $item = app(InventoryService::class)->getInventoryOverview()->firstWhere('item_id', $product->id);
        $this->assertSame(0, $item['clinic_stock']);
        $this->assertSame(0, $item['store_stock']);
        $this->assertSame('VetPharma Perú', $product->distributor_name);
    }

    public function test_inventory_movements_display_catalog_name_and_spanish_label(): void
    {
        $user = $this->inventoryUser();
        $warehouse = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $product = Product::create([
            'code' => 'SHA-001',
            'name' => 'Shampoo dermatológico',
            'type' => 'Higiene',
            'presentation' => 'Frasco',
            'sale_price_cents' => 4000,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
        app(InventoryService::class)->registerEntry($warehouse, 'product', $product->id, 10, 'Reposición de tienda', $user);

        $movements = app(InventoryService::class)->addMovementItemNames(
            StockMovement::where('item_id', $product->id)->get()
        );

        $this->assertSame('Shampoo dermatológico', $movements->first()->display_item_name);
        $this->assertSame('Entrada', $movements->first()->displayMovementType());
        $this->assertSame('Producto', $movements->first()->displayItemType());
    }

    private function inventoryUser(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user->update(['profile_id' => Profile::where('code', 'INVENTARIO')->value('id')]);

        return $user->fresh();
    }

    private function medication(): Medication
    {
        return Medication::create([
            'code' => fake()->unique()->bothify('MED-###'),
            'name' => 'Medicamento de prueba',
            'presentation' => 'Unidad',
            'purchase_price_cents' => 100,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
    }
}
