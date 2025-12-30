<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\VplAuth;
use App\Http\Controllers\articulos\ArticulosController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\gestiones\gestionOperacionController;
use App\Http\Controllers\gestiones\gestionAreaController;
use App\Http\Controllers\gestiones\gestionCentroCostoController;

use App\Http\Controllers\ElementoXcargo\CargoController;
use App\Http\Controllers\ElementoXcargo\CargoProductosController;
use App\Http\Controllers\Recepcion\RecepcionController;

Route::get('/', function () {
    return view('index');
});

// Aceptar GET /login redirigiendo al formulario en '/'
Route::get('/login', function () {
    return redirect('/');
});
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Proteger rutas con el middleware por clase directamente
Route::middleware([VplAuth::class])->group(function () {
    Route::get('/menus/menu', function () {
        return view('menus.menuth');
    });
    Route::get('/menus/menuentrega', function () {
        return view('menus.menuEntrega');
    });

    Route::get('/articulos', [ArticulosController::class, 'index'])->name('articulos.index');
    Route::post('/articulos/{sku}', [ArticulosController::class, 'update'])->name('articulos.update');

Route::get('/formularioEntregas', function () {
    return view('formularioEntregas.formularioEntregas');
})->name('formularioEntregas');
Route::post('/formularioEntregas', [EntregaController::class, 'store'])
    ->name('entregas.store');

Route::resource('gestionOperacion', gestionOperacionController::class);

Route::resource('gestionArea', gestionAreaController::class);

Route::resource('gestionCentroCosto', gestionCentroCostoController::class);


    // Gestión de Cargos (CRUD)
    Route::get('/elementoxcargo/cargos', [CargoController::class, 'index'])->name('cargos.index');
    Route::post('/elementoxcargo/cargos', [CargoController::class, 'store'])->name('cargos.store');
    Route::put('/elementoxcargo/cargos/{cargo}', [CargoController::class, 'update'])->name('cargos.update');
    Route::delete('/elementoxcargo/cargos/{cargo}', [CargoController::class, 'destroy'])->name('cargos.destroy');

    // Asignación de productos por cargo
    Route::get('/elementoxcargo/productos', [CargoProductosController::class, 'index'])->name('elementoxcargo.productos');
    Route::post('/elementoxcargo/productos', [CargoProductosController::class, 'store'])->name('elementoxcargo.productos.store');
    Route::delete('/elementoxcargo/productos/{cargoProducto}', [CargoProductosController::class, 'destroy'])->name('elementoxcargo.productos.destroy');

    // Recepción de devoluciones
    Route::get('/recepcion', [RecepcionController::class, 'create'])->name('recepcion.create');
    Route::post('/recepcion', [RecepcionController::class, 'store'])->name('recepcion.store');
});
