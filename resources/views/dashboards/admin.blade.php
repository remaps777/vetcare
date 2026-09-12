@extends('layouts.app')
@section('title', 'Dashboard administrativo')
@section('page-title', 'Dashboard')
@section('content')
<div class="mb-4"><h1 class="h3 fw-bold">Resumen de la clínica</h1><p class="text-secondary">Bienvenido, {{ auth()->user()->name }}. Consulta la actividad y las solicitudes de registro.</p></div>
@include('partials.stats', ['icons' => ['people', 'person-badge', 'hourglass-split', 'heart', 'calendar-event']])
@if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.ver'))
    <section class="vet-card p-4">
        <h2 class="h5 fw-bold mb-3">Solicitudes de trabajadores</h2>
        <div class="row g-3 mb-4">
            @foreach(['PENDING' => 'Pendientes', 'ON_HOLD' => 'En espera', 'REJECTED' => 'Rechazadas'] as $status => $label)
                <div class="col-md-4"><div class="border rounded p-3"><span class="text-secondary">{{ $label }}</span><div class="h3 mb-0 mt-1">{{ $applicationCounts[$status] ?? 0 }}</div></div></div>
            @endforeach
        </div>
        <h3 class="h6 fw-bold">Solicitudes actuales</h3>
        @forelse($currentApplications as $application)
            <details class="border rounded p-3 mb-3" open>
                <summary class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">{{ $application->first_name }} {{ $application->last_name }} · {{ $application->requestedProfile?->name }}</span>
                    <span class="badge text-bg-{{ ['PENDING' => 'warning', 'ON_HOLD' => 'info'][$application->status] }}">{{ \App\Models\WorkerApplication::STATUSES[$application->status] }}</span>
                </summary>
                <div class="row g-3 mt-1">
                    @foreach(['DNI' => $application->dni, 'Teléfono' => $application->phone, 'Correo' => $application->email, 'Usuario' => $application->username, 'Puesto solicitado' => $application->requestedProfile?->name, 'Perfil asignado' => $application->assignedProfile?->name, 'Especialidad' => $application->specialty?->name, 'Colegiatura' => $application->license_number, 'Fecha' => $application->created_at?->format('d/m/Y H:i')] as $label => $value)
                        <div class="col-md-4"><span class="small text-secondary d-block">{{ $label }}</span><span class="text-break">{{ $value ?: '—' }}</span></div>
                    @endforeach
                </div>
                @if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.aprobar'))
                    <button class="btn btn-success btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#dashboardApproveApplication{{ $application->id }}">Aprobar y activar cuenta</button>
                @endif
                @if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.rechazar'))
                    <button class="btn btn-outline-secondary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#dashboardDecisionApplication{{ $application->id }}">Cambiar estado</button>
                @endif
            </details>
        @empty
            <p class="text-center text-secondary py-3 mb-0">No hay solicitudes actuales.</p>
        @endforelse
    </section>

    <section class="vet-card p-4 mt-4">
        <h2 class="h5 fw-bold mb-3">Historial de solicitudes</h2>
        <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
            <table class="table table-sm table-hover align-middle mb-0" data-datatable>
                <thead><tr><th>Solicitante</th><th>Usuario</th><th>Puesto</th><th>Estado</th><th>Fecha</th><th>Responsable</th><th>Observaciones</th><th>Acción</th></tr></thead>
                <tbody>
                @forelse($applicationHistory as $entry)
                    <tr>
                        <td>{{ $entry['applicant'] }}</td><td>{{ $entry['username'] }}</td><td>{{ $entry['profile'] }}</td><td>{{ $entry['status'] }}</td><td>{{ $entry['date'] }}</td><td>{{ $entry['actor'] }}</td><td class="text-break">{{ $entry['notes'] }}</td>
                        <td>
                            @if($entry['application_status'] === 'REJECTED' && (auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.aprobar')))
                                <form method="POST" action="{{ route('admin.worker-applications.review', $entry['application_id']) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="reaccept"><input type="hidden" name="_version" value="{{ $entry['application_version'] }}"><input type="hidden" name="profile_id" value="{{ $entry['application_profile_id'] }}">
                                    <button class="btn btn-sm btn-outline-success" type="submit">Reaceptar</button>
                                </form>
                            @else
                                <span class="text-secondary small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-3">No hay historial registrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
@if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.ver'))
    @foreach($currentApplications as $application)
        @if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.aprobar'))
            <div class="modal fade" id="dashboardApproveApplication{{ $application->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.worker-applications.review', $application) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($application) }}"><input type="hidden" name="action" value="approve">
                    <div class="modal-header"><h2 class="modal-title fs-5">Aprobar solicitud de {{ $application->first_name }} {{ $application->last_name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">@include('auth.worker-fields', ['record' => $application, 'prefix' => 'dashboardApplication'.$application->id, 'profileField' => 'profile_id', 'profiles' => $workerProfiles, 'specialties' => $workerSpecialties, 'showPassword' => false])<label class="form-label mt-3">Observaciones</label><textarea class="form-control" name="review_notes" maxlength="2000"></textarea></div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Aprobar y activar cuenta</button></div>
                </form></div></div>
            </div>
        @endif
        @if(auth()->user()->effectiveRole() === \App\Models\User::ROLE_ADMIN || auth()->user()->hasPermission('solicitudes_trabajador.rechazar'))
            <div class="modal fade" id="dashboardDecisionApplication{{ $application->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.worker-applications.review', $application) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($application) }}">
                    <div class="modal-header"><h2 class="modal-title fs-5">Cambiar estado de la solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><p>{{ $application->first_name }} {{ $application->last_name }}</p><label class="form-label">Nuevo estado</label><select class="form-select" name="action" required>@if($application->status === 'PENDING')<option value="hold">Poner en espera</option>@endif @if($application->status === 'PENDING' || $application->status === 'ON_HOLD')<option value="reject">Rechazar</option>@endif</select><label class="form-label mt-3">Motivo</label><textarea class="form-control" name="review_notes" maxlength="2000"></textarea></div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Confirmar cambio</button></div>
                </form></div></div>
            </div>
        @endif
    @endforeach
@endif
@endsection
