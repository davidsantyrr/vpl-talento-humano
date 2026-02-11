<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\VplAuth;
use App\Http\Controllers\articulos\ArticulosController;
use App\Http\Controllers\entregasPdf\FormularioEntregasController;
use App\Http\Controllers\entregasPdf\HistorialEntregaController;
use App\Http\Controllers\entregasPdf\EntregaController; // para métodos que queden aquí
use App\Http\Controllers\gestiones\gestionOperacionController;
use App\Http\Controllers\gestiones\gestionAreaController;
use App\Http\Controllers\gestiones\gestionCentroCostoController;
use App\Http\Controllers\gestiones\GestionUsuarioController;
use App\Http\Controllers\gestiones\gestionPeriodicidad;
use App\Http\Controllers\gestiones\gestionCorreosController;
use App\Http\Controllers\gestiones\GestionArticulosController;
use App\Http\Controllers\consultaEementosUsuario\controllerConsulta;
use App\Http\Controllers\ElementoXcargo\CargoController;
use App\Http\Controllers\ElementoXcargo\CargoProductosController;
use App\Http\Controllers\Recepcion\RecepcionController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ElementoXUsuario\ElementoXUsuarioController;
use App\Http\Controllers\elementoPeriodicidad\elementoPeriodicidadController as ElementoPeriodicidadController;
use App\Http\Controllers\PDF\ComprobanteController as PdfComprobanteController;

Route::get('/', function () {
    return view('index');
});

// Endpoint de salud: listado de rutas (para validar en deploy)
Route::get('/health/routes', function(){
    $routes = [];
    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
        $routes[] = [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    }
    $only = request()->query('only');
    if ($only) {
        $routes = array_values(array_filter($routes, function($r) use ($only){
            return (stripos($r['uri'], $only) !== false) || (stripos($r['name'] ?? '', $only) !== false);
        }));
    }
    return response()->json(['count' => count($routes), 'routes' => $routes], 200);
})->name('health.routes');

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

    



});
    // Gestión de periodicidades (CRUD)
    Route::resource('gestionPeriodicidad', App\Http\Controllers\gestiones\gestionPeriodicidad::class);
    // Endpoints POST para compatibilidad con servidores que no permiten PUT/DELETE.
    // NOTA: no agregar nombres duplicados para no chocar con las rutas del resource.
    Route::post('/gestionPeriodicidad/{gestionPeriodicidad}', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'update']);
    Route::post('/gestionPeriodicidad/{gestionPeriodicidad}/delete', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'destroy']);

    // Compatibilidad con prefijo /gestiones (URLs generadas desde la UI)
    // No registramos un resource aquí para evitar duplicar nombres de ruta.
    Route::prefix('gestiones')->group(function(){
        Route::post('/gestionPeriodicidad/{gestionPeriodicidad}', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'update']);
        Route::post('/gestionPeriodicidad/{gestionPeriodicidad}/delete', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'destroy']);
        // ruta save sin nombre (la ruta con nombre está registrada más abajo)
        Route::post('/gestionPeriodicidad/save', [App\Http\Controllers\gestiones\gestionPeriodicidad::class, 'saveAll']);
    });

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
    // Usar prefijo /recepcion para evitar conflicto con /entregas/{entrega}
    Route::get('/recepcion/entregas/buscar', [RecepcionController::class, 'buscarEntregas'])->name('entregas.buscar');
    // Compatibilidad: ruta anterior sin nombre para quienes apunten a /entregas/buscar
    Route::get('/entregas/buscar', [RecepcionController::class, 'buscarEntregas']);

    // Vista matriz cargo x subárea
    Route::get('/elementoxcargo/matriz', [CargoProductosController::class, 'matrix'])->name('elementoxcargo.productos.matriz');
    
    

// Descargar plantilla de importación de usuarios (ruta específica antes del resource)
Route::get('/gestionUsuario/template', [GestionUsuarioController::class, 'downloadTemplate'])->name('gestionUsuario.template');

Route::resource('gestionUsuario', GestionUsuarioController::class);

// Importar usuarios desde Excel
Route::post('/gestionUsuario/import', [GestionUsuarioController::class, 'import'])->name('gestionUsuario.import');

// AJAX: buscar usuario por número de documento
Route::get('/usuarios/buscar', [GestionUsuarioController::class, 'findByDocumento'])->name('usuarios.find');
// Asignar producto a usuario (guardar en elemento_x_usuario)
Route::post('/usuarios/{id}/producto-asignado', [GestionUsuarioController::class, 'asignarProducto'])->name('usuarios.asignarProducto');
// Obtener productos asignados para un usuario (precarga en modal)
Route::get('/usuarios/{id}/productos-asignados', [GestionUsuarioController::class, 'productosAsignados'])->name('usuarios.productosAsignados');
// Eliminar asignación de producto a usuario
Route::delete('/usuarios/producto-asignado/{asignacionId}', [GestionUsuarioController::class, 'eliminarProductoAsignado'])->name('usuarios.productoAsignado.eliminar');

// formulario de entregas
Route::get('/formularioEntregas', [FormularioEntregasController::class, 'create'])->name('formularioEntregas');
Route::post('/formularioEntregas', [FormularioEntregasController::class, 'store'])->name('formularioEntregas.store');

// APIs usadas por el formulario
Route::get('/cargo-productos', [FormularioEntregasController::class, 'cargoProductos'])->name('cargo.productos');
Route::get('/recepciones/buscar', [FormularioEntregasController::class, 'buscarRecepciones'])->name('recepciones.buscar');
Route::post('/productos/nombres', [FormularioEntregasController::class, 'obtenerNombresProductos'])->name('productos.nombres');
Route::post('/_log_comprobante_hit', [FormularioEntregasController::class, 'logComprobanteHit']);

// Rutas historial
Route::get('/historial/entregas', [HistorialEntregaController::class, 'index'])->name('entregas.index');
Route::get('/entregas/{entrega}', [HistorialEntregaController::class, 'show'])->name('entregas.show');
Route::get('/historial/unificado', [HistorialEntregaController::class, 'historialUnificado'])->name('historial.unificado');
Route::get('/historial/pdf', [HistorialEntregaController::class, 'descargarPDFIndividual'])->name('historial.pdf');
Route::get('/historial/pdf-masivo', [HistorialEntregaController::class, 'descargarPDFMasivo'])->name('historial.pdf.masivo');
Route::get('historial/export-excel', [App\Http\Controllers\HistorialController::class, 'exportExcel'])->name('historial.export_excel');

// Ruta para descargar comprobante
Route::get('/comprobantes/{dir}/{file}', [HistorialEntregaController::class, 'downloadComprobante'])
    ->where('dir', 'comprobantes_entregas|comprobantes_recepciones')
    ->name('comprobantes.download');


Route::resource('gestionUsuario', GestionUsuarioController::class);
// Route para consulta de elementos por usuario (vista: consultaElementoUsuario.consulta)
Route::get('/consulta-elementos', [controllerConsulta::class, 'index'])->name('consultaElementoUsuario.consulta');
Route::resource('consultaElementos', controllerConsulta::class);
Route::post('/formularioEntregas', [EntregaController::class, 'store'])
    ->name('entregas.store');
Route::get('/formularioEntregas', [EntregaController::class, 'create'])
    ->name('formularioEntregas');

Route::resource('gestionCorreos', gestionCorreosController::class);
// AJAX: lookup user by email to auto-select role when creating a correo
Route::get('/gestionCorreos/lookup-user', [gestionCorreosController::class, 'lookupUser'])->name('gestionCorreos.lookupUser');
Route::resource('elementoXusuario', ElementoXUsuarioController::class);


// Registrar endpoint POST para logging desde cliente y mapear al método del controlador
Route::post('/_log_comprobante_hit', [EntregaController::class, 'logComprobanteHit']);

// Rutas para calendario de entregas periódicas (protegidas por middleware VplAuth)
Route::middleware([VplAuth::class])->group(function(){
    Route::get('/elemento-periodicidad', [ElementoPeriodicidadController::class, 'index'])->name('elementoPeriodicidad.index');
    Route::get('/elemento-periodicidad/usuarios/{sku}', [ElementoPeriodicidadController::class, 'usuariosForSku'])->name('elementoPeriodicidad.usuarios');
    Route::get('/elemento-periodicidad/productos-por-semana', [ElementoPeriodicidadController::class, 'productosPorSemana'])->name('elementoPeriodicidad.productosPorSemana');
});

// Ruta para generación de comprobantes (POST) usada por la vista JS (nombre requerido: comprobantes.generar)
Route::post('comprobantes/generar', [ComprobanteController::class, 'generar'])->name('comprobantes.generar');
Route::get('/articulos', [ArticulosController::class, 'index'])->name('articulos.index');
Route::get('/debug/usados', [ArticulosController::class, 'debugUsados'])->name('debug.usados');
Route::get('/articulos/export-inventario', [App\Http\Controllers\articulos\ArticulosController::class, 'exportInventario'])->name('articulos.exportInventario');
Route::post('/articulos/{sku}', [ArticulosController::class, 'update'])->name('articulos.update');
Route::post('/articulos/price', [ArticulosController::class, 'savePrice'])->name('articulos.savePrice');
Route::post('/articulos-destruir', [ArticulosController::class, 'destruir'])->name('articulos.destruir');
Route::get('/articulos/constancias', [ArticulosController::class, 'listarConstancias'])->name('articulos.constancias');
Route::get('/articulos/constancias/{sku}', [ArticulosController::class, 'obtenerConstanciasPorSku'])->name('articulos.constancias.sku');
Route::get('/articulos/constancia/{archivo}', [ArticulosController::class, 'descargarConstancia'])->name('articulos.constancia.descargar');
Route::post('/articulos/ubicacion/eliminar', [App\Http\Controllers\articulos\ArticulosController::class, 'eliminarUbicacion'])->name('articulos.ubicacion.eliminar');




Route::resource('gestionOperacion', gestionOperacionController::class);

// Gestión de notificaciones de inventario (CRUD desde una sola vista)
Route::get('/gestionNotificacionesInventario', [App\Http\Controllers\gestiones\GestionNotificacionesInventarioController::class, 'index'])
    ->middleware([VplAuth::class])->name('gestionNotificacionesInventario.index');
Route::post('/gestionNotificacionesInventario', [App\Http\Controllers\gestiones\GestionNotificacionesInventarioController::class, 'store'])
    ->middleware([VplAuth::class])->name('gestionNotificacionesInventario.store');
Route::put('/gestionNotificacionesInventario/{id}', [App\Http\Controllers\gestiones\GestionNotificacionesInventarioController::class, 'update'])
    ->middleware([VplAuth::class])->name('gestionNotificacionesInventario.update');
Route::delete('/gestionNotificacionesInventario/{id}', [App\Http\Controllers\gestiones\GestionNotificacionesInventarioController::class, 'destroy'])
    ->middleware([VplAuth::class])->name('gestionNotificacionesInventario.destroy');

Route::resource('gestionArea', gestionAreaController::class);

Route::resource('gestionCentroCosto', gestionCentroCostoController::class);
// Gestión de artículos (resource protegido)
    Route::resource('gestionArticulos', GestionArticulosController::class);

    
Route::get('/menus/menuGestiones', function () {
        return view('menus.menuGestiones');
    })->middleware([VplAuth::class])->name('menus.menuGestiones');
