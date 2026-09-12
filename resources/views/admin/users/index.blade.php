@extends('layouts.app')
@section('title', 'Usuarios')
@section('page-title', 'Administración de usuarios')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-4">
    @if(auth()->user()->hasPermission('usuarios.editar'))
        <a class="btn btn-primary px-4" href="{{ route('admin.users.create') }}"><i class="bi bi-person-plus me-2"></i>Nuevo trabajador</a>
    @endif
    @if(auth()->user()->hasPermission('solicitudes_trabajador.ver'))
        <a class="btn btn-outline-primary px-4" href="{{ route('admin.worker-applications') }}"><i class="bi bi-clipboard2-check me-2"></i>Solicitudes de trabajadores</a>
    @endif
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h5 class="mb-1">Usuarios</h5><p class="text-muted mb-0">Administra datos, perfiles y permisos desde esta pantalla.</p></div>
            <div class="input-group" style="max-width: 320px"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" data-user-search placeholder="Buscar usuario..."></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" data-users-table>
                <thead><tr><th>Nombre</th><th>Contacto</th><th>Tipo inicial</th><th>Perfil</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    @php($location = $user->owner?->address)
                    <tr data-user-row data-search="{{ strtolower($user->name.' '.$user->email.' '.$user->phone.' '.$location.' '.$user->profile?->name) }}">
                        <td><strong>{{ $user->name }}</strong><br><small class="text-muted">{{ $user->email }}</small></td>
                        <td>{{ $user->phone ?: '—' }}<br><small class="text-muted">{{ $location ?: 'Ubicación no registrada' }}</small></td>
                        <td>{{ $user->account_type === 'DOCTOR' ? 'Doctor' : 'Usuario' }}</td>
                        <td>{{ $user->profile?->name ?? 'Sin perfil' }}</td>
                        <td><span class="badge text-bg-{{ $user->is_active ? 'success' : 'secondary' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                    @if(auth()->user()->hasPermission('usuarios.editar'))
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal" title="Editar usuario" aria-label="Editar usuario" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-user-phone="{{ $user->phone }}" data-user-profile-id="{{ $user->profile_id }}" data-user-active="{{ $user->is_active ? '1' : '0' }}" data-user-update="{{ route('admin.users.update', ['user' => $user->getRouteKey()]) }}"><i class="bi bi-pencil me-1"></i>Editar</button>
                                    @endif
                                    @if(auth()->user()->hasPermission('usuarios.cambiar_permisos'))
                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#permissionsUserModal" title="Permisos" aria-label="Permisos" data-user-name="{{ $user->name }}" data-permissions-url="{{ route('admin.users.permissions.data', ['user' => $user->getRouteKey()]) }}" data-permissions-save="{{ route('admin.users.permissions.sync', ['user' => $user->getRouteKey()]) }}"><i class="bi bi-shield-lock me-1"></i>Permisos</button>
                                    @endif
                                    @if(auth()->user()->hasPermission('usuarios.restaurar_password'))
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#passwordUserModal" title="Restaurar contraseña" aria-label="Restaurar contraseña" data-user-name="{{ $user->name }}" data-password-action="{{ route('admin.users.restore-password', ['user' => $user->getRouteKey()]) }}"><i class="bi bi-key me-1"></i>Restaurar contraseña</button>
                                    @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>

@include('admin.users.modals')
@endsection
