@extends('layouts.app')
@section('title', 'Perfil operativo')
@section('page-title', 'Cambiar perfil operativo')
@section('content')
<div class="card border-0 shadow-sm"><div class="card-body">
    <p class="mb-3"><strong>{{ $user->name }}</strong><br>Tipo inicial: {{ $user->account_type === 'DOCTOR' ? 'Doctor' : 'Usuario' }}</p>
    <form method="POST" action="{{ route('admin.users.profile.update', $user) }}" class="row g-3">
        @csrf @method('PATCH')
        <div class="col-md-8"><label class="form-label">Perfil</label><select class="form-select" name="profile_id" required>@foreach($profiles as $profile)<option value="{{ $profile->id }}" @selected($user->profile_id === $profile->id)>{{ $profile->name }}</option>@endforeach</select></div>
        <div class="col-12"><button class="btn btn-primary">Guardar perfil</button></div>
    </form>
</div></div>
@endsection
