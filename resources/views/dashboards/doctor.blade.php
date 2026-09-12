@extends('layouts.app')
@section('title', 'Dashboard del doctor')
@section('page-title', 'Dashboard')
@section('content')
<div class="mb-4"><h1 class="h3 fw-bold">Tu actividad clínica</h1><p class="text-secondary">Bienvenido, {{ auth()->user()->name }}. Las consultas recientes corresponden a los últimos 30 días.</p></div>
@include('partials.stats', ['icons' => ['calendar-day', 'calendar-event', 'heart', 'clipboard2-pulse']])
@include('profile._form', ['user' => $profileUser, 'specialties' => $specialties])
@include('partials.appointments')
@endsection
