<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function createSale(User $seller, ?Owner $owner, array $items, ?string $paymentMethod = null): Sale
    {
        if ($items === []) {
            throw new InvalidArgumentException('La venta debe contener al menos un producto.');
        }

        return DB::transaction(function () use ($items, $owner, $paymentMethod, $seller): Sale {
            $store = Warehouse::where('code', 'STORE')->where('is_active', true)->firstOrFail();
            $sale = Sale::create([
                'sale_number' => 'PENDIENTE-'.str()->random(12),
                'owner_id' => $owner?->id,
                'seller_user_id' => $seller->id,
                'status' => 'COMPLETED',
                'payment_method' => $paymentMethod,
                'sold_at' => now(),
            ]);
            $total = 0;

            foreach ($items as $item) {
                $product = Product::query()->whereKey($item['product_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
                $quantity = (int) $item['quantity'];
                if ($quantity < 1) {
                    throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
                }

                $unitPrice = (int) $product->sale_price_cents;
                $subtotal = $unitPrice * $quantity;
                $this->inventory->registerExit($store, 'product', $product->id, $quantity, 'VENTA', $seller, Sale::class, $sale->id);
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPrice,
                    'subtotal_cents' => $subtotal,
                ]);
                $total += $subtotal;
            }

            $sale->update(['sale_number' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT), 'total_cents' => $total]);

            return $sale->load('owner', 'seller', 'items.product');
        });
    }

    /** @return Collection<int, Sale> */
    public function getSales(?User $seller = null): Collection
    {
        return Sale::with('owner', 'seller', 'items.product')
            ->when($seller, fn ($query) => $query->where('seller_user_id', $seller->id))
            ->latest('id')->get();
    }

    public function getSale(Sale $sale): Sale
    {
        return $sale->load('owner', 'seller', 'items.product');
    }
}
