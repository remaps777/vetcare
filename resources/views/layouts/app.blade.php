<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') - VetCare</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <button class="sidebar-backdrop border-0" id="sidebarBackdrop" type="button" aria-label="Cerrar navegación" tabindex="-1"></button>
    <div class="app-wrapper">
        <aside class="sidebar" id="appSidebar" aria-label="Navegación principal">
            <div class="sidebar-header justify-content-between">
                <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="sidebar-brand"><i class="bi bi-heart-pulse-fill text-primary-vet fs-3"></i> VetCare</a>
                <button class="btn btn-outline-light btn-sm d-lg-none" id="sidebarClose" type="button" aria-label="Cerrar menú"><i class="bi bi-x-lg"></i></button>
            </div>
            @php
                $role = strtolower(auth()->user()->effectiveRole());
                $effectivePermissions = app(\App\Services\PermissionService::class)->effective(auth()->user());
                $can = static fn (string $permission): bool => (bool) ($effectivePermissions[$permission] ?? false);
                $links = match($role) {
                    'admin' => [
                        ['dashboard', 'Dashboard', 'grid-1x2-fill'],
                        ['owners', 'Propietarios', 'people'], ['pets', 'Mascotas', 'heart'], ['appointments', 'Citas', 'calendar-event'],
                        ['consultations', 'Consultas', 'clipboard2-pulse'],
                        ['users', 'Usuarios', 'shield-lock'],
                    ],
                    'doctor' => [
                        ['dashboard', 'Dashboard', 'grid-1x2-fill'], ['owners', 'Propietarios', 'people'], ['pets', 'Mascotas', 'heart'],
                        ['appointments', 'Citas', 'calendar-event'], ['consultations', 'Consultas', 'clipboard2-pulse'],
                    ],
                    'worker' => [['dashboard', 'Mi área de trabajo', 'house-door']],
                    default => [
                        ['dashboard', 'Inicio', 'house-door'], ['pets', 'Mis mascotas', 'heart'], ['appointments', 'Mis citas', 'calendar-event'],
                        ['history', 'Historial', 'file-medical'],
                    ],
                };
                $linkPermissions = [
                    'owners' => 'pacientes.ver', 'pets' => 'pacientes.ver', 'appointments' => 'citas.ver',
                    'consultations' => 'consultas.ver', 'medications' => 'medicamentos.ver', 'purchases' => 'compras.ver',
                    'users' => 'usuarios.ver',
                ];
                $links = array_values(array_filter($links, fn (array $link): bool => ! isset($linkPermissions[$link[0]]) || $can($linkPermissions[$link[0]])));
            @endphp
            <nav class="sidebar-nav">
                @foreach($links as [$name, $label, $icon])
                    <a href="{{ route($role.'.'.$name) }}" class="sidebar-link {{ request()->routeIs($role.'.'.$name) ? 'active' : '' }}" @if(request()->routeIs($role.'.'.$name)) aria-current="page" @endif>
                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                    </a>
                @endforeach
                @if($role !== 'admin' && $can('usuarios.ver'))<a href="{{ route('admin.users') }}" class="sidebar-link"><i class="bi bi-people"></i><span>Usuarios</span></a>@endif
                @php
                    $operationalInventoryProfile = in_array(auth()->user()->profile?->code, ['ADMINISTRADOR', 'INVENTARIO'], true);
                    $inventoryVisible = $can('inventario.ver') || $can('inventario.movimientos.ver') || $can('inventario.ingreso') || $can('inventario.ajustar') || $can('inventario.transferir');
                    $inventorySections = [
                        ['route' => 'admin.inventory.stocks', 'label' => 'Existencias', 'icon' => 'boxes', 'permission' => 'inventario.ver'],
                        ['route' => 'admin.inventory.movements', 'label' => 'Movimientos', 'icon' => 'arrow-left-right', 'permission' => 'inventario.movimientos.ver'],
                        ['route' => 'admin.products', 'label' => 'Productos', 'icon' => 'box-seam', 'permission' => 'productos.ver'],
                        ['route' => 'admin.medications', 'label' => 'Medicamentos', 'icon' => 'capsule', 'permission' => 'medicamentos.crear'],
                    ];
                    $visibleInventorySections = array_values(array_filter($inventorySections, fn (array $item): bool => $item['permission'] === null ? $inventoryVisible : $can($item['permission'])));
                    $inventoryOpen = request()->routeIs('admin.inventory.*', 'admin.products', 'admin.medications');
                @endphp
                @if($visibleInventorySections)
                    <div class="sidebar-group">
                        <button class="sidebar-link w-100 border-0 bg-transparent text-start {{ $inventoryOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#inventoryMenu" aria-expanded="{{ $inventoryOpen ? 'true' : 'false' }}">
                            <i class="bi bi-boxes" aria-hidden="true"></i><span class="flex-grow-1">Inventario</span><i class="bi bi-chevron-down small"></i>
                        </button>
                        <div class="collapse {{ $inventoryOpen ? 'show' : '' }}" id="inventoryMenu">
                            @foreach($visibleInventorySections as $item)
                                <a href="{{ route($item['route']) }}" class="sidebar-link ps-4 {{ request()->routeIs($item['route']) ? 'active' : '' }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @php
                    $configurationSections = [
                        ['route' => 'admin.settings', 'label' => 'Configuración de clínica', 'icon' => 'building-gear', 'permission' => 'configuracion.ver'],
                        ['route' => ($role === 'doctor' ? 'doctor' : 'admin').'.species', 'label' => 'Especies', 'icon' => 'tags', 'permission' => 'catalogos.ver'],
                        ['route' => ($role === 'doctor' ? 'doctor' : 'admin').'.breeds', 'label' => 'Razas', 'icon' => 'bezier2', 'permission' => 'catalogos.ver'],
                        ['route' => 'admin.item-types', 'label' => 'Tipos de artículo', 'icon' => 'list-check', 'permission' => 'tipos_articulo.ver'],
                        ['route' => 'admin.distributors', 'label' => 'Distribuidores', 'icon' => 'truck', 'permission' => 'distribuidores.ver'],
                        ['route' => 'admin.specialties', 'label' => 'Especialidades', 'icon' => 'stethoscope', 'permission' => 'especialidades.ver'],
                    ];
                    $visibleConfigurationSections = array_values(array_filter($configurationSections, fn (array $item): bool => $item['permission'] === null || $can($item['permission'])));
                    $configurationOpen = request()->routeIs('admin.settings', 'admin.species', 'admin.breeds', 'admin.distributors', 'admin.item-types', 'admin.specialties', 'doctor.species', 'doctor.breeds');
                @endphp
                @if($visibleConfigurationSections)
                    <div class="sidebar-group">
                        <button class="sidebar-link w-100 border-0 bg-transparent text-start {{ $configurationOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#configurationMenu" aria-expanded="{{ $configurationOpen ? 'true' : 'false' }}">
                            <i class="bi bi-gear" aria-hidden="true"></i><span class="flex-grow-1">Configuración</span><i class="bi bi-chevron-down small"></i>
                        </button>
                        <div class="collapse {{ $configurationOpen ? 'show' : '' }}" id="configurationMenu">
                            @foreach($visibleConfigurationSections as $item)
                                <a href="{{ route($item['route']) }}" class="sidebar-link ps-4 {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @php
                    $storeSections = [
                        ['route' => 'store.cashier', 'label' => 'Caja', 'icon' => 'cash-coin', 'permission' => 'ventas.crear'],
                        ['route' => 'store.sales', 'label' => 'Ventas', 'icon' => 'receipt', 'permission' => 'ventas.ver'],
                    ];
                    $visibleStoreSections = array_values(array_filter($storeSections, fn (array $item): bool => $can($item['permission'])));
                    $storeOpen = request()->routeIs('store.*');
                @endphp
                @if($visibleStoreSections)
                    <div class="sidebar-group">
                        <button class="sidebar-link w-100 border-0 bg-transparent text-start {{ $storeOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#storeMenu" aria-expanded="{{ $storeOpen ? 'true' : 'false' }}">
                            <i class="bi bi-shop" aria-hidden="true"></i><span class="flex-grow-1">Tienda</span><i class="bi bi-chevron-down small"></i>
                        </button>
                        <div class="collapse {{ $storeOpen ? 'show' : '' }}" id="storeMenu">
                            @foreach($visibleStoreSections as $item)
                                <a href="{{ route($item['route']) }}" class="sidebar-link ps-4 {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </nav>
            <div class="p-3 border-top border-secondary border-opacity-25 small">
                <div class="text-white fw-semibold text-break">{{ auth()->user()->name }}</div>
                <div class="text-break">{{ auth()->user()->username }}</div>
            </div>
        </aside>
        <div class="main-content">
            <header class="top-navbar gap-2">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle" type="button" aria-controls="appSidebar" aria-expanded="false" aria-label="Abrir menú"><i class="bi bi-list"></i></button>
                    <span class="fw-bold">@yield('page-title', 'Panel de control')</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-role-{{ $role }} d-none d-sm-inline">{{ auth()->user()->profile?->name ?? ['admin' => 'Administrador', 'doctor' => 'Médico veterinario', 'owner' => 'Propietario', 'worker' => 'Trabajador'][$role] }}</span>
                    <div class="dropdown">
                        <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de cuenta"><i class="bi bi-person-circle"></i><span class="d-none d-md-inline ms-2">Mi cuenta</span></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><span class="dropdown-item-text small text-break">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
                        </ul>
                    </div>
                </div>
            </header>
            <main class="page-body">
                @include('modules.notifications')
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
