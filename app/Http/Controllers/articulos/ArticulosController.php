<?php

namespace App\Http\Controllers\articulos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticulosController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 20;

        $productos = Producto::select('sku', 'name_produc', 'categoria_produc')
            ->orderBy('name_produc')
            ->paginate($perPage)
            ->appends(['per_page' => $perPage]);

        $skus = $productos->pluck('sku');
        // traer todas las filas de inventarios (pueden existir múltiples por SKU)
        $inventariosRows = DB::connection('mysql_third')
            ->table('inventarios as i')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'i.ubicaciones_id')
            ->whereIn('i.sku', $skus)
            ->select('i.sku','i.stock','i.estatus','u.ubicacion','u.bodega')
            ->orderBy('i.sku')
            ->get();
        // agrupar por SKU
        $inventariosBySku = $inventariosRows->groupBy('sku');

        $rowsHtml = '';
        foreach ($productos as $p) {
            $rows = $inventariosBySku->get($p->sku);
            if (!$rows || $rows->isEmpty()) {
                $rows = collect([ (object) [
                    'sku' => $p->sku,
                    'stock' => 0,
                    'estatus' => 'disponible',
                    'ubicacion' => '',
                    'bodega' => ''
                ] ]);
            }

            foreach ($rows as $inv) {
                $stock = (int) ($inv->stock ?? 0);
                $estatus = $inv->estatus ?? 'disponible';
                $ubicacionSel = $inv->ubicacion ?? '';
                $bodegaSel = $inv->bodega ?? '';

                // Botones de acción según el estatus
                $botonesAccion = '';
                if ($estatus === 'destruido') {
                    // Solo botón de ojo para ver constancias
                    $botonesAccion = '<button type="button" class="btn-icon view-constancias" title="Ver Constancias de Destrucción" aria-label="Ver Constancias" data-sku="' . e($p->sku) . '">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
                      . '<path d="M12 5C7 5 2.73 8.11 1 12.5 2.73 16.89 7 20 12 20s9.27-3.11 11-7.5C21.27 8.11 17 5 12 5z" stroke="currentColor" stroke-width="1.5" fill="none"/>'
                      . '<circle cx="12" cy="12.5" r="3.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
                      . '</svg>'
                      . '</button>'
                      . '<span style="color: #999; font-size: 0.875rem; margin-left: 8px;">Artículo destruido</span>';
                } else {
                    // Botones normales para otros estatus
                    $botonesAccion = '<button type="button" class="btn-icon location" title="Ubicación" aria-label="Ubicación">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s-7-6.16-7-11a7 7 0 1 1 14 0c0 4.84-7 11-7 11z" stroke="currentColor" stroke-width="1.2" fill="none"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon edit" title="Editar" aria-label="Editar">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25z" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M14.62 5.63l3.75 3.75" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon delete" title="Destruir" aria-label="Destruir">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2"/><path d="M8 6V4h8v2" stroke="currentColor" stroke-width="1.2"/><path d="M6 6l1 14h10l1-14" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>';
                }

                $rowsHtml .= '<tr data-sku="' . e($p->sku) . '" data-bodega="' . e($bodegaSel) . '" data-ubicacion="' . e($ubicacionSel) . '" data-estatus="' . e($estatus) . '" data-stock="' . e($stock) . '">'
                    . '<td>' . e($p->sku) . '</td>'
                    . '<td>' . e($p->name_produc) . '</td>'
                    . '<td>' . e($p->categoria_produc) . '</td>'
                    . '<td>' . e($bodegaSel ?: '-') . '</td>'
                    . '<td>' . e($ubicacionSel ?: '-') . '</td>'
                    . '<td>' . e(ucfirst($estatus)) . '</td>'
                    . '<td>' . e($stock) . '</td>'
                    . '<td>'
                      . '<div class="actions" style="display:inline-flex; gap:8px; align-items:center;">'
                      . $botonesAccion
                      . '</div>'
                    . '</td>'
                    . '</tr>';
            }
        }

        return view('articulos.articulos', [
            'rowsHtml' => $rowsHtml,
            'paginationHtml' => $this->buildPagination($productos, $perPage),
            'perPage' => $perPage,
            'status' => session('status'),
        ]);
    }

    private function buildPagination($productos, $perPage)
    {
        $paginationHtml = '';
        if ($productos->hasPages()) {
            $paginationHtml .= '<nav aria-label="Paginación"><ul class="pagination">';
            if ($productos->onFirstPage()) {
                $paginationHtml .= '<li class="disabled"><span>&lsaquo;</span></li>';
            } else {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->previousPageUrl() . '" rel="prev">&lsaquo;</a></li>';
            }
            $window = $perPage <= 10 ? 3 : ($perPage <= 20 ? 5 : 7);
            $start = max(1, $productos->currentPage() - intdiv($window, 2));
            $end = min($productos->lastPage(), $start + $window - 1);
            for ($page = $start; $page <= $end; $page++) {
                if ($page == $productos->currentPage()) {
                    $paginationHtml .= '<li class="active"><span>' . $page . '</span></li>';
                } else {
                    $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->url($page) . '">' . $page . '</a></li>';
                }
            }
            if ($productos->hasMorePages()) {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->nextPageUrl() . '" rel="next">&rsaquo;</a></li>';
            } else {
                $paginationHtml .= '<li class="disabled"><span>&rsaquo;</span></li>';
            }
            $paginationHtml .= '</ul></nav>';
        }
        return $paginationHtml;
    }

    public function update(Request $request, string $sku)
    {
        $data = $request->validate([
            'bodega' => ['nullable','string','max:255'],
            'ubicacion' => ['nullable','string','max:255'],
            'estatus' => ['nullable','in:disponible,perdido,prestado,destruido'],
            'stock' => ['required','integer','min:0'],
            'per_page' => ['nullable','integer'],
            'from_status' => ['nullable','in:disponible,perdido,prestado,destruido'],
            'new_location' => ['nullable','in:1']
        ]);

        // upsert ubicaciones si el usuario envía datos
        $ubicacionesId = null;
        if (!empty($data['bodega']) || !empty($data['ubicacion'])) {
            $existingU = DB::connection('mysql_third')->table('ubicaciones')
                ->where('bodega', $data['bodega'] ?? '')
                ->where('ubicacion', $data['ubicacion'] ?? '')
                ->first();
            if ($existingU) {
                $ubicacionesId = (int) $existingU->id;
            } else {
                $ubicacionesId = (int) DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
                    'bodega' => $data['bodega'] ?? '',
                    'ubicacion' => $data['ubicacion'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // helper: ubicacion por defecto
        $getDefaultUbicId = function() {
            $row = DB::connection('mysql_third')->table('ubicaciones')
                ->where('bodega', '')
                ->where('ubicacion', '')
                ->first();
            if ($row) return (int) $row->id;
            return (int) DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
                'bodega' => '',
                'ubicacion' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $targetStatus = $data['estatus'] ?? 'disponible';
        $qty = (int) $data['stock'];
        $fromStatus = $data['from_status'] ?? null;
        $isNewLocation = isset($data['new_location']) && $data['new_location'] === '1';

        if ($isNewLocation) {
            // Crear nueva fila de inventarios para la nueva ubicación
            $insertUbicId = !is_null($ubicacionesId) ? $ubicacionesId : $getDefaultUbicId();
            DB::connection('mysql_third')->table('inventarios')->insert([
                'sku' => $sku,
                'stock' => 0, // comienza en 0; luego podrá transferir
                'estatus' => $targetStatus,
                'ubicaciones_id' => $insertUbicId,
            ]);
        } else if ($fromStatus && $fromStatus !== $targetStatus && $qty > 0) {
            // Transferencia entre estatus (generalizado)
            $origin = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $fromStatus)
                ->first();
            if ($origin) {
                $move = min($qty, (int) $origin->stock);
                $newOriginStock = max(0, (int) $origin->stock - $move);
                if ($newOriginStock > 0) {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->update(['stock' => $newOriginStock]);
                } else {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->delete();
                }

                $dest = DB::connection('mysql_third')->table('inventarios')
                    ->where('sku', $sku)
                    ->where('estatus', $targetStatus)
                    ->first();

                $destUbicId = !is_null($ubicacionesId)
                    ? $ubicacionesId
                    : ($dest->ubicaciones_id ?? ($origin->ubicaciones_id ?? $getDefaultUbicId()));

                if ($dest) {
                    $update = [ 'stock' => ((int) $dest->stock) + $move ];
                    if (!is_null($ubicacionesId)) { $update['ubicaciones_id'] = $destUbicId; }
                    DB::connection('mysql_third')->table('inventarios')->where('id', $dest->id)->update($update);
                } else {
                    DB::connection('mysql_third')->table('inventarios')->insert([
                        'sku' => $sku,
                        'stock' => $move,
                        'estatus' => $targetStatus,
                        'ubicaciones_id' => $destUbicId,
                    ]);
                }
            }
        } else {
            // Upsert simple por sku + estatus
            $inv = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $targetStatus)
                ->first();

            if ($inv) {
                $update = [ 'sku' => $sku, 'stock' => $qty, 'estatus' => $targetStatus ];
                if (!is_null($ubicacionesId)) { $update['ubicaciones_id'] = $ubicacionesId; }
                DB::connection('mysql_third')->table('inventarios')->where('id', $inv->id)->update($update);
            } else {
                $insertUbicId = !is_null($ubicacionesId) ? $ubicacionesId : $getDefaultUbicId();
                DB::connection('mysql_third')->table('inventarios')->insert([
                    'sku' => $sku,
                    'stock' => $qty,
                    'estatus' => $targetStatus,
                    'ubicaciones_id' => $insertUbicId,
                ]);
            }
        }

        return redirect()->route('articulos.index', ['per_page' => (int) ($data['per_page'] ?? 20)])
            ->with('status', 'Inventario actualizado');
    }

    public function destruir(Request $request)
    {
        try {
            $data = $request->validate([
                'sku' => ['required','string'],
                'bodega' => ['nullable','string','max:255'],
                'ubicacion' => ['nullable','string','max:255'],
                'estatus' => ['required','in:disponible,perdido,prestado,destruido'],
                'cantidad' => ['required','integer','min:1'],
                'constancia' => ['required','file','mimes:pdf','max:5120'], // máx 5MB
                'per_page' => ['nullable','integer'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        }

        try {
            $sku = $data['sku'];
            $cantidad = (int) $data['cantidad'];
            $estatusOrigen = $data['estatus'];

            // Validar que no esté intentando destruir algo ya destruido
            if ($estatusOrigen === 'destruido') {
                return response()->json([
                    'success' => false,
                    'message' => 'El artículo ya está destruido'
                ], 400);
            }

            // Buscar inventario origen
            $inventarioOrigen = DB::connection('mysql_third')
                ->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $estatusOrigen)
                ->first();

            if (!$inventarioOrigen || (int)$inventarioOrigen->stock < $cantidad) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay suficiente stock {$estatusOrigen} para destruir"
                ], 400);
            }

            // Guardar archivo PDF
            if ($request->hasFile('constancia')) {
                $file = $request->file('constancia');
                
                // Crear directorio si no existe (storage/app/constancias_destruccion)
                $directory = storage_path('app/constancias_destruccion');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Generar nombre único: SKU_FECHA_TIMESTAMP.pdf
                $fecha = now()->format('Y-m-d');
                $timestamp = now()->timestamp;
                $nombreArchivo = "{$sku}_{$fecha}_{$timestamp}.pdf";
                
                // Mover archivo
                $file->move($directory, $nombreArchivo);
                $rutaArchivo = "constancias_destruccion/{$nombreArchivo}";
                
                Log::info('Constancia de destrucción guardada', [
                    'sku' => $sku,
                    'ruta_completa' => $directory . '/' . $nombreArchivo,
                    'ruta_relativa' => $rutaArchivo,
                    'nombre_archivo' => $nombreArchivo
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe cargar la constancia de destrucción'
                ], 400);
            }

            // Restar del inventario origen
            $nuevoStockOrigen = (int)$inventarioOrigen->stock - $cantidad;
            if ($nuevoStockOrigen > 0) {
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioOrigen->id)
                    ->update(['stock' => $nuevoStockOrigen]);
            } else {
                // Si llega a 0, eliminar la fila
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioOrigen->id)
                    ->delete();
            }

            // Buscar o crear inventario destruido
            $inventarioDestruido = DB::connection('mysql_third')
                ->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', 'destruido')
                ->first();

            if ($inventarioDestruido) {
                // Sumar al existente
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioDestruido->id)
                    ->update(['stock' => (int)$inventarioDestruido->stock + $cantidad]);
            } else {
                // Crear nuevo registro
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->insert([
                        'sku' => $sku,
                        'stock' => $cantidad,
                        'estatus' => 'destruido',
                        'ubicaciones_id' => $inventarioOrigen->ubicaciones_id ?? 1
                    ]);
            }

            // Registrar la destrucción en una tabla de log (opcional pero recomendado)
            try {
                $authUser = session('auth.user');
                $nombreUsuario = 'sistema';
                if (is_array($authUser) && isset($authUser['name'])) {
                    $nombreUsuario = $authUser['name'];
                } elseif (is_object($authUser) && isset($authUser->name)) {
                    $nombreUsuario = $authUser->name;
                }
                
                DB::connection('mysql_third')
                    ->table('log_destrucciones')
                    ->insert([
                        'sku' => $sku,
                        'cantidad' => $cantidad,
                        'estatus_origen' => $estatusOrigen,
                        'constancia_path' => $rutaArchivo,
                        'usuario' => $nombreUsuario,
                        'created_at' => now()
                    ]);
            } catch (\Exception $logError) {
                // Si falla el log, solo registrar el error pero continuar
                Log::warning('No se pudo guardar log de destrucción', ['error' => $logError->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Artículo destruido correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error destruyendo artículo', [
                'error' => $e->getMessage(),
                'sku' => $data['sku'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la destrucción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener constancias de destrucción por SKU
     */
    public function obtenerConstanciasPorSku($sku)
    {
        try {
            // Verificar si la tabla existe
            $tableExists = DB::connection('mysql_third')
                ->select("SHOW TABLES LIKE 'log_destrucciones'");
            
            if (empty($tableExists)) {
                Log::warning('Tabla log_destrucciones no existe, buscando en carpeta directamente');
                return $this->obtenerConstanciasDeDirectorio($sku);
            }

            // Buscar en la tabla de log
            $destrucciones = DB::connection('mysql_third')
                ->table('log_destrucciones')
                ->where('sku', $sku)
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Destrucciones encontradas en BD', [
                'sku' => $sku,
                'count' => $destrucciones->count()
            ]);

            if ($destrucciones->isEmpty()) {
                // Si no hay en BD, buscar en carpeta
                Log::info('No hay registros en BD, buscando en carpeta');
                return $this->obtenerConstanciasDeDirectorio($sku);
            }

            $constancias = [];
            foreach ($destrucciones as $destruccion) {
                $rutaCompleta = storage_path('app/' . $destruccion->constancia_path);
                $existe = file_exists($rutaCompleta);
                
                Log::info('Verificando archivo', [
                    'path' => $destruccion->constancia_path,
                    'ruta_completa' => $rutaCompleta,
                    'existe' => $existe
                ]);
                
                if ($existe) {
                    $nombreArchivo = basename($destruccion->constancia_path);
                    $constancias[] = [
                        'id' => $destruccion->id,
                        'sku' => $destruccion->sku,
                        'cantidad' => $destruccion->cantidad,
                        'estatus_origen' => $destruccion->estatus_origen ?? 'N/A',
                        'usuario' => $destruccion->usuario ?? 'Sistema',
                        'fecha' => $destruccion->created_at,
                        'fecha_formateada' => date('d/m/Y H:i', strtotime($destruccion->created_at)),
                        'archivo' => $nombreArchivo,
                        'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $nombreArchivo]),
                        'tamano' => filesize($rutaCompleta),
                        'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2)
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total' => count($constancias),
                'constancias' => $constancias
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo constancias por SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Intentar buscar en directorio como fallback
            return $this->obtenerConstanciasDeDirectorio($sku);
        }
    }

    /**
     * Obtener constancias directamente del directorio (fallback)
     */
    private function obtenerConstanciasDeDirectorio($sku)
    {
        try {
            $directory = storage_path('app/constancias_destruccion');
            
            if (!file_exists($directory)) {
                Log::warning('Directorio de constancias no existe', ['path' => $directory]);
                return response()->json([
                    'success' => true,
                    'sku' => $sku,
                    'total' => 0,
                    'constancias' => [],
                    'message' => 'No hay constancias registradas'
                ]);
            }

            $archivos = array_diff(scandir($directory), ['.', '..']);
            $constancias = [];

            foreach ($archivos as $archivo) {
                // Verificar que el archivo pertenece al SKU buscado
                if (strpos($archivo, $sku) === 0 && pathinfo($archivo, PATHINFO_EXTENSION) === 'pdf') {
                    $rutaCompleta = $directory . '/' . $archivo;
                    
                    // Extraer información del nombre del archivo: SKU_FECHA_TIMESTAMP.pdf
                    $partes = explode('_', pathinfo($archivo, PATHINFO_FILENAME));
                    $fecha = count($partes) >= 2 ? $partes[1] : date('Y-m-d');
                    
                    $constancias[] = [
                        'id' => null,
                        'sku' => $sku,
                        'cantidad' => 'N/A',
                        'estatus_origen' => 'N/A',
                        'usuario' => 'Sistema',
                        'fecha' => $fecha,
                        'fecha_formateada' => date('d/m/Y', strtotime($fecha)),
                        'archivo' => $archivo,
                        'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $archivo]),
                        'tamano' => filesize($rutaCompleta),
                        'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2)
                    ];
                }
            }

            // Ordenar por fecha descendente
            usort($constancias, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            Log::info('Constancias encontradas en directorio', [
                'sku' => $sku,
                'count' => count($constancias)
            ]);

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total' => count($constancias),
                'constancias' => $constancias,
                'source' => 'directorio'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo constancias de directorio', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener constancias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar constancia de destrucción
     */
    public function descargarConstancia($nombreArchivo)
    {
        $ruta = storage_path('app/constancias_destruccion/' . $nombreArchivo);
        
        if (!file_exists($ruta)) {
            abort(404, 'Constancia no encontrada');
        }
        
        return response()->download($ruta);
    }

    /**
     * Listar constancias de destrucción
     */
    public function listarConstancias()
    {
        $directory = storage_path('app/constancias_destruccion');
        
        if (!file_exists($directory)) {
            return response()->json([
                'success' => true,
                'constancias' => [],
                'message' => 'No hay constancias registradas'
            ]);
        }
        
        $archivos = array_diff(scandir($directory), ['.', '..']);
        $constancias = [];
        
        foreach ($archivos as $archivo) {
            if (pathinfo($archivo, PATHINFO_EXTENSION) === 'pdf') {
                $rutaCompleta = $directory . '/' . $archivo;
                $constancias[] = [
                    'nombre' => $archivo,
                    'tamano' => filesize($rutaCompleta),
                    'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($rutaCompleta)),
                    'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $archivo])
                ];
            }
        }
        
        // Ordenar por fecha de modificación descendente
        usort($constancias, function($a, $b) {
            return strtotime($b['fecha_modificacion']) - strtotime($a['fecha_modificacion']);
        });
        
        return response()->json([
            'success' => true,
            'total' => count($constancias),
            'directorio' => $directory,
            'constancias' => $constancias
        ]);
    }
}
