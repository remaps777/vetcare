@php
    $items = collect(['medication' => $medications, 'product' => $products]);
@endphp
<div class="modal fade" id="sendToStoreModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('admin.inventory.send-to-store') }}" data-send-to-store-form>@csrf
    <div class="modal-header"><h5 class="modal-title">Enviar a tienda</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="mb-2">Producto: <strong data-send-item-name></strong></p><p>Disponible en Clínica: <strong data-send-clinic-stock></strong></p><input type="hidden" name="item_type"><input type="hidden" name="item_id"><div class="mb-3"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="quantity" min="1" required></div><div><label class="form-label">Motivo</label><textarea class="form-control" name="reason" minlength="5" maxlength="255" required>Reposición de tienda</textarea></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Transferir</button></div>
</form></div></div></div>
@foreach([
    ['id' => 'inventoryEntryModal', 'title' => 'Registrar entrada', 'action' => route('admin.inventory.entries.store'), 'button' => 'Registrar entrada', 'method' => 'POST'],
    ['id' => 'inventoryExitModal', 'title' => 'Registrar salida', 'action' => route('admin.inventory.exits.store'), 'button' => 'Registrar salida', 'method' => 'POST'],
] as $modal)
<div class="modal fade" id="{{ $modal['id'] }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ $modal['action'] }}" data-inventory-form>@csrf
    <div class="modal-header"><h5 class="modal-title">{{ $modal['title'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Artículo</label><select class="form-select" name="item_type" required><option value="medication">Medicamento</option><option value="product">Producto</option></select></div>
        <div class="mb-3"><label class="form-label">Artículo</label><select class="form-select" name="item_id" required><option value="">Selecciona un artículo</option>@foreach($medications as $item)<option value="{{ $item->id }}" data-item-type="medication">{{ $item->name }} · {{ $item->presentation }} (medicamento)</option>@endforeach @foreach($products as $item)<option value="{{ $item->id }}" data-item-type="product">{{ $item->name }} · {{ $item->presentation }} (producto)</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label">Almacén</label><select class="form-select" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="quantity" min="1" required></div>
        <div><label class="form-label">Motivo</label><textarea class="form-control" name="reason" minlength="5" maxlength="255" required></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">{{ $modal['button'] }}</button></div>
</form></div></div></div>
@endforeach

<div class="modal fade" id="inventoryTransferModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.inventory.transfers.store') }}" data-inventory-form>@csrf
    <div class="modal-header"><h5 class="modal-title">Transferir stock</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <div class="mb-3"><label class="form-label">Artículo</label><select class="form-select" name="item_type" required><option value="medication">Medicamento</option><option value="product">Producto</option></select></div>
        <div class="mb-3"><label class="form-label">Artículo</label><select class="form-select" name="item_id" required><option value="">Selecciona un artículo</option>@foreach($medications as $item)<option value="{{ $item->id }}" data-item-type="medication">{{ $item->name }} · {{ $item->presentation }} (medicamento)</option>@endforeach @foreach($products as $item)<option value="{{ $item->id }}" data-item-type="product">{{ $item->name }} · {{ $item->presentation }} (producto)</option>@endforeach</select></div>
        <div class="row g-3 mb-3"><div class="col"><label class="form-label">Origen</label><select class="form-select" name="origin_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div><div class="col"><label class="form-label">Destino</label><select class="form-select" name="destination_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div></div>
        <div class="mb-3"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="quantity" min="1" required></div><div><label class="form-label">Motivo</label><textarea class="form-control" name="reason" minlength="5" maxlength="255" required></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Transferir stock</button></div>
</form></div></div></div>
