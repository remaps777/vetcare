@extends('layouts.app')
@section('title', $title)
@section('page-title', $title)
@section('content')
@php
    $statusLabels = [
        'PENDING' => 'Pendiente',
        'CONFIRMED' => 'Confirmada',
        'COMPLETED' => 'Atendida',
        'CANCELLED' => 'Cancelada',
    ];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 fw-bold">{{ $title }}</h1><p class="text-secondary mb-0">{{ $description }}</p></div>
    <div class="d-flex gap-2">
        @if($canEmergencyOrder)<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#emergencyOrderModal"><i class="bi bi-lightning-charge me-1"></i>Atención rápida / emergencia</button>@endif
        @if($canCreate)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRecord"><i class="bi bi-plus-lg me-2"></i>Nuevo registro</button>@endif
    </div>
</div>
@if($statusCatalog)
<form method="GET" class="d-flex gap-2 align-items-center mb-3"><label for="catalog-active">Estado</label><select class="form-select w-auto" name="active" id="catalog-active"><option value="">Todos</option><option value="1" @selected(request('active') === '1')>Activos</option><option value="0" @selected(request('active') === '0')>Inactivos</option></select><button class="btn btn-outline-primary">Filtrar</button></form>
@endif
<div class="vet-card p-3 p-md-4">
    @if($records->isEmpty())<p class="text-secondary text-center py-4 mb-0">No hay registros disponibles.</p>
    @else
        <div class="table-responsive"><table class="table align-middle mb-3" data-datatable>
            <thead><tr>@foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach @if($canEdit || $canDelete || ($canUpdateStatus ?? false))<th scope="col">Acciones</th>@endif</tr></thead>
            <tbody>@foreach($records as $record)<tr>
                @foreach($columns as $key => $label)
                    @php $value = data_get($record, $key); @endphp
                    <td>@if($value instanceof \DateTimeInterface) {{ $value->format('d/m/Y H:i') }} @elseif(is_bool($value)) <span class="badge {{ $value ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $key === 'is_active' ? ($value ? 'Activo' : 'Inactivo') : ($value ? 'Sí' : 'No') }}</span> @elseif($key === 'status') <span class="badge text-bg-light border">{{ $statusLabels[$value] ?? $value ?? '—' }}</span> @else {{ $value ?? '—' }} @endif</td>
                @endforeach
                @if($canEdit || $canDelete || ($canUpdateStatus ?? false) || ($canAttendOrder ?? false))<td class="text-nowrap">
                    @if($canEdit)<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRecord{{ $record->id }}">Editar</button>@endif
                    @if($statusCatalog && $canEdit)<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#catalogStatus{{ $record->id }}">{{ $record->is_active ? 'Desactivar' : 'Activar' }}</button>@endif
                    @if($canDelete)<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRecord{{ $record->id }}">Eliminar</button>@endif
                    @if(($canUpdateStatus ?? false) && isset($record->status))
                        <form method="POST" action="{{ route($base.'.status', ['record' => $record->id]) }}" class="d-inline-flex align-items-center gap-1">
                            @csrf
                            @method('PATCH')
                            <label class="visually-hidden" for="status-{{ $record->id }}">Estado de la cita</label>
                            <select class="form-select form-select-sm" id="status-{{ $record->id }}" name="status" onchange="this.form.submit()">
                                @foreach(['PENDING' => 'Pendiente', 'CONFIRMED' => 'Confirmada', 'COMPLETED' => 'Atendida', 'CANCELLED' => 'Cancelada'] as $status => $label)
                                    <option value="{{ $status }}" @selected($record->status === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    @if(($canAttendOrder ?? false) && $record instanceof \App\Models\Appointment)
                        @if($record->serviceOrder)
                            <form method="POST" action="{{ route('appointments.attend', $record) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success"><i class="bi bi-clipboard-check me-1"></i>Ver orden</button></form>
                        @elseif($record->status === \App\Models\Appointment::STATUS_CONFIRMED)
                            <form method="POST" action="{{ route('appointments.attend', $record) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-person-check me-1"></i>Atender</button></form>
                        @endif
                    @endif
                </td>@endif
            </tr>@endforeach</tbody>
        </table></div>
        {{ $records->links('pagination::bootstrap-5') }}
    @endif
    @if($serviceOrder)
    <div class="modal fade" id="serviceOrderModal{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5">Orden de atención #{{ str_pad((string) $serviceOrder->id, 6, '0', STR_PAD_LEFT) }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-5">Propietario</dt><dd class="col-7">{{ $serviceOrder->owner->full_name }}</dd>
                    <dt class="col-5">Mascota</dt><dd class="col-7">{{ $serviceOrder->pet->name }}</dd>
                    <dt class="col-5">Doctor</dt><dd class="col-7">{{ $serviceOrder->doctor?->user?->name ?? 'Sin asignar' }}</dd>
                    <dt class="col-5">Origen</dt><dd class="col-7">{{ $serviceOrder->origin === \App\Models\ServiceOrder::ORIGIN_EMERGENCY ? 'Emergencia' : ($serviceOrder->origin === \App\Models\ServiceOrder::ORIGIN_WALK_IN ? 'Atención directa' : 'Cita') }}</dd>
                    <dt class="col-5">Estado</dt><dd class="col-7">Abierta</dd>
                    <dt class="col-5">Pago</dt><dd class="col-7">Pendiente de pago</dd>
                </dl>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div></div>
    </div>
    <script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('serviceOrderModal{{ $serviceOrder->id }}')).show());</script>
    @endif
</div>
@if($canCreate) @include('modules.form', ['record' => null, 'modalId' => 'createRecord']) @endif
@if($canEmergencyOrder)
<div class="modal fade" id="emergencyOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('appointments.emergency') }}">
            @csrf
            <div class="modal-header"><h2 class="modal-title fs-5">Atención rápida / emergencia</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="text-secondary">Busca por DNI para reutilizar al propietario existente. Si no existe, se creará un registro provisional.</p><div class="row g-3">
                <div class="col-md-6"><label class="form-label">DNI</label><input class="form-control" name="dni" maxlength="30" required></div>
                <div class="col-md-6"><label class="form-label">Nombres</label><input class="form-control" name="first_name" maxlength="100" required></div>
                <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="phone" maxlength="30" required></div>
                <div class="col-md-6"><label class="form-label">Mascota</label><input class="form-control" name="pet_name" maxlength="100" required></div>
                <div class="col-md-6"><label class="form-label">Especie</label><select class="form-select" name="species_id" required><option value="">Seleccionar</option>@foreach($emergencySpecies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Motivo</label><textarea class="form-control" name="reason" rows="2" maxlength="255" required></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger"><i class="bi bi-lightning-charge me-1"></i>Crear atención</button></div>
        </form>
    </div></div>
</div>
@endif
@if($canEdit) @foreach($records as $record) @include('modules.form', ['modalId' => 'editRecord'.$record->id]) @endforeach @endif
@if($canDelete) @foreach($records as $record)
    <div class="modal fade" id="deleteRecord{{ $record->id }}" tabindex="-1" aria-labelledby="deleteRecord{{ $record->id }}Title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form data-confirmed-form method="POST" action="{{ route($base.'.destroy', [...($routeParams ?? []), 'record' => $record->id]) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($record) }}">
                <div class="modal-header"><h2 class="modal-title fs-5" id="deleteRecord{{ $record->id }}Title">Eliminar registro</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><div class="alert alert-danger d-none" data-form-errors role="alert"></div><p>¿Deseas eliminar <strong>{{ $record->name }}</strong>? Esta acción no se puede realizar si tiene citas o consultas relacionadas.</p><div class="d-none" data-edit-fields></div><div class="d-none" data-confirmation><p class="fw-semibold">Confirma la eliminación</p><dl class="row mb-0" data-summary></dl></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-back-to-edit>Volver</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" data-save-button>Revisar y eliminar</button></div>
            </form>
        </div></div>
    </div>
@endforeach @endif
@if($statusCatalog && $canEdit)
@foreach($records as $record)
<div class="modal fade" id="catalogStatus{{ $record->id }}" tabindex="-1" aria-labelledby="catalogStatusTitle{{ $record->id }}" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form data-confirmed-form method="POST" action="{{ route('admin.catalogs.status', ['catalog' => $statusCatalog, 'record' => $record->id]) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($record) }}"><input type="hidden" name="is_active" value="{{ $record->is_active ? 0 : 1 }}">
        <div class="modal-header"><h2 class="modal-title fs-5" id="catalogStatusTitle{{ $record->id }}">{{ $record->is_active ? 'Desactivar' : 'Activar' }} registro</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><div class="alert alert-danger d-none" data-form-errors></div><div data-edit-fields><p>{{ $record->name ?? $record->business_name }}</p><p class="text-secondary">El historial y las relaciones se conservarán.</p></div><div class="d-none" data-confirmation><dl class="row mb-0" data-summary></dl></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-back-to-edit>Volver</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" data-save-button>Revisar cambio</button></div>
    </form></div></div>
</div>
@endforeach
@endif
@endsection
