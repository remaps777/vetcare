@extends('layouts.app')
@section('title', 'Inventario')
@section('page-title', 'Inventario')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 fw-bold">Inventario</h1><p class="text-secondary mb-0">Catálogos y existencias por almacén.</p></div>
    <div class="d-flex flex-wrap gap-2"></div>
</div>

@if($section === 'stocks')
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="table-responsive"><table class="table align-middle" data-datatable><thead><tr><th>Artículo</th><th>Tipo</th><th>Clínica</th><th>Tienda</th><th>Total</th><th>Stock mínimo</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>@forelse($items as $item)<tr><td><strong>{{ $item['name'] }}</strong><br><small class="text-muted">{{ $item['presentation'] ?? '—' }}</small></td><td>{{ $item['item_type'] === 'medication' ? 'Medicamento' : 'Producto' }}</td><td>{{ $item['clinic_stock'] }}</td><td>{{ $item['store_stock'] }}</td><td>{{ $item['total_stock'] }}</td><td>{{ $item['minimum_stock'] }}</td><td><span class="badge text-bg-{{ ! $item['is_active'] ? 'secondary' : ($item['total_stock'] <= 0 ? 'danger' : ($item['total_stock'] <= $item['minimum_stock'] ? 'warning' : 'success')) }}">{{ ! $item['is_active'] ? 'Inactivo' : ($item['total_stock'] <= 0 ? 'Sin stock' : ($item['total_stock'] <= $item['minimum_stock'] ? 'Stock bajo' : 'Disponible')) }}</span></td><td><div class="d-flex gap-1">
                @if(auth()->user()->hasPermission('inventario.ingreso'))<button class="btn btn-sm btn-primary text-white" type="button" title="Registrar entrada" aria-label="Registrar entrada" data-bs-toggle="modal" data-bs-target="#inventoryEntryModal" data-item-type="{{ $item['item_type'] }}" data-item-id="{{ $item['item_id'] }}"><i class="bi bi-box-arrow-in-down me-1"></i>Entrada</button>@endif
                @if(auth()->user()->hasPermission('inventario.transferir'))<button class="btn btn-sm btn-info text-white" type="button" title="Enviar a tienda" aria-label="Enviar a tienda" data-bs-toggle="modal" data-bs-target="#sendToStoreModal" data-item-type="{{ $item['item_type'] }}" data-item-id="{{ $item['item_id'] }}" data-item-name="{{ $item['name'] }}" data-clinic-stock="{{ $item['clinic_stock'] }}"><i class="bi bi-shop me-1"></i>A tienda</button>@endif
                @if(auth()->user()->hasPermission('inventario.ajustar'))<button class="btn btn-sm btn-warning text-white" type="button" title="Registrar salida" aria-label="Registrar salida" data-bs-toggle="modal" data-bs-target="#inventoryExitModal" data-item-type="{{ $item['item_type'] }}" data-item-id="{{ $item['item_id'] }}"><i class="bi bi-box-arrow-up me-1"></i>Salida</button>@endif
            </div></td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No hay artículos registrados.</td></tr>@endforelse</tbody></table></div>
            {{ $items->links('pagination::bootstrap-5') }}
        </div></div>
@else
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle" data-datatable><thead><tr><th>Fecha</th><th>Almacén</th><th>Artículo</th><th>Movimiento</th><th>Cantidad</th><th>Saldo</th><th>Motivo</th><th>Responsable</th></tr></thead>
        <tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at?->format('d/m/Y H:i') }}</td><td>{{ $movement->warehouse?->name }}</td><td>{{ $movement->display_item_name }} <small class="text-muted">({{ $movement->displayItemType() }})</small></td><td>{{ $movement->displayMovementType() }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->balance_after }}</td><td>{{ $movement->reason }}</td><td>{{ $movement->user?->name }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No hay movimientos centralizados.</td></tr>@endforelse</tbody></table></div>{{ $movements->links('pagination::bootstrap-5') }}</div></div>
@endif

@include('inventory.modals')
@endsection
