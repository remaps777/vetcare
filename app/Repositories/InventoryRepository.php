<?php

namespace App\Repositories;

use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryRepository
{
    public function stockForUpdate(Warehouse $warehouse, string $itemType, int $itemId, int $minimumStock = 0): WarehouseStock
    {
        Warehouse::query()->whereKey($warehouse->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        return WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'quantity' => 0,
            'minimum_stock' => $minimumStock,
        ]);
    }

    public function recordMovement(
        WarehouseStock $stock,
        string $movementType,
        int $quantity,
        string $reason,
        User $user,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        $before = (int) $stock->quantity;
        $delta = in_array($movementType, ['EXIT', 'ADJUSTMENT_OUT', 'TRANSFER_OUT'], true) ? -$quantity : $quantity;
        $after = $before + $delta;

        if ($after < 0) {
            throw new RuntimeException('Stock insuficiente para realizar la operación.');
        }

        $stock->update(['quantity' => $after]);

        return StockMovement::create([
            'warehouse_id' => $stock->warehouse_id,
            'item_type' => $stock->item_type,
            'item_id' => $stock->item_id,
            'movement_type' => $movementType,
            'user_id' => $user->id,
            'quantity' => $quantity,
            'balance' => $after,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public function inTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
