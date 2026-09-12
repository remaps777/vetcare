<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceso') - VetCare Clínica Veterinaria</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="guest-container">
        <div class="w-100 d-flex flex-column align-items-center">
            <!-- Brand Header -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-2">
                    <span class="p-2 rounded-3 bg-primary-vet text-white shadow-sm d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-heart-pulse-fill fs-4"></i>
                    </span>
                    <span class="fs-2 fw-bold text-dark tracking-tight">VetCare</span>
                </a>
                <p class="text-muted small mb-0">Sistema Integral de Gestión Clínica Veterinaria</p>
            </div>

            <!-- Main Card Container -->
            <div class="guest-card p-4 p-md-5 @yield('card-class', '')">
                @include('modules.notifications')

                @yield('content')
            </div>

            <!-- Footer -->
            <div class="mt-4 text-center text-muted small">
                &copy; {{ date('Y') }} VetCare. Todos los derechos reservados.
            </div>
        </div>
    </div>
</body>
</html>
