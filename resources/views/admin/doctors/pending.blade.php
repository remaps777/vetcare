@extends('layouts.app')
@section('title', 'Doctores pendientes')
@section('page-title', 'Doctores')
@section('content')
<div class="mb-4"><h1 class="h3 fw-bold">Doctores pendientes</h1><p class="text-secondary">Valida cada solicitud para aprobar o rechazar el acceso profesional.</p></div>
<div class="vet-card p-3 p-md-4">
    @if($doctors->isEmpty())
        <div class="text-center py-5"><i class="bi bi-check-circle display-5 text-primary-vet"></i><h2 class="h5 mt-3">No hay solicitudes pendientes</h2><p class="text-secondary mb-0">Las nuevas solicitudes aparecerán aquí.</p></div>
    @else
        <div class="table-responsive">
            <table class="table align-middle" data-datatable>
                <thead><tr><th scope="col">Doctor</th><th scope="col">DNI / Colegiatura</th><th scope="col">Especialidad</th><th scope="col">Contacto</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    @foreach($doctors as $doctor)
                        <tr>
                            <td><strong>{{ $doctor->user->name }}</strong><div class="small text-secondary">{{ $doctor->user->username }}</div><span class="badge badge-status-pending">Pendiente</span></td>
                            <td>{{ $doctor->dni }}<div class="small text-secondary">{{ $doctor->license_number }}</div></td>
                            <td>{{ $doctor->specialty?->name ?? ($doctor->specialty ?? '—') }}</td>
                            <td>{{ $doctor->user->email }}<div class="small text-secondary">{{ $doctor->phone }}</div></td>
                            <td><div class="d-flex gap-2">
                                <form method="POST" action="{{ route('admin.doctors.approve', $doctor) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success" aria-label="Aprobar a {{ $doctor->user->name }}">Aprobar</button></form>
                                <form method="POST" action="{{ route('admin.doctors.reject', $doctor) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" aria-label="Rechazar a {{ $doctor->user->name }}">Rechazar</button></form>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $doctors->links('pagination::bootstrap-5') }}
    @endif
</div>
@endsection
