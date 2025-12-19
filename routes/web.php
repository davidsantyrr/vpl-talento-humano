<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::get('/', function () {
    return view('index');
});

// Aceptar GET /login redirigiendo al formulario en '/'
Route::get('/login', function () {
    return redirect('/');
});

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::get('/menu', function () {
    return view('menu');
});
Route::get('/entregas', function () {
    return view('sidebarComponente');
});
Route::get('/registro', function () {
    return view('formRegitreUserExtern');
});