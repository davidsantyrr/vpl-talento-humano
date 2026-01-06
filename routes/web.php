<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\VplAuth;
use App\Http\Controllers\articulos\ArticulosController;
use App\Http\Controllers\entregasPdf\EntregaController;
use App\Http\Controllers\gestiones\gestionOperacionController;
use App\Http\Controllers\gestiones\gestionAreaController;
use App\Http\Controllers\gestiones\gestionCentroCostoController;
use App\Http\Controllers\gestiones\GestionUsuarioController;
use App\Http\Controllers\gestiones\gestionPeriodicidad;
use App\Http\Controllers\gestiones\gestionCorreosController;
use App\Http\Controllers\consultaEementosUsuario\controllerConsulta;
use App\Http\Controllers\ElementoXcargo\CargoController;
use App\Http\Controllers\ElementoXcargo\CargoProductosController;
use App\Http\Controllers\Recepcion\RecepcionController;
use App\Http\Controllers\ComprobanteController;

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
Route::post('/articulos-destruir', [ArticulosController::class, 'destruir'])->name('articulos.destruir');
Route::get('/articulos/constancias', [ArticulosController::class, 'listarConstancias'])->name('articulos.constancias');
Route::get('/articulos/constancias/{sku}', [ArticulosController::class, 'obtenerConstanciasPorSku'])->name('articulos.constancias.sku');
Route::get('/articulos/constancia/{archivo}', [ArticulosController::class, 'descargarConstancia'])->name('articulos.constancia.descargar');




Route::resource('gestionOperacion', gestionOperacionController::class);

Route::resource('gestionArea', gestionAreaController::class);

Route::resource('gestionCentroCosto', gestionCentroCostoController::class);



});
    // Gestión de periodicidades (CRUD)
    Route::resource('gestionPeriodicidad', App\Http\Controllers\gestiones\gestionPeriodicidad::class);
    // Ruta personalizada para guardado masivo
    Route::post('/gestionPeriodicidad/save', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'saveAll'])
        ->name('gestionPeriodicidad.saveAll');

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
    Route::get('/entregas/buscar', [RecepcionController::class, 'buscarEntregas'])->name('entregas.buscar');

    // Vista matriz cargo x subárea
    Route::get('/elementoxcargo/matriz', [CargoProductosController::class, 'matrix'])->name('elementoxcargo.productos.matriz');
    
    

Route::resource('gestionUsuario', GestionUsuarioController::class);

// AJAX: buscar usuario por número de documento
Route::get('/usuarios/buscar', [GestionUsuarioController::class, 'findByDocumento'])->name('usuarios.find');

Route::post('/formularioEntregas', [EntregaController::class, 'store'])
    ->name('entregas.store');

// Historial de entregas - ruta pública (fuera de middleware)
Route::get('/historial/entregas', [EntregaController::class, 'index'])->name('entregas.index');

// Historial unificado de entregas y recepciones
Route::get('/historial/unificado', [EntregaController::class, 'historialUnificado'])->name('historial.unificado');
Route::get('/historial/pdf', [EntregaController::class, 'descargarPDFIndividual'])->name('historial.pdf');
Route::get('/historial/pdf-masivo', [EntregaController::class, 'descargarPDFMasivo'])->name('historial.pdf.masivo');

Route::resource('gestionOperacion', gestionOperacionController::class);

Route::resource('gestionArea', gestionAreaController::class);

Route::resource('gestionCentroCosto', gestionCentroCostoController::class);

// Ruta para obtener productos de cargo_productos (sin filtros o con filtros opcionales)
Route::get('/cargo-productos', [App\Http\Controllers\entregasPdf\EntregaController::class, 'cargoProductos'])->name('cargo.productos');

// Ruta para buscar recepciones (API para modal de entregas)
Route::get('/recepciones/buscar', [EntregaController::class, 'buscarRecepciones'])->name('recepciones.buscar');

// Ruta para obtener nombres de productos por SKUs
Route::post('/productos/nombres', [EntregaController::class, 'obtenerNombresProductos'])->name('productos.nombres');

Route::post('/comprobantes/generar', [ComprobanteController::class, 'generar'])->name('comprobantes.generar');

// Ruta para descargar comprobante (archivo en storage/app/{dir}/{file})
Route::get('/comprobantes/{dir}/{file}', function($dir, $file) {
    $path = storage_path('app/' . $dir . '/' . $file);
    if (!file_exists($path)) abort(404);
    return response()->download($path);
})->where('dir', 'comprobantes_entregas|comprobantes_recepciones')->name('comprobantes.download');


Route::resource('gestionUsuario', GestionUsuarioController::class);
// Route para consulta de elementos por usuario (vista: consultaElementoUsuario.consulta)
Route::get('/consulta-elementos', [controllerConsulta::class, 'index'])->name('consultaElementoUsuario.consulta');
Route::resource('consultaElementos', controllerConsulta::class);
Route::post('/formularioEntregas', [EntregaController::class, 'store'])
    ->name('entregas.store');
Route::get('/formularioEntregas', [EntregaController::class, 'create'])
    ->name('formularioEntregas');

Route::resource('gestionCorreos', gestionCorreosController::class);