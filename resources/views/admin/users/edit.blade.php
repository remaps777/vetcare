@extends('layouts.app')
@section('title', 'Editar usuario')
@section('page-title', 'Editar datos de usuario')
@section('content')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="row g-3">
        @csrf @method('PUT')
        <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="phone" value="{{ old('phone', $user->phone) }}"></div>
        <div class="col-md-6"><label class="form-label">Perfil</label><select class="form-select" name="profile_id" required>@foreach($profiles as $profile)<option value="{{ $profile->id }}" @selected($user->profile_id === $profile->id)>{{ $profile->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="is_active" required><option value="1" @selected($user->is_active)>Activo</option><option value="0" @selected(! $user->is_active)>Inactivo</option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Guardar cambios</button><a class="btn btn-light" href="{{ route('admin.users') }}">Cancelar</a></div>
    </form>
    @if(auth()->user()->hasPermission('usuarios.restaurar_password'))
    <hr><form method="POST" action="{{ route('admin.users.restore-password', $user) }}">@csrf<button class="btn btn-outline-warning">Restaurar contraseña</button></form>
    @endif
    <hr><form method="POST" action="{{ route('admin.users.toggle', $user) }}">@csrf @method('PATCH')<button class="btn btn-outline-{{ $user->is_active ? 'danger' : 'success' }}">{{ $user->is_active ? 'Desactivar' : 'Activar' }}</button></form>
</div></div>
@endsection
