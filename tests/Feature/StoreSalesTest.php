<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Profile;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_showcase_only_lists_products_with_store_stock(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->actingAs($user)->get('/store/showcase')->assertOk()->assertDontSee($product->name);

        app(InventoryService::class)->registerEntry(Warehouse::where('code', 'CLINIC')->firstOrFail(), 'product', $product->id, 5, 'Ingreso clínica', $user);
        $this->actingAs($user)->get('/store/showcase')->assertOk()->assertDontSee($product->name);
        app(InventoryService::class)->registerEntry(Warehouse::where('code', 'STORE')->firstOrFail(), 'product', $product->id, 2, 'Reposición tienda', $user);
        $this->actingAs($user)->get('/store/showcase')->assertOk()->assertSee($product->name);
        $this->actingAs($user)->get('/store/cashier')->assertOk()->assertSee($product->name)->assertSee('2 disponibles');
    }

    public function test_product_sent_from_inventory_appears_in_store_dynamically(): void
    {
        $user = $this->inventoryUser();
        $product = $this->product('TRANSFER');
        $clinic = Warehouse::where('code', 'CLINIC')->firstOrFail();

        $this->actingAs($user)->post('/admin/inventory/entries', [
            'warehouse_id' => $clinic->id,
            'item_type' => 'product',
            'item_id' => $product->id,
            'quantity' => 6,
            'reason' => 'Ingreso de producto',
        ])->assertRedirect();

        $this->actingAs($user)->post('/admin/inventory/send-to-store', [
            'item_type' => 'product',
            'item_id' => $product->id,
            'quantity' => 4,
            'reason' => 'Reposición de tienda',
        ])->assertRedirect();

        $this->actingAs($user)->get('/store/showcase')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('4 disponibles');
    }

    public function test_sale_uses_store_stock_and_keeps_clinic_stock_unchanged(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $service = app(InventoryService::class);
        $clinic = Warehouse::where('code', 'CLINIC')->firstOrFail();
        $store = Warehouse::where('code', 'STORE')->firstOrFail();
        $service->registerEntry($clinic, 'product', $product->id, 20, 'Ingreso clínica', $user);
        $service->registerEntry($store, 'product', $product->id, 4, 'Reposición tienda', $user);

        $this->actingAs($user)->post('/store/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertSame(20, $service->getStock($clinic, 'product', $product->id));
        $this->assertSame(2, $service->getStock($store, 'product', $product->id));
        $this->assertDatabaseHas('sales', ['seller_user_id' => $user->id, 'total_cents' => 2000]);
    }

    public function test_sale_rolls_back_when_one_item_has_insufficient_store_stock(): void
    {
        $user = $this->cashier();
        $first = $this->product('A');
        $second = $this->product('B');
        $store = Warehouse::where('code', 'STORE')->firstOrFail();
        app(InventoryService::class)->registerEntry($store, 'product', $first->id, 2, 'Reposición', $user);

        $this->actingAs($user)->post('/store/sales', [
            'items' => [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2, app(InventoryService::class)->getStock($store, 'product', $first->id));
    }

    public function test_cashier_renders_working_cart_controls_and_pdf_download_for_own_sale(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $store = Warehouse::where('code', 'STORE')->firstOrFail();
        app(InventoryService::class)->registerEntry($store, 'product', $product->id, 3, 'Reposición', $user);

        $response = $this->actingAs($user)->get('/store/cashier');
        $response->assertOk()
            ->assertSee('data-cashier-submit', false)
            ->assertSee('data-product-add="'.$product->id.'"', false)
            ->assertSee('data-product-minus="'.$product->id.'"', false);

        $this->actingAs($user)->post('/store/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->actingAs($user)->get(route('store.sales.pdf', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('store.sales.pdf', $sale))
            ->assertForbidden();
    }

    private function cashier(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->update(['profile_id' => Profile::where('code', 'CAJA')->value('id')]);

        return $user->fresh();
    }

    private function inventoryUser(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user->update(['profile_id' => Profile::where('code', 'INVENTARIO')->value('id')]);

        return $user->fresh();
    }

    private function product(string $suffix = ''): Product
    {
        return Product::create([
            'code' => 'PRO-'.($suffix ?: fake()->unique()->numerify('####')),
            'name' => 'Producto '.$suffix.fake()->unique()->numerify('###'),
            'type' => 'Higiene',
            'presentation' => 'Unidad',
            'sale_price_cents' => 1000,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);
    }
}
