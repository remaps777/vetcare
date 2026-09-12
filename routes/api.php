<?php

use App\Http\Controllers\ApiOwnerPetController;
use App\Http\Controllers\ApiTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas de la API de VetCare.
|
*/

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
|
| Genera un token para utilizar la API.
| Limitado a 10 intentos por minuto.
|
*/

Route::post('/auth/token', [ApiTokenController::class, 'store'])
    ->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| PRUEBA DE SWAGGER
|--------------------------------------------------------------------------
|
| Ruta pública temporal para comprobar que Swagger funciona.
| No requiere token ni rol.
|
*/

Route::get('/swagger-test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Swagger funciona correctamente',
        'proyecto' => 'VetCare API',
    ]);
});

/*
|--------------------------------------------------------------------------
| USUARIO AUTENTICADO
|--------------------------------------------------------------------------
|
| Devuelve los datos del usuario correspondiente al token utilizado.
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware([
    'auth:sanctum',
    'active',
]);

/*
|--------------------------------------------------------------------------
| OWNER
|--------------------------------------------------------------------------
|
| Rutas exclusivas para usuarios autenticados con rol OWNER.
|
*/

Route::middleware([
    'auth:sanctum',
    'active',
    'role:OWNER',
])
    ->prefix('owner')
    ->name('api.owner.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | MASCOTAS DEL OWNER
        |--------------------------------------------------------------------------
        |
        | GET     /api/owner/pets
        | POST    /api/owner/pets
        | GET     /api/owner/pets/{pet}
        | PUT     /api/owner/pets/{pet}
        | PATCH   /api/owner/pets/{pet}
        | DELETE  /api/owner/pets/{pet}
        |
        */

        Route::apiResource('pets', ApiOwnerPetController::class);
    });
