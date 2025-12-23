<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\VplAuth;

Route::get('/', function () {
    return view('index');
});

// Aceptar GET /login redirigiendo al formulario en '/'
Route::get('/login', function () {
    return redirect('/');
});

Route::post('/login', [AuthController::class, 'login'])->name('login');

// Proteger rutas con el middleware por clase directamente
Route::middleware([VplAuth::class])->group(function () {
    Route::get('/menu', function () { return view('menu'); });
    Route::get('/menuentrega', function () { return view('menuEntrega'); });
});