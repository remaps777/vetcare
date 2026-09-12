<?php

use App\Http\Controllers\Admin\DoctorApprovalController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OperationalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WorkerApplicationController;
use App\Http\Middleware\AuthenticateWebOrSanctum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
    Route::view('/register/owner', 'auth.register-owner')->name('register.owner');
    Route::get('/register/worker', [WorkerApplicationController::class, 'create'])->name('register.worker');
    Route::redirect('/register/doctor', '/register/worker')->name('register.doctor');
    Route::post('/register/worker', [WorkerApplicationController::class, 'store'])->middleware('throttle:10,1')->name('register.worker.store');
    Route::post('/register/owner', [RegistrationController::class, 'store'])->middleware('throttle:10,1')->name('register.owner.store');
    Route::post('/register/doctor', [WorkerApplicationController::class, 'store'])->middleware('throttle:10,1')->name('register.doctor.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware([AuthenticateWebOrSanctum::class, 'active'])->group(function (): void {
    Route::post('/appointments/{appointment}/attend', [OperationalController::class, 'attendAppointment'])
        ->middleware('permission:ordenes_atencion.crear')
        ->name('appointments.attend');
    Route::post('/appointments/emergency', [OperationalController::class, 'createEmergencyOrder'])
        ->middleware('permission:ordenes_atencion.crear')
        ->name('appointments.emergency');
    Route::get('/dashboard', fn (Request $request) => redirect()->route($request->user()->dashboardRoute()))->name('dashboard');

    Route::patch('/admin/catalogs/{catalog}/{record}/status', [CatalogManagementController::class, 'status'])->whereNumber('record')->name('admin.catalogs.status');
    Route::view('/workspace', 'dashboards.worker')->name('worker.dashboard');
    Route::prefix('admin/worker-applications')->name('admin.worker-applications')->middleware('permission:solicitudes_trabajador.ver')->group(function (): void {
        Route::get('/', [WorkerApplicationController::class, 'index']);
        Route::patch('/{application}', [WorkerApplicationController::class, 'review'])->name('.review');
    });

    // ── Admin ─────────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function (): void {
        Route::redirect('/', '/admin/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        // Doctor approval
        Route::get('/doctors/pending', [DoctorApprovalController::class, 'index'])->name('doctors.pending');
        Route::patch('/doctors/{doctor}/approve', [DoctorApprovalController::class, 'approve'])->name('doctors.approve');
        Route::patch('/doctors/{doctor}/reject', [DoctorApprovalController::class, 'reject'])->name('doctors.reject');

        // Catalog — species & breeds
        foreach (['species', 'breeds'] as $catalog) {
            Route::get('/'.$catalog, [CatalogController::class, 'index'])->name($catalog);
            Route::post('/'.$catalog, [CatalogController::class, 'save'])->name($catalog.'.store');
            Route::patch('/'.$catalog.'/{record}', [CatalogController::class, 'save'])
                ->whereNumber('record')
                ->name($catalog.'.update');
        }

        // Inventory
        Route::get('/purchases', [InventoryController::class, 'purchases'])->name('purchases');
        Route::post('/purchases', [InventoryController::class, 'purchase'])->name('purchases.store');
        Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
        Route::post('/movements', [InventoryController::class, 'movement'])->name('movements.store');

        // Clinic settings
        Route::middleware('permission:configuracion.ver')->group(function (): void {
            Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
            Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        foreach (['owners', 'pets', 'appointments', 'consultations'] as $path) {
            Route::get('/'.$path, [OperationalController::class, 'index'])->defaults('module', $path)->name($path);
        }
        Route::post('/appointments', [OperationalController::class, 'storeAppointment'])->name('appointments.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('permission:usuarios.ver')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/create', [WorkerApplicationController::class, 'createInternal'])->middleware('permission:usuarios.editar')->name('users.create');
        Route::post('/users', [WorkerApplicationController::class, 'storeInternal'])->middleware('permission:usuarios.editar')->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->middleware('permission:usuarios.editar')->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->middleware('permission:usuarios.editar')->name('users.update');
        Route::get('/users/{user}/profile', [UserManagementController::class, 'profile'])->middleware('permission:usuarios.editar')->name('users.profile');
        Route::patch('/users/{user}/profile', [UserManagementController::class, 'changeProfile'])->middleware('permission:usuarios.editar')->name('users.profile.update');
        Route::get('/users/{user}/permissions', [UserManagementController::class, 'permissions'])->middleware('permission:usuarios.cambiar_permisos')->name('users.permissions');
        Route::get('/users/{user}/permissions/data', [UserManagementController::class, 'permissionData'])->middleware('permission:usuarios.cambiar_permisos')->name('users.permissions.data');
        Route::put('/users/{user}/permissions', [UserManagementController::class, 'syncPermissions'])->middleware('permission:usuarios.cambiar_permisos')->name('users.permissions.sync');
        Route::patch('/users/{user}/permissions', [UserManagementController::class, 'setPermission'])->middleware('permission:usuarios.cambiar_permisos')->name('users.permissions.update');
        Route::patch('/users/{user}/toggle', [UserManagementController::class, 'toggle'])->middleware('permission:usuarios.editar')->name('users.toggle');
        Route::post('/users/{user}/restore-password', [UserManagementController::class, 'restorePassword'])->middleware('permission:usuarios.restaurar_password')->name('users.restore-password');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/distributors', [CatalogManagementController::class, 'distributors'])->middleware('permission:distribuidores.ver')->name('distributors');
        Route::post('/distributors', [CatalogManagementController::class, 'saveDistributor'])->middleware('permission:distribuidores.crear')->name('distributors.store');
        Route::patch('/distributors/{record}', [CatalogManagementController::class, 'saveDistributor'])->middleware('permission:distribuidores.editar')->name('distributors.update');
        Route::get('/item-types', [CatalogManagementController::class, 'itemTypes'])->middleware('permission:tipos_articulo.ver')->name('item-types');
        Route::post('/item-types', [CatalogManagementController::class, 'saveItemType'])->middleware('permission:tipos_articulo.crear')->name('item-types.store');
        Route::patch('/item-types/{record}', [CatalogManagementController::class, 'saveItemType'])->middleware('permission:tipos_articulo.editar')->name('item-types.update');
        Route::get('/specialties', [CatalogManagementController::class, 'specialties'])->middleware('permission:especialidades.ver')->name('specialties');
        Route::post('/specialties', [CatalogManagementController::class, 'saveSpecialty'])->middleware('permission:especialidades.crear')->name('specialties.store');
        Route::patch('/specialties/{record}', [CatalogManagementController::class, 'saveSpecialty'])->middleware('permission:especialidades.editar')->name('specialties.update');
        Route::get('/medications', [InventoryController::class, 'medications'])->middleware('permission:medicamentos.ver')->name('medications');
        Route::post('/medications', [InventoryController::class, 'saveMedication'])->middleware('permission:medicamentos.crear')->name('medications.store');
        Route::patch('/medications/{record}', [InventoryController::class, 'saveMedication'])->middleware('permission:medicamentos.editar')->name('medications.update');
        Route::get('/products', [InventoryController::class, 'products'])->middleware('permission:productos.ver')->name('products');
        Route::post('/products', [InventoryController::class, 'saveProduct'])->middleware('permission:productos.crear')->name('products.store');
        Route::patch('/products/{record}', [InventoryController::class, 'saveProduct'])->middleware('permission:productos.editar')->name('products.update');
    });

    Route::prefix('admin/inventory')->name('admin.inventory.')->group(function (): void {
        Route::get('/', [InventoryController::class, 'inventoryStocks'])->name('index');
        Route::get('/stocks', [InventoryController::class, 'inventoryStocks'])->name('stocks');
        Route::get('/movements', [InventoryController::class, 'inventoryMovements'])->middleware('permission:inventario.movimientos.ver')->name('movements');
        Route::post('/entries', [InventoryController::class, 'registerEntry'])->middleware('permission:inventario.ingreso')->name('entries.store');
        Route::post('/exits', [InventoryController::class, 'registerExit'])->middleware('permission:inventario.ajustar')->name('exits.store');
        Route::post('/transfers', [InventoryController::class, 'transferStock'])->middleware('permission:inventario.transferir')->name('transfers.store');
        Route::post('/send-to-store', [InventoryController::class, 'sendToStore'])->middleware('permission:inventario.transferir')->name('send-to-store');
    });

    Route::prefix('store')->name('store.')->group(function (): void {
        Route::get('/showcase', [StoreController::class, 'showcase'])->middleware('permission:productos.ver')->name('showcase');
        Route::get('/cashier', [StoreController::class, 'cashier'])->middleware('permission:ventas.crear')->name('cashier');
        Route::post('/sales', [StoreController::class, 'store'])->middleware('permission:ventas.crear')->name('sales.store');
        Route::get('/sales', [StoreController::class, 'sales'])->middleware('permission:ventas.ver')->name('sales');
        Route::get('/sales/{sale}/pdf', [StoreController::class, 'pdf'])->middleware('permission:ventas.ver')->name('sales.pdf');
    });

    // ── Doctor ────────────────────────────────────────────────────────────────
    Route::prefix('doctor')->name('doctor.')->middleware('role:DOCTOR')->group(function (): void {
        Route::redirect('/', '/doctor/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'doctor'])->name('dashboard');

        foreach (['species', 'breeds'] as $catalog) {
            Route::get('/'.$catalog, [CatalogController::class, 'index'])->name($catalog);
            Route::post('/'.$catalog, [CatalogController::class, 'save'])->name($catalog.'.store');
            Route::patch('/'.$catalog.'/{record}', [CatalogController::class, 'save'])
                ->whereNumber('record')
                ->name($catalog.'.update');
        }

        // Doctor inventory views
        Route::get('/medications', [InventoryController::class, 'medications'])->name('medications');
        Route::post('/medications', [InventoryController::class, 'saveMedication'])->name('medications.store');
        Route::patch('/medications/{record}', [InventoryController::class, 'saveMedication'])->name('medications.update');
        Route::get('/purchases', [InventoryController::class, 'purchases'])->name('purchases');
        Route::post('/purchases', [InventoryController::class, 'purchase'])->name('purchases.store');
        Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
        Route::post('/movements', [InventoryController::class, 'movement'])->name('movements.store');
        Route::get('/products', [InventoryController::class, 'products'])->name('products');
        Route::post('/products', [InventoryController::class, 'saveProduct'])->name('products.store');
        Route::patch('/products/{record}', [InventoryController::class, 'saveProduct'])->name('products.update');

        foreach (['owners', 'pets', 'appointments', 'consultations'] as $path) {
            Route::get('/'.$path, [OperationalController::class, 'index'])->defaults('module', $path)->name($path);
        }
        Route::post('/appointments', [OperationalController::class, 'storeAppointment'])->name('appointments.store');
        Route::patch('/appointments/{record}/status', [OperationalController::class, 'updateAppointmentStatus'])->whereNumber('record')->name('appointments.status');
        Route::post('/consultations', [OperationalController::class, 'storeConsultation'])->name('consultations.store');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    // ── Owner ─────────────────────────────────────────────────────────────────
    Route::prefix('owner')->name('owner.')->middleware('role:OWNER')->group(function (): void {
        Route::redirect('/', '/owner/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'owner'])->name('dashboard');

        foreach (['pets', 'appointments', 'history'] as $path) {
            Route::get('/'.$path, [OperationalController::class, 'index'])->defaults('module', $path)->name($path);
        }
        Route::post('/pets', [OperationalController::class, 'savePet'])->name('pets.store');
        Route::patch('/pets/{record}', [OperationalController::class, 'savePet'])->name('pets.update');
        Route::delete('/pets/{record}', [OperationalController::class, 'destroyPet'])->name('pets.destroy');
        Route::get('/products', [InventoryController::class, 'products'])->name('products');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});
