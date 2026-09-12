@extends('layouts.app')
@section('title', 'Solicitudes de trabajadores')
@section('page-title', 'Solicitudes de trabajadores')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div><h1 class="h3 fw-bold">Solicitudes de trabajadores</h1><p class="text-secondary mb-0">Revisa cada solicitud antes de habilitar el acceso a la clínica.</p></div>
    @if(auth()->user()->hasPermission('usuarios.ver'))<a class="btn btn-outline-primary align-self-start" href="{{ route('admin.users') }}">Usuarios</a>@endif
</div>
<div class="vet-card p-3 p-md-4">
    <h2 class="h5">Historial de solicitudes</h2>
    <form class="d-flex flex-wrap gap-2 align-items-center my-3" method="GET">
        <label for="application-status">Estado</label>
        <select name="status" id="application-status" class="form-select w-auto">
            <option value="">Todos</option>
            @foreach(['PENDING' => 'Pendientes', 'ON_HOLD' => 'En espera', 'APPROVED' => 'Aprobadas', 'REJECTED' => 'Rechazadas'] as $status => $label)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-primary">Filtrar</button>
    </form>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nombre</th><th>DNI</th><th>Puesto solicitado</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse($applications as $application)
                <tr>
                    <td>{{ $application->first_name }} {{ $application->last_name }}</td><td>{{ $application->dni }}</td>
                    <td>{{ $application->requestedProfile->name }}</td><td>{{ $application->created_at?->format('d/m/Y H:i') }}</td>
                    <td><span class="badge text-bg-{{ ['PENDING' => 'warning', 'ON_HOLD' => 'info', 'APPROVED' => 'success', 'REJECTED' => 'secondary'][$application->status] }}">{{ \App\Models\WorkerApplication::STATUSES[$application->status] }}</span></td>
                    <td><div class="d-flex flex-wrap gap-1">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewApplication{{ $application->id }}">Ver</button>
                        @if($application->status !== 'APPROVED' && auth()->user()->hasPermission('solicitudes_trabajador.aprobar'))
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveApplication{{ $application->id }}">Aprobar</button>
                        @endif
                        @if(auth()->user()->hasPermission('solicitudes_trabajador.rechazar'))
                            @foreach(match($application->status) {'PENDING' => ['hold' => 'Poner en espera', 'reject' => 'Rechazar'], 'ON_HOLD' => ['reject' => 'Rechazar'], 'REJECTED' => ['reconsider' => 'Reconsiderar'], default => []} as $action => $label)
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#decideApplication{{ $application->id }}" data-review-action="{{ $action }}" data-review-label="{{ $label }}">{{ $label }}</button>
                            @endforeach
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No hay solicitudes para este filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $applications->links() }}
</div>
@foreach($applications as $application)
    <div class="modal fade" id="viewApplication{{ $application->id }}" tabindex="-1" aria-labelledby="viewApplicationTitle{{ $application->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="viewApplicationTitle{{ $application->id }}">Datos de la solicitud</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <h3 class="h5">{{ $application->first_name }} {{ $application->last_name }}</h3>
                <dl class="row">
                    @foreach(['DNI' => $application->dni, 'Teléfono' => $application->phone, 'Correo' => $application->email, 'Usuario' => $application->username, 'Puesto solicitado' => $application->requestedProfile->name, 'Perfil asignado' => $application->assignedProfile?->name, 'Especialidad' => $application->specialty?->name, 'Colegiatura' => $application->license_number] as $label => $value)
                        <dt class="col-sm-4">{{ $label }}</dt><dd class="col-sm-8 text-break">{{ $value ?: '—' }}</dd>
                    @endforeach
                </dl>
                <h3 class="h6 fw-bold">Historial de estados</h3>
                <ul class="list-group list-group-flush">
                    @foreach($application->history as $entry)
                        <li class="list-group-item px-0">
                            <strong>{{ \App\Models\WorkerApplication::STATUSES[$entry->after_data['status'] ?? 'PENDING'] }}</strong>
                            @if(isset($entry->before_data['status']))<span class="small text-secondary"> · Antes: {{ \App\Models\WorkerApplication::STATUSES[$entry->before_data['status']] }}</span>@endif
                            <div class="small text-secondary">{{ $entry->created_at?->format('d/m/Y H:i') }} · {{ $entry->user?->name ?? ($entry->action === 'WORKER_APPLICATION_IMPORTED' ? 'Solicitud anterior' : 'Solicitante') }}</div>
                            @if($entry->after_data['review_notes'] ?? null)<p class="mb-0 text-break">{{ $entry->after_data['review_notes'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div></div>
    </div>
    @if($application->status !== 'APPROVED' && auth()->user()->hasPermission('solicitudes_trabajador.aprobar'))
    <div class="modal fade" id="approveApplication{{ $application->id }}" tabindex="-1" aria-labelledby="approveApplicationTitle{{ $application->id }}" aria-hidden="true" @if((string) old('_application') === (string) $application->id && old('action') === 'approve') data-reopen-modal @endif>
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.worker-applications.review', $application) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="_application" value="{{ $application->id }}"><input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($application) }}"><input type="hidden" name="action" value="approve">
            <div class="modal-header"><h2 class="modal-title fs-5" id="approveApplicationTitle{{ $application->id }}">Aprobar solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p>Puesto solicitado: <strong>{{ $application->requestedProfile->name }}</strong>. Revisa los datos y el perfil a asignar.</p>
                @include('auth.worker-fields', ['record' => $application, 'prefix' => 'application'.$application->id, 'profileField' => 'profile_id', 'showPassword' => false])
                <label class="form-label mt-3" for="approval-notes{{ $application->id }}">Observaciones (opcional)</label>
                <textarea class="form-control" name="review_notes" id="approval-notes{{ $application->id }}" maxlength="2000">{{ (string) old('_application') === (string) $application->id ? old('review_notes') : '' }}</textarea>
                <p class="small text-secondary mt-3 mb-0">Al aprobar, la cuenta quedará activa con los accesos del perfil. Se conservarán las excepciones de permisos que ya existan.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Aprobar y activar cuenta</button></div>
        </form></div></div>
    </div>
    @endif
    <div class="modal fade" id="decideApplication{{ $application->id }}" data-application-decision tabindex="-1" aria-labelledby="decisionTitle{{ $application->id }}" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.worker-applications.review', $application) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($application) }}"><input type="hidden" name="action" data-decision-action>
            <div class="modal-header"><h2 class="modal-title fs-5" id="decisionTitle{{ $application->id }}" data-decision-label>Revisar solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p>{{ $application->first_name }} {{ $application->last_name }}</p><label class="form-label" for="decision-notes{{ $application->id }}">Motivo (opcional)</label><textarea class="form-control" name="review_notes" id="decision-notes{{ $application->id }}" maxlength="2000"></textarea><p class="small text-secondary mt-3 mb-0">La solicitud y su historial se conservarán.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" data-decision-label>Confirmar</button></div>
        </form></div></div>
    </div>
@endforeach
@endsection
