<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $clinic->name }}: gestión de citas, mascotas e historia clínica veterinaria.">
    <title>{{ $clinic->name }} - Sistema Integral de Gestión Clínica Veterinaria</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="vetcare-landing">
        <nav class="navbar navbar-expand-lg bg-white shadow-sm py-3">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    <i class="bi bi-heart-pulse-fill text-teal fs-3" aria-hidden="true"></i>
                    <span>
                        <strong class="h5 mb-0 fw-bold text-dark d-block">{{ $clinic->name }}</strong>
                        <small class="text-muted">Sistema Integral de Gestión Clínica Veterinaria</small>
                    </span>
                </a>
                <a href="{{ route('login') }}" class="btn btn-outline-teal px-4 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Iniciar sesión
                </a>
            </div>
        </nav>

        <main>
            <section class="hero-section bg-light-teal py-5">
                <div class="container py-4 py-lg-5">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-6 text-center text-lg-start">
                            <span class="text-teal fw-bold small text-uppercase tracking-wide mb-2 d-block">
                                <i class="bi bi-shield-check me-1" aria-hidden="true"></i> Cuidado conectado
                            </span>
                            <h1 class="display-5 fw-bold text-dark mb-4">Cuidamos a tu mejor amigo con el mismo cariño que tú.</h1>
                            <p class="lead text-secondary mb-5">Gestiona citas, mascotas e historial médico en una plataforma segura para propietarios y el equipo de la clínica.</p>
                            <a href="#accesos" class="btn btn-teal btn-lg px-5 fw-semibold shadow-sm">
                                Únete a VetCare <i class="bi bi-arrow-down-short ms-1" aria-hidden="true"></i>
                            </a>
                        </div>
                        <div class="col-lg-6 text-center">
                            <div class="hero-illustration mx-auto" role="img" aria-label="Veterinario cuidando mascota">
                                <i class="bi bi-heart-pulse-fill" aria-hidden="true"></i>
                                <i class="bi bi-stars" aria-hidden="true"></i>
                                <i class="bi bi-shield-plus" aria-hidden="true"></i>
                                <i class="bi bi-person-heart" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="accesos" class="py-5 bg-white">
                <div class="container py-5">
                    <div class="text-center mb-5">
                        <span class="text-muted fw-semibold small text-uppercase tracking-wide d-block mb-2">Elige tu perfil</span>
                        <h2 class="h2 fw-bold text-dark mb-3">¿Cómo deseas ingresar?</h2>
                        <p class="text-secondary">Selecciona el flujo que corresponde a tu relación con la clínica.</p>
                    </div>

                    <div class="row g-4 justify-content-center mx-auto" style="max-width: 900px;">
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm card-hover-teal">
                                <div class="card-body p-4 p-lg-5 text-center d-flex flex-column">
                                    <div class="icon-wrapper bg-teal-light text-teal mb-4 mx-auto d-flex align-items-center justify-content-center rounded-circle">
                                        <i class="bi bi-person-heart fs-1" aria-hidden="true"></i>
                                    </div>
                                    <h3 class="h4 fw-bold mb-3">Soy propietario</h3>
                                    <p class="text-secondary mb-4">Crea tu cuenta para registrar a tus mascotas, ver su historial médico y agendar citas de manera inmediata.</p>
                                    <a href="{{ route('register.owner') }}" class="btn btn-teal w-100 fw-semibold py-2 mt-auto">Registrar mi cuenta</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm card-hover-dark">
                                <div class="card-body p-4 p-lg-5 text-center d-flex flex-column">
                                    <div class="icon-wrapper bg-dark-light text-dark mb-4 mx-auto d-flex align-items-center justify-content-center rounded-circle">
                                        <i class="bi bi-hospital fs-1" aria-hidden="true"></i>
                                    </div>
                                    <h3 class="h4 fw-bold mb-3">Soy trabajador</h3>
                                    <p class="text-secondary mb-4">Envía tu solicitud de acceso para unirte a nuestro equipo médico, de almacén, caja o administrativo.</p>
                                    <a href="{{ route('register.worker') }}" class="btn btn-outline-dark w-100 fw-semibold py-2 mt-auto">Enviar solicitud de acceso</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-light border-top py-4">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-center text-md-start">
                        <h2 class="h6 fw-bold mb-1">{{ $clinic->name }} Clínica Veterinaria</h2>
                        <p class="text-muted small mb-0">{{ collect([$clinic->phone, $clinic->email, $clinic->address])->filter()->join(' · ') ?: 'Atención integral para tu mascota' }}</p>
                    </div>
                    @if($clinic->facebook_url || $clinic->instagram_url || $clinic->tiktok_url || $clinic->whatsapp_url)
                        <nav class="social-links d-flex gap-3" aria-label="Redes sociales y contacto">
                            @if($clinic->facebook_url)<a href="{{ $clinic->facebook_url }}" class="social-btn facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a>@endif
                            @if($clinic->instagram_url)<a href="{{ $clinic->instagram_url }}" class="social-btn instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a>@endif
                            @if($clinic->tiktok_url)<a href="{{ $clinic->tiktok_url }}" class="social-btn tiktok" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="bi bi-tiktok" aria-hidden="true"></i></a>@endif
                            @if($clinic->whatsapp_url)<a href="{{ $clinic->whatsapp_url }}" class="social-btn whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>@endif
                        </nav>
                    @endif
                </div>
                <p class="text-center text-muted small mt-4 mb-0">&copy; {{ date('Y') }} {{ $clinic->name }}. Todos los derechos reservados.</p>
            </div>
        </footer>

        @if($clinic->whatsapp_url)
            <a href="{{ $clinic->whatsapp_url }}" class="whatsapp-float shadow-lg" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp" title="Contáctanos por WhatsApp">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
            </a>
        @endif
    </div>
</body>
</html>
