@extends('layouts.guest')

@section('title', 'Registro de Propietario')
@section('card-class', 'guest-card-lg')

@section('content')
<div class="mb-4 text-center">
    <div class="brand-badge mb-2">
        <i class="bi bi-person-heart"></i> Portal de Propietarios
    </div>
    <h4 class="fw-bold text-dark">Registra tu Cuenta de Dueño</h4>
    <p class="text-muted small">Crea tu cuenta para gestionar el historial, citas y vacunas de tus mascotas</p>
</div>

<form method="POST" action="{{ route('register.owner.store') }}" novalidate>
    @csrf

    <div class="row g-3">
        <!-- Nombres -->
        <div class="col-md-6">
            <label for="first_name" class="form-label fw-semibold small text-secondary">Nombres <span class="text-danger">*</span></label>
            <input type="text"
                   name="first_name"
                   id="first_name"
                   class="form-control @error('first_name') is-invalid @enderror"
                   value="{{ old('first_name') }}"
                   placeholder="ej. Carlos Alberto"
                   required>
            @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Apellidos -->
        <div class="col-md-6">
            <label for="last_name" class="form-label fw-semibold small text-secondary">Apellidos <span class="text-danger">*</span></label>
            <input type="text"
                   name="last_name"
                   id="last_name"
                   class="form-control @error('last_name') is-invalid @enderror"
                   value="{{ old('last_name') }}"
                   placeholder="ej. Mendoza Pérez"
                   required>
            @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- DNI -->
        <div class="col-md-6">
            <label for="dni" class="form-label fw-semibold small text-secondary">DNI / Documento de Identidad <span class="text-danger">*</span></label>
            <input type="text"
                   name="dni"
                   id="dni"
                   class="form-control @error('dni') is-invalid @enderror"
                   value="{{ old('dni') }}"
                   placeholder="ej. 71234567"
                   inputmode="numeric"
                   maxlength="8"
                   pattern="[0-9]{8}"
                   data-numeric-only
                   data-maxlength="8"
                   required>
            @error('dni')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Teléfono -->
        <div class="col-md-6">
            <label for="phone" class="form-label fw-semibold small text-secondary">Teléfono / Celular <span class="text-danger">*</span></label>
            <input type="text"
                   name="phone"
                   id="phone"
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone') }}"
                   placeholder="ej. 987654321"
                   inputmode="numeric"
                   maxlength="10"
                   pattern="[0-9]{1,10}"
                   data-numeric-only
                   data-maxlength="10"
                   required>
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Correo Electrónico -->
        <div class="col-md-6">
            <label for="email" class="form-label fw-semibold small text-secondary">Correo Electrónico <span class="text-danger">*</span></label>
            <input type="email"
                   name="email"
                   id="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}"
                   placeholder="ej. carlos@ejemplo.com"
                   required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Dirección -->
        <div class="col-md-6">
            <label for="address" class="form-label fw-semibold small text-secondary">Dirección (Opcional)</label>
            <input type="text"
                   name="address"
                   id="address"
                   class="form-control @error('address') is-invalid @enderror"
                   value="{{ old('address') }}"
                   placeholder="ej. Av. Las Flores 123">
            @error('address')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12"><hr class="my-2 text-secondary opacity-25"></div>

        <!-- Username -->
        <div class="col-md-12">
            <label for="username" class="form-label fw-semibold small text-secondary">Nombre de Usuario <span class="text-danger">*</span></label>
            <div class="input-group has-validation">
                <span class="input-group-text bg-light text-muted">@</span>
                <input type="text"
                       name="username"
                       id="username"
                       class="form-control @error('username') is-invalid @enderror"
                       value="{{ old('username') }}"
                       placeholder="ej. cmendoza"
                       required>
                @error('username')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text small">Este usuario lo utilizarás para iniciar sesión.</div>
        </div>

        <!-- Contraseña -->
        <div class="col-md-6">
            <label for="password" class="form-label fw-semibold small text-secondary">Contraseña <span class="text-danger">*</span></label>
            <input type="password"
                   name="password"
                   id="password"
                   autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Mínimo 8 caracteres"
                   required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirmar Contraseña -->
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label fw-semibold small text-secondary">Confirmar Contraseña <span class="text-danger">*</span></label>
            <input type="password"
                   name="password_confirmation"
                   id="password_confirmation"
                   autocomplete="new-password"
                   class="form-control"
                   placeholder="Repite tu contraseña"
                   required>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="d-grid mt-4 mb-3">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm py-2">
            <i class="bi bi-check2-circle me-1"></i> Registrar mi Cuenta de Propietario
        </button>
    </div>

    <div class="text-center small text-muted">
        ¿Ya tienes una cuenta registrada?
        <a href="{{ route('login') }}" class="text-decoration-none fw-semibold text-primary-vet">Inicia sesión aquí</a>
    </div>
</form>
@endsection
