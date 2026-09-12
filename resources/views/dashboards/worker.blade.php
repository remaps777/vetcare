@extends('layouts.app')
@section('title', 'Mi área de trabajo')
@section('page-title', 'Mi área de trabajo')
@section('content')
<div class="vet-card p-4">
    <h1 class="h4">Bienvenido, {{ auth()->user()->name }}</h1>
    <p>Perfil: {{ auth()->user()->operational_profile_name }}</p>
    <p class="text-secondary mb-0">Usa el menú para acceder a las funciones habilitadas. Si necesitas otro acceso, comunícate con la administración de la clínica.</p>
</div>
@endsection
