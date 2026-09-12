<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(private InventoryRepository $repository) {}

    public function getStock(Warehouse $warehouse, string $itemType, int $itemId): int
    {
        return (int) $warehouse->stocks()->where('item_type', $itemType)->where('item_id', $itemId)->value('quantity');
    }

    public function getAvailableStock(Warehouse $warehouse, string $itemType, int $itemId): int
    {
        return max(0, $this->getStock($warehouse, $itemType, $itemId));
    }

    /**
     * @return Collection<int, array{item_type: string, item_id: int, name: string, presentation: ?string, clinic_stock: int, store_stock: int, total_stock: int, minimum_stock: int, is_active: bool}>
     */
    public function getInventoryOverview(): Collection
    {
        $items = Medication::query()
            ->select(['id', 'name', 'presentation', 'minimum_stock', 'is_active'])
            ->get()
            ->map(fn (Medication $item): array => [
                'item_type' => 'medication',
                'item_id' => $item->id,
                'name' => $item->name,
                'presentation' => $item->presentation,
                'minimum_stock' => (int) $item->minimum_stock,
                'is_active' => (bool) $item->is_active,
            ])
            ->concat(Product::query()
                ->select(['id', 'name', 'presentation', 'minimum_stock', 'is_active'])
                ->get()
                ->map(fn (Product $item): array => [
                    'item_type' => 'product',
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'presentation' => $item->presentation,
                    'minimum_stock' => (int) $item->minimum_stock,
                    'is_active' => (bool) $item->is_active,
                ]));

        $stocks = WarehouseStock::query()
            ->whereIn('item_type', ['medication', 'product'])
            ->get()
            ->keyBy(fn (WarehouseStock $stock): string => $stock->item_type.':'.$stock->item_id.':'.$stock->warehouse_id);

        $warehouses = Warehouse::query()->whereIn('code', ['CLINIC', 'STORE'])->pluck('id', 'code');

        return $items->map(function (array $item) use ($stocks, $warehouses): array {
            $clinic = (int) ($stocks->get($item['item_type'].':'.$item['item_id'].':'.$warehouses['CLINIC'])?->quantity ?? 0);
            $store = (int) ($stocks->get($item['item_type'].':'.$item['item_id'].':'.$warehouses['STORE'])?->quantity ?? 0);

            return [...$item, 'clinic_stock' => $clinic, 'store_stock' => $store, 'total_stock' => $clinic + $store];
        })->values();
    }

    public function registerEntry(Warehouse $warehouse, string $itemType, int $itemId, int $quantity, string $reason, User $user, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        return $this->move($warehouse, $itemType, $itemId, 'ENTRY', $quantity, $reason, $user, $referenceType, $referenceId);
    }

    public function registerExit(Warehouse $warehouse, string $itemType, int $itemId, int $quantity, string $reason, User $user, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        return $this->move($warehouse, $itemType, $itemId, 'EXIT', $quantity, $reason, $user, $referenceType, $referenceId);
    }

    public function adjustStock(Warehouse $warehouse, string $itemType, int $itemId, string $direction, int $quantity, string $reason, User $user): StockMovement
    {
        $movementType = $direction === 'IN' ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';

        return $this->move($warehouse, $itemType, $itemId, $movementType, $quantity, $reason, $user);
    }

    public function transferStock(Warehouse $origin, Warehouse $destination, string $itemType, int $itemId, int $quantity, string $reason, User $user): array
    {
        if ($origin->id === $destination->id) {
            throw new InvalidArgumentException('El almacén de origen y destino deben ser diferentes.');
        }

        return DB::transaction(function () use ($destination, $itemId, $itemType, $origin, $quantity, $reason, $user): array {
            $out = $this->moveWithinTransaction($origin, $itemType, $itemId, 'TRANSFER_OUT', $quantity, $reason, $user);
            $in = $this->moveWithinTransaction($destination, $itemType, $itemId, 'TRANSFER_IN', $quantity, $reason, $user);

            return ['out' => $out, 'in' => $in];
        });
    }

    /** @return Collection<int, StockMovement> */
    public function getMovements(?Warehouse $warehouse = null, ?string $itemType = null, ?int $itemId = null): Collection
    {
        return StockMovement::query()
            ->when($warehouse, fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->when($itemType, fn ($query) => $query->where('item_type', $itemType))
            ->when($itemId, fn ($query) => $query->where('item_id', $itemId))
            ->latest('id')->get();
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return Collection<int, StockMovement>
     */
    public function addMovementItemNames(Collection $movements): Collection
    {
        $medicationIds = $movements->where('item_type', 'medication')->pluck('item_id')->filter()->unique();
        $productIds = $movements->where('item_type', 'product')->pluck('item_id')->filter()->unique();
        $medications = Medication::whereIn('id', $medicationIds)->pluck('name', 'id');
        $products = Product::whereIn('id', $productIds)->pluck('name', 'id');

        return $movements->each(function (StockMovement $movement) use ($medications, $products): void {
            $names = $movement->item_type === 'medication' ? $medications : $products;
            $movement->setAttribute('display_item_name', $names->get($movement->item_id, $movement->displayItemType().' #'.$movement->item_id));
        });
    }

    private function move(Warehouse $warehouse, string $itemType, int $itemId, string $movementType, int $quantity, string $reason, User $user, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        return DB::transaction(fn (): StockMovement => $this->moveWithinTransaction($warehouse, $itemType, $itemId, $movementType, $quantity, $reason, $user, $referenceType, $referenceId));
    }

    private function moveWithinTransaction(Warehouse $warehouse, string $itemType, int $itemId, string $movementType, int $quantity, string $reason, User $user, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        if (! in_array($itemType, ['medication', 'product'], true) || $quantity < 1 || trim($reason) === '') {
            throw new InvalidArgumentException('La cantidad y el motivo son obligatorios.');
        }
        $model = $itemType === 'medication' ? Medication::class : Product::class;
        abort_unless($model::query()->whereKey($itemId)->where('is_active', true)->exists(), 422, 'El artículo no existe o está inactivo.');

        $stock = $this->repository->stockForUpdate($warehouse, $itemType, $itemId);

        return $this->repository->recordMovement($stock, $movementType, $quantity, $reason, $user, $referenceType, $referenceId);
    }
}
