@extends('layouts.app')
@section('title', $title)
@section('page-title', $title)
@section('content')
<section class="vet-card p-4 p-md-5 text-center">
    <i class="bi bi-tools display-5 text-primary-vet" aria-hidden="true"></i>
    <h1 class="h3 fw-bold mt-3">{{ $title }}</h1>
    <h2 class="h5">Próximamente</h2>
    <p class="text-secondary">Estamos preparando este módulo para facilitar la atención veterinaria.</p>
    <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="btn btn-primary">Volver al inicio</a>
</section>
@endsection
