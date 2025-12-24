<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\VplAuth;
use App\Http\Controllers\articulos\ArticulosController;

Route::get('/', function () {
    return view('index');
});

// Aceptar GET /login redirigiendo al formulario en '/'
Route::get('/login', function () { return redirect('/'); });
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Proteger rutas con el middleware por clase directamente
Route::middleware([VplAuth::class])->group(function () {
    Route::get('/menus/menu', function () { return view('menus.menuth'); });
    Route::get('/menus/menuentrega', function () { return view('menus.menuEntrega'); });

    Route::get('/articulos', [ArticulosController::class, 'index'])->name('articulos.index');
    Route::post('/articulos/{sku}', [ArticulosController::class, 'update'])->name('articulos.update');
});