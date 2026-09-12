@extends('layouts.app')
@section('title', 'Nuevo trabajador')
@section('page-title', 'Nuevo trabajador')
@section('content')
<div class="vet-card p-4 mx-auto" style="max-width: 850px">
    <h1 class="h4 fw-bold">Nuevo trabajador</h1>
    <p class="text-secondary">Crea una cuenta activa para una persona contratada. Sus accesos iniciales se asignan según el perfil.</p>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        @include('auth.worker-fields', ['profileField' => 'profile_id'])
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Crear trabajador</button><a class="btn btn-outline-secondary" href="{{ route('admin.users') }}">Cancelar</a></div>
    </form>
</div>
@endsection
