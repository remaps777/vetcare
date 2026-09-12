@extends('layouts.guest')
@section('title', 'Registro de Doctor')
@section('card-class', 'guest-card-lg')
@section('content')
<div class="text-center mb-4">
    <span class="brand-badge mb-2"><i class="bi bi-person-badge"></i> Profesionales veterinarios</span>
    <h1 class="h4 fw-bold">Solicita tu cuenta de doctor</h1>
    <p class="text-secondary small">Un administrador revisará tu solicitud antes de habilitar tu acceso.</p>
</div>
<form method="POST" action="{{ route('register.doctor.store') }}" novalidate>
    @csrf
    <div class="row g-3">
        @foreach(['first_name' => ['Nombres', 'text', 100, false], 'last_name' => ['Apellidos', 'text', 100, false], 'dni' => ['DNI', 'text', 8, true], 'license_number' => ['Colegiatura', 'text', 20, true], 'phone' => ['Teléfono', 'tel', 10, true], 'email' => ['Correo electrónico', 'email', 150, false], 'username' => ['Nombre de usuario', 'text', 50, false]] as $field => [$label, $type, $max, $isNumeric])
            <div class="col-md-6">
                <label for="{{ $field }}" class="form-label small fw-semibold">{{ $label }} <span class="text-danger">*</span></label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" required @if($field === 'username') autocomplete="username" @endif @if($isNumeric) inputmode="numeric" pattern="[0-9]*" data-numeric-only data-maxlength="{{ $max }}" @endif>
                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="col-12">
            <label for="specialty_id" class="form-label small fw-semibold">Especialidad <span class="text-danger">*</span></label>
            <select id="specialty_id" name="specialty_id" class="form-select @error('specialty_id') is-invalid @enderror" required>
                <option value="">Selecciona una especialidad</option>
                @foreach($specialties as $specialty)
                    <option value="{{ $specialty->id }}" @selected(old('specialty_id') == $specialty->id)>{{ $specialty->name }}</option>
                @endforeach
            </select>
            @error('specialty_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="password" class="form-label small fw-semibold">Contraseña <span class="text-danger">*</span></label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Mínimo 8 caracteres.</div>
        </div>
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label small fw-semibold">Confirmar contraseña <span class="text-danger">*</span></label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 mt-4 mb-3">Enviar solicitud de registro</button>
    <p class="text-center small mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="text-primary-vet">Inicia sesión</a></p>
</form>
@endsection
