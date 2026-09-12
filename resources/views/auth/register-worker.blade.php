@extends('layouts.guest')
@section('title', 'Solicitud de acceso como trabajador')
@section('card-class', 'guest-card-lg')
@section('content')
<div class="text-center mb-4">
    <span class="brand-badge mb-2"><i class="bi bi-person-badge"></i> Equipo VetCare</span>
    <h1 class="h4 fw-bold">Solicitud de acceso como trabajador</h1>
    <p class="text-secondary small">Elige el puesto que solicitas. La clínica revisará tus datos antes de habilitar tu cuenta.</p>
</div>
<form method="POST" action="{{ route('register.worker.store') }}">
    @csrf
    @include('auth.worker-fields', ['profileField' => 'requested_profile_id'])
    <p class="small text-secondary mt-3">Podrás usar tu usuario y contraseña cuando la clínica apruebe tu solicitud.</p>
    <button class="btn btn-primary w-100 mt-2 mb-3">Enviar solicitud</button>
    <p class="text-center small mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
</form>
@endsection
