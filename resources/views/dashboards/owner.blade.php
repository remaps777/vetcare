@extends('layouts.app')
@section('title', 'Inicio')
@section('page-title', 'Inicio')
@section('content')
<div class="mb-4"><h1 class="h3 fw-bold">Hola, {{ auth()->user()->name }}</h1><p class="text-secondary">Este es el resumen del cuidado de tus mascotas.</p></div>
@include('partials.stats', ['icons' => ['heart', 'calendar-event', 'file-medical']])
@include('profile._form', ['user' => $profileUser, 'specialties' => $specialties])
<section class="vet-card p-4">
    <h2 class="h5 fw-bold mb-3">Mis mascotas</h2>
    <div class="row g-3">
        @forelse($pets as $pet)
            <div class="col-md-6 col-xl-4"><div class="border rounded-3 p-3"><i class="bi bi-heart text-primary-vet me-2"></i><strong>{{ $pet->name }}</strong><p class="small text-secondary mt-2 mb-0">{{ $pet->species->name }} · {{ $pet->breed->name }}</p></div></div>
        @empty
            <div class="col-12"><p class="text-secondary mb-0">Aún no tienes mascotas registradas. Contacta a la clínica para registrar a tu mascota.</p></div>
        @endforelse
    </div>
</section>
@include('partials.appointments')
@endsection
