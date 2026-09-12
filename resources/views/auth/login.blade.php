@extends('layouts.guest')

@section('title', 'Iniciar Sesión')

@section('content')
<div class="mb-4 text-center">
    <h4 class="fw-bold text-dark">Bienvenido de nuevo</h4>
    <p class="text-muted small">Ingresa tus credenciales para acceder a la plataforma</p>
</div>

<form method="POST" action="{{ route('login.store') }}" novalidate>
    @csrf

    <!-- Username or Email -->
    <div class="mb-3">
        <label for="login" class="form-label fw-semibold small text-secondary">Usuario o correo electrónico</label>
        <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted border-end-0">
                <i class="bi bi-person"></i>
            </span>
            <input type="text"
                   name="login"
                   id="login"
                   class="form-control border-start-0 @error('login') is-invalid @enderror"
                   placeholder="ej. juanperez o juan@correo.com"
                   value="{{ old('login') }}"
                   required
                   autofocus
                   autocomplete="username">
            @error('login')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- Password -->
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label fw-semibold small text-secondary mb-0">Contraseña</label>
        </div>
        <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted border-end-0">
                <i class="bi bi-lock"></i>
            </span>
            <input type="password"
                   name="password"
                   id="password"
                   class="form-control border-start-0 @error('password') is-invalid @enderror"
                   placeholder="••••••••"
                   required
                   autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- Remember Me -->
    <div class="mb-4 form-check">
        <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
        <label for="remember" class="form-check-label small text-muted">Recordarme en este equipo</label>
    </div>

    <!-- Submit Button -->
    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
        </button>
    </div>

    <!-- Registration Links -->
    <div class="border-top pt-3 text-center">
        <p class="small text-muted mb-2">¿Aún no tienes cuenta?</p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a href="{{ route('register.owner') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="bi bi-person-plus me-1"></i> Registrarme como propietario
            </a>
            <a href="{{ route('register.worker') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-hospital me-1"></i> Solicitar acceso como trabajador
            </a>
        </div>
    </div>
</form>
@endsection
