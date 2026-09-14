<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use App\Models\ItemType;
use App\Models\Medication;
use App\Models\MedicationPurchase;
use App\Models\Pet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\ConfirmedMutation;
use App\Services\InventoryService;
use App\Services\ModulePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function inventoryStocks(Request $request, InventoryService $inventory): View
    {
        $request->merge(['section' => 'stocks']);

        return $this->inventory($request, $inventory);
    }

    public function inventoryMovements(Request $request, InventoryService $inventory): View
    {
        $request->merge(['section' => 'movements']);

        return $this->inventory($request, $inventory);
    }

    public function inventory(Request $request, InventoryService $inventory): View
    {
        $section = $request->string('section')->toString() ?: 'stocks';
        $permissions = [
            'stocks' => 'inventario.ver',
            'movements' => 'inventario.movimientos.ver',
        ];
        abort_unless($request->user()->hasPermission($permissions[$section] ?? 'inventario.ver'), 403);
        abort_unless(isset($permissions[$section]), 404);
        $movements = $section === 'movements' && $request->user()->hasPermission('inventario.movimientos.ver')
            ? StockMovement::with('user', 'warehouse')->whereNotNull('warehouse_id')->latest('id')->paginate(20)
            : StockMovement::query()->whereKey(0)->paginate(20);
        if ($section === 'movements') {
            $inventory->addMovementItemNames($movements->getCollection());
        }
        $canInventory = $request->user()->hasPermission('inventario.ver');
        $canMedications = $request->user()->hasPermission('medicamentos.ver');
        $items = $canInventory && $section === 'stocks'
            ? $inventory->getPaginatedInventoryOverview()
            : collect();

        return view('inventory.index', [
            'warehouses' => $canInventory ? Warehouse::where('is_active', true)->orderBy('name')->get() : collect(),
            'medications' => $canMedications ? Medication::where('is_active', true)->orderBy('name')->get() : collect(),
            'products' => $canInventory ? Product::where('is_active', true)->orderBy('name')->get() : collect(),
            'items' => $items,
            'movements' => $movements,
            'section' => $section,
        ]);
    }

    public function registerEntry(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $this->validateCentralMovement($request);
        $inventory->registerEntry(Warehouse::findOrFail($data['warehouse_id']), $data['item_type'], $data['item_id'], $data['quantity'], $data['reason'], $request->user(), $data['reference_type'] ?? null, $data['reference_id'] ?? null);

        return back()->with('success', 'Entrada registrada correctamente.');
    }

    public function registerExit(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $this->validateCentralMovement($request);
        $inventory->registerExit(Warehouse::findOrFail($data['warehouse_id']), $data['item_type'], $data['item_id'], $data['quantity'], $data['reason'], $request->user());

        return back()->with('success', 'Salida registrada correctamente.');
    }

    public function transferStock(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'origin_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_id' => ['required', 'integer', 'exists:warehouses,id', 'different:origin_id'],
            'item_type' => ['required', Rule::in(['medication', 'product'])],
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'between:1,2000000000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $inventory->transferStock(Warehouse::findOrFail($data['origin_id']), Warehouse::findOrFail($data['destination_id']), $data['item_type'], $data['item_id'], $data['quantity'], $data['reason'], $request->user());

        return back()->with('success', 'Transferencia registrada correctamente.');
    }

    public function sendToStore(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['medication', 'product'])],
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'between:1,2000000000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $warehouses = Warehouse::query()->whereIn('code', ['CLINIC', 'STORE'])->pluck('id', 'code');
        $inventory->transferStock(
            Warehouse::findOrFail($warehouses['CLINIC']),
            Warehouse::findOrFail($warehouses['STORE']),
            $data['item_type'],
            $data['item_id'],
            $data['quantity'],
            $data['reason'],
            $request->user(),
        );

        return back()->with('success', 'Stock enviado a tienda correctamente.');
    }

    private function validateCentralMovement(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'item_type' => ['required', Rule::in(['medication', 'product'])],
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'between:1,2000000000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
        ]);
    }

    public function medications(Request $request): View
    {
        return ModulePage::render('Medicamentos', ModulePage::catalogRecords(Medication::with(['itemType', 'distributor'])->orderBy('name')), [
            'code' => ['label' => 'Código', 'required' => true],
            'name' => ['label' => 'Medicamento', 'required' => true],
            'presentation_quantity' => ['label' => 'Cantidad de presentación', 'required' => true, 'type' => 'number', 'min' => '0.001', 'step' => '0.001'],
            'presentation_unit' => ['label' => 'Unidad', 'required' => true, 'options' => $this->presentationUnits()],
            'item_type_id' => ['label' => 'Tipo', 'required' => true, 'options' => $this->itemTypeOptions('MEDICATION')],
            'distributor_id' => ['label' => 'Distribuidor', 'type' => 'autocomplete', 'options' => $this->distributorOptions()],
            'purchase_price' => ['label' => 'Precio de compra por unidad (S/)', 'required' => true, 'type' => 'number', 'step' => '0.01'],
            'minimum_stock' => ['label' => 'Stock mínimo', 'required' => true, 'type' => 'number', 'step' => '1', 'default' => 5],
            'is_active' => ['label' => 'Estado', 'required' => true, 'options' => [1 => 'Activo', 0 => 'Inactivo'], 'default' => 1],
        ], ['code' => 'Código', 'name' => 'Medicamento', 'itemType.name' => 'Tipo', 'presentation_display' => 'Presentación', 'distributor.display_name' => 'Distribuidor', 'purchase_price' => 'Precio S/', 'stock' => 'Existencias', 'minimum_stock' => 'Mínimo', 'is_active' => 'Activo'], 'admin.medications', description: 'Los precios se validan contra la base de datos y las existencias se controlan mediante movimientos.');
    }

    public function saveMedication(Request $request, ConfirmedMutation $mutation): JsonResponse|RedirectResponse
    {
        $record = $request->route('record') ? Medication::findOrFail($request->route('record')) : null;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('medications')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'presentation_quantity' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'presentation_unit' => ['nullable', Rule::in(array_keys($this->presentationUnits()))],
            'item_type_id' => ['nullable', Rule::exists('item_types', 'id')->where(fn ($query) => $query->whereIn('applies_to', ['MEDICATION', 'BOTH'])->where('is_active', true))],
            'presentation' => ['nullable', 'string', 'max:100'],
            'distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')->where('is_active', true)],
            'distributor_name' => ['nullable', 'string', 'max:150'],
            'purchase_price' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000'], 'is_active' => ['required', 'boolean'],
            'stock' => ['prohibited'], 'purchase_price_cents' => ['prohibited'],
        ]);
        $parts = explode('.', (string) $data['purchase_price']);
        $data['purchase_price_cents'] = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        unset($data['purchase_price']);
        $data['presentation'] = ! empty($data['presentation_quantity']) && ! empty($data['presentation_unit'])
            ? $this->formatPresentation($data['presentation_quantity'], $data['presentation_unit'])
            : ($data['presentation'] ?? $record?->presentation);
        $data['distributor_name'] = ! empty($data['distributor_id']) ? Distributor::findOrFail($data['distributor_id'])->display_name : ($data['distributor_name'] ?? null);

        if (! $request->expectsJson()) {
            $this->persistMedication($record, $data);

            return back()->with('success', $record ? 'Medicamento actualizado correctamente.' : 'Medicamento creado correctamente.');
        }

        return $mutation->handle($request, $record, $data, function ($locked, $values) {
            return $this->persistMedication($locked, $values);
        }, ['Código' => $data['code'], 'Medicamento' => $data['name'], 'Presentación' => $data['presentation'], 'Precio anterior S/' => $record?->purchase_price ?? 'Nuevo', 'Precio nuevo S/' => number_format($data['purchase_price_cents'] / 100, 2), 'Stock mínimo' => $data['minimum_stock'], 'Activo' => $data['is_active'] ? 'Sí' : 'No'], true);
    }

    public function purchases(Request $request): View
    {
        return ModulePage::render('Compras de medicamentos', MedicationPurchase::with('medication', 'user')->latest('id')->paginate(15), [
            'medication_id' => ['label' => 'Medicamento', 'required' => true, 'options' => $this->medicationOptions()],
            'receipt_number' => ['label' => 'Comprobante / referencia única de la línea', 'required' => true],
            'supplier' => ['label' => 'Proveedor', 'required' => true],
            'quantity' => ['label' => 'Unidades compradas', 'required' => true, 'type' => 'number', 'step' => 1],
            'purchased_at' => ['label' => 'Fecha de compra', 'required' => true, 'type' => 'date'],
            'batch_number' => ['label' => 'Lote'], 'expires_at' => ['label' => 'Vencimiento', 'type' => 'date'],
        ], ['receipt_number' => 'Comprobante', 'medication.name' => 'Medicamento', 'supplier' => 'Proveedor', 'quantity' => 'Cantidad', 'unit_price' => 'Precio S/', 'total' => 'Total S/', 'purchased_at' => 'Compra', 'expires_at' => 'Vencimiento'], strtolower($request->user()->effectiveRole()).'.purchases', canEdit: false, description: 'El precio y el total se consultan y calculan en el servidor. Cada comprobante se registra una sola vez; las compras confirmadas no se sobrescriben.');
    }

    public function purchase(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $data = $request->validate([
            'medication_id' => ['required', 'integer', 'exists:medications,id'],
            'receipt_number' => ['required', 'string', 'max:100', 'unique:medication_purchases'],
            'supplier' => ['required', 'string', 'max:150'], 'quantity' => ['required', 'integer', 'between:1,1000000'],
            'purchased_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'batch_number' => ['nullable', 'string', 'max:50'], 'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchased_at'],
            'unit_price_cents' => ['prohibited'], 'total_cents' => ['prohibited'], 'price' => ['prohibited'], 'total' => ['prohibited'], 'user_id' => ['prohibited'],
        ]);

        return DB::transaction(function () use ($request, $mutation, $data): JsonResponse {
            $medication = Medication::whereKey($data['medication_id'])->lockForUpdate()->firstOrFail();
            abort_unless($medication->is_active, 409, 'El medicamento está inactivo.');
            abort_if($medication->stock + $data['quantity'] > 2000000000, 422, 'La cantidad excede el límite de inventario.');
            $data['unit_price_cents'] = $medication->purchase_price_cents;
            $data['total_cents'] = $medication->purchase_price_cents * (int) $data['quantity'];
            $data['user_id'] = $request->user()->id;

            return $mutation->handle($request, null, $data, function ($locked, $values) use ($medication) {
                $purchase = MedicationPurchase::create($values);
                $medication->stock += $values['quantity'];
                $medication->save();
                StockMovement::create(['medication_id' => $medication->id, 'user_id' => $values['user_id'], 'quantity' => $values['quantity'], 'balance' => $medication->stock, 'reason' => 'Compra '.$values['receipt_number']]);

                return $purchase;
            }, ['Medicamento' => $medication->name, 'Proveedor' => $data['supplier'], 'Comprobante' => $data['receipt_number'], 'Cantidad' => $data['quantity'], 'Precio unitario S/' => $medication->purchase_price, 'Total S/' => number_format($data['total_cents'] / 100, 2), 'Fecha' => $data['purchased_at'], 'Lote' => $data['batch_number'] ?? '—', 'Vencimiento' => $data['expires_at'] ?? '—'], true);
        });
    }

    public function movements(Request $request): View
    {
        return ModulePage::render('Movimientos de inventario', StockMovement::with('medication', 'user', 'pet')->latest('id')->paginate(15), [
            'medication_id' => ['label' => 'Medicamento', 'required' => true, 'options' => $this->medicationOptions()],
            'direction' => ['label' => 'Movimiento', 'required' => true, 'options' => ['OUT' => 'Salida / consumo', 'IN' => 'Entrada por ajuste justificado']],
            'quantity' => ['label' => 'Cantidad de unidades', 'required' => true, 'type' => 'number', 'step' => 1],
            'pet_id' => ['label' => 'Mascota atendida (opcional)', 'options' => Pet::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($pet) => [$pet->id => $pet->name.' · #'.$pet->id])->all()],
            'reason' => ['label' => 'Motivo obligatorio', 'required' => true, 'type' => 'textarea'],
        ], ['created_at' => 'Fecha', 'medication.name' => 'Medicamento', 'quantity' => 'Movimiento', 'balance' => 'Saldo', 'reason' => 'Motivo', 'user.name' => 'Responsable'], strtolower($request->user()->effectiveRole()).'.movements', canEdit: false, description: 'Cada entrada o salida conserva su responsable y motivo. No se permiten saldos negativos.');
    }

    public function products(Request $request): View
    {
        $role = strtolower($request->user()->effectiveRole());
        $canManage = $request->user()->isAdmin() || $request->user()->isDoctor();

        return ModulePage::render('Tienda de productos', ModulePage::catalogRecords(Product::with(['itemType', 'distributor'])->when($request->user()->isOwner(), fn ($query) => $query->where('is_active', true))->latest('id')), [
            'code' => ['label' => 'Código', 'required' => true],
            'name' => ['label' => 'Producto', 'required' => true],
            'item_type_id' => ['label' => 'Tipo', 'required' => true, 'options' => $this->itemTypeOptions('PRODUCT')],
            'presentation_quantity' => ['label' => 'Cantidad de presentación', 'type' => 'number', 'min' => '0.001', 'step' => '0.001'],
            'presentation_unit' => ['label' => 'Unidad', 'options' => $this->presentationUnits()],
            'distributor_id' => ['label' => 'Distribuidor', 'type' => 'autocomplete', 'options' => $this->distributorOptions()],
            'description' => ['label' => 'Descripción', 'type' => 'textarea'],
            'sale_price' => ['label' => 'Precio (S/)', 'required' => true, 'type' => 'number', 'step' => '0.01'],
            'minimum_stock' => ['label' => 'Stock mínimo', 'required' => true, 'type' => 'number', 'step' => '1'],
            'is_active' => ['label' => 'Estado', 'required' => true, 'options' => [1 => 'Activo', 0 => 'Inactivo'], 'default' => 1],
        ], [
            'code' => 'Código',
            'name' => 'Producto',
            'itemType.name' => 'Tipo',
            'presentation_display' => 'Presentación',
            'sale_price' => 'Precio S/',
            'is_active' => 'Activo',
        ], 'admin.products', canCreate: $canManage, canEdit: $canManage, description: 'Catálogo de productos disponibles en la tienda de la clínica.');
    }

    public function saveProduct(Request $request, ConfirmedMutation $mutation): JsonResponse|RedirectResponse
    {
        $record = $request->route('record') ? Product::findOrFail($request->route('record')) : null;
        abort_unless($request->user()->hasPermission($record ? 'productos.editar' : 'productos.crear'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:80'],
            'presentation' => ['nullable', 'string', 'max:120'],
            'item_type_id' => ['nullable', Rule::exists('item_types', 'id')->where(fn ($query) => $query->whereIn('applies_to', ['PRODUCT', 'BOTH'])->where('is_active', true))],
            'presentation_quantity' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'presentation_unit' => ['nullable', Rule::in(array_keys($this->presentationUnits()))],
            'distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')->where('is_active', true)],
            'distributor_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sale_price' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'stock' => ['prohibited'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $parts = explode('.', (string) $data['sale_price']);
        $data['sale_price_cents'] = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        unset($data['sale_price']);
        $itemType = ! empty($data['item_type_id'])
            ? ItemType::findOrFail($data['item_type_id'])
            : ItemType::where('name', $data['type'] ?? '')->whereIn('applies_to', ['PRODUCT', 'BOTH'])->first();
        $data['type'] = $itemType?->name ?? ($data['type'] ?? 'Otro');
        $data['presentation'] = ! empty($data['presentation_quantity']) && ! empty($data['presentation_unit'])
            ? $this->formatPresentation($data['presentation_quantity'], $data['presentation_unit'])
            : ($data['presentation'] ?? $record?->presentation);
        $data['distributor_name'] = ! empty($data['distributor_id']) ? Distributor::findOrFail($data['distributor_id'])->display_name : ($data['distributor_name'] ?? null);

        if (! $request->expectsJson()) {
            $this->persistProduct($record, $data);

            return back()->with('success', $record ? 'Producto actualizado correctamente.' : 'Producto creado correctamente.');
        }

        return $mutation->handle($request, $record, $data, function ($locked, $values): Product {
            return $this->persistProduct($locked, $values);
        }, ['Código' => $data['code'], 'Producto' => $data['name'], 'Tipo' => $data['type'], 'Precio S/' => number_format($data['sale_price_cents'] / 100, 2), 'Activo' => $data['is_active'] ? 'Sí' : 'No'], true);
    }

    public function movement(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $data = $request->validate(['medication_id' => ['required', 'integer', 'exists:medications,id'], 'direction' => ['required', Rule::in(['IN', 'OUT'])], 'quantity' => ['required', 'integer', 'between:1,1000000'], 'pet_id' => ['nullable', 'integer', 'exists:pets,id'], 'reason' => ['required', 'string', 'min:5', 'max:255'], 'balance' => ['prohibited'], 'user_id' => ['prohibited']]);

        return DB::transaction(function () use ($request, $mutation, $data): JsonResponse {
            $medication = Medication::whereKey($data['medication_id'])->lockForUpdate()->firstOrFail();
            abort_unless($medication->is_active, 409, 'El medicamento está inactivo.');
            $data['quantity'] = (int) $data['quantity'] * ($data['direction'] === 'OUT' ? -1 : 1);
            $data['balance'] = $medication->stock + $data['quantity'];
            abort_if($data['balance'] < 0 || $data['balance'] > 2000000000, 422, 'Existencias insuficientes o cantidad fuera de rango.');
            $data['user_id'] = $request->user()->id;
            unset($data['direction']);

            return $mutation->handle($request, null, $data, function ($locked, $values) use ($medication) {
                $medication->stock = $values['balance'];
                $medication->save();

                return StockMovement::create($values);
            }, ['Medicamento' => $medication->name, 'Movimiento' => $data['quantity'], 'Saldo actual' => $medication->stock, 'Saldo resultante' => $data['balance'], 'Motivo' => $data['reason']], true);
        });
    }

    private function medicationOptions(): array
    {
        return Medication::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($medication) => [$medication->id => $medication->name.' · '.$medication->presentation.' · S/ '.$medication->purchase_price])->all();
    }

    private function presentationUnits(): array
    {
        return ['unidad' => 'Unidad', 'ml' => 'ml', 'L' => 'L', 'mg' => 'mg', 'g' => 'g', 'kg' => 'kg', 'tableta' => 'Tableta', 'cápsula' => 'Cápsula', 'sobre' => 'Sobre', 'ampolla' => 'Ampolla', 'frasco' => 'Frasco', 'dosis' => 'Dosis'];
    }

    private function itemTypeOptions(string $appliesTo): array
    {
        return ItemType::where('is_active', true)->whereIn('applies_to', [$appliesTo, 'BOTH'])->orderBy('name')->pluck('name', 'id')->all();
    }

    private function distributorOptions(): array
    {
        return Distributor::where('is_active', true)->orderBy('business_name')->get()->mapWithKeys(fn (Distributor $distributor) => [$distributor->id => $distributor->display_name.' · RUC '.$distributor->ruc])->all();
    }

    private function formatPresentation(float|int|string $quantity, string $unit): string
    {
        return rtrim(rtrim((string) $quantity, '0'), '.').' '.$unit;
    }

    private function persistMedication(?Medication $record, array $data): Medication
    {
        $medication = $record ?? new Medication;
        $medication->fill($data)->save();

        return $medication;
    }

    private function persistProduct(?Product $record, array $data): Product
    {
        $product = $record ?? new Product;
        $product->fill($data)->save();

        return $product;
    }
}
