<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Repositories\InventoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->whereIn('warehouse_id', fn ($query) => $query->select('id')->from('warehouses')->whereIn('code', ['CLINIC', 'STORE']))
            ->selectRaw('item_type, item_id, warehouse_id, SUM(quantity) AS quantity')
            ->groupBy('item_type', 'item_id', 'warehouse_id')
            ->get()
            ->keyBy(fn (WarehouseStock $stock): string => $stock->item_type.':'.$stock->item_id.':'.$stock->warehouse_id);

        $warehouses = Warehouse::query()->whereIn('code', ['CLINIC', 'STORE'])->pluck('id', 'code');

        return $items->map(function (array $item) use ($stocks, $warehouses): array {
            $clinic = (int) ($stocks->get($item['item_type'].':'.$item['item_id'].':'.$warehouses['CLINIC'])?->quantity ?? 0);
            $store = (int) ($stocks->get($item['item_type'].':'.$item['item_id'].':'.$warehouses['STORE'])?->quantity ?? 0);

            return [...$item, 'clinic_stock' => $clinic, 'store_stock' => $store, 'total_stock' => $clinic + $store];
        })->values();
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function getPaginatedInventoryOverview(int $perPage = 20): LengthAwarePaginator
    {
        $items = Medication::query()
            ->selectRaw("'medication' AS item_type, id AS item_id, name, presentation, minimum_stock, is_active")
            ->unionAll(Product::query()
                ->selectRaw("'product' AS item_type, id AS item_id, name, presentation, minimum_stock, is_active"));

        $stocks = WarehouseStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')
            ->whereIn('warehouses.code', ['CLINIC', 'STORE'])
            ->selectRaw('warehouse_stocks.item_type, warehouse_stocks.item_id')
            ->selectRaw("SUM(CASE WHEN warehouses.code = 'CLINIC' THEN warehouse_stocks.quantity ELSE 0 END) AS clinic_stock")
            ->selectRaw("SUM(CASE WHEN warehouses.code = 'STORE' THEN warehouse_stocks.quantity ELSE 0 END) AS store_stock")
            ->groupBy('warehouse_stocks.item_type', 'warehouse_stocks.item_id');

        $paginator = DB::query()
            ->fromSub($items, 'items')
            ->leftJoinSub($stocks, 'stocks', function ($join): void {
                $join->on('stocks.item_type', '=', 'items.item_type')
                    ->on('stocks.item_id', '=', 'items.item_id');
            })
            ->select([
                'items.item_type',
                'items.item_id',
                'items.name',
                'items.presentation',
                'items.minimum_stock',
                'items.is_active',
                DB::raw('COALESCE(stocks.clinic_stock, 0) AS clinic_stock'),
                DB::raw('COALESCE(stocks.store_stock, 0) AS store_stock'),
            ])
            ->orderBy('items.name')
            ->orderBy('items.item_id')
            ->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map(function (object $item): array {
            $clinic = (int) $item->clinic_stock;
            $store = (int) $item->store_stock;

            return [
                'item_type' => $item->item_type,
                'item_id' => (int) $item->item_id,
                'name' => $item->name,
                'presentation' => $item->presentation,
                'minimum_stock' => (int) $item->minimum_stock,
                'is_active' => (bool) $item->is_active,
                'clinic_stock' => $clinic,
                'store_stock' => $store,
                'total_stock' => $clinic + $store,
            ];
        }));

        return $paginator;
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
