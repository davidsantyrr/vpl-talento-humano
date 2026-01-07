<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\SubArea;
use App\Models\Entrega;
use App\Models\Producto;
use App\Models\Usuarios;
use App\Models\ElementoXEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Jobs\EnviarCorreoEntrega;


class EntregaController extends Controller
{
    /** Mostrar el formulario de entregas */
    public function create()
    {
        try {
            // Intentar obtener operaciones (puede que la tabla no exista o tenga otro nombre)
            try {
                $operations = \Illuminate\Support\Facades\DB::table('gestion_operaciones')->get();
            } catch (\Exception $e) {
                \Log::warning('No se pudo cargar operaciones', ['error' => $e->getMessage()]);
                $operations = collect(); // Colección vacía si falla
            }
            
            // Obtener cargos
            $cargos = \App\Models\Cargo::orderBy('nombre')->get();
            
            // Obtener todos los productos
            $allProducts = \App\Models\Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();
            
            return view('formularioEntregas.formularioEntregas', compact('operations', 'cargos', 'allProducts'));
        } catch (\Exception $e) {
            \Log::error('Error en create de EntregaController', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Intentar cargar la vista con datos vacíos
            $operations = collect();
            $cargos = collect();
            $allProducts = collect();
            
            return view('formularioEntregas.formularioEntregas', compact('operations', 'cargos', 'allProducts'))
                ->with('error', 'Advertencia: No se pudieron cargar algunos datos del formulario');
        }
    }

    /** Mostrar historial de entregas (ruta pública) */
    public function index(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();
        $query = Entrega::with(['operacion','usuario','elementos'])->orderBy('created_at', 'desc');
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->whereHas('usuario', function($uq) use ($q){
                $uq->where('nombres', 'like', "%{$q}%")
                   ->orWhere('apellidos', 'like', "%{$q}%")
                   ->orWhere('numero_documento', 'like', "%{$q}%");
            });
        }
        if ($request->filled('operacion')) {
            $query->where('operacion_id', $request->input('operacion'));
        }

        $entregas = $query->paginate(15)->withQueryString();

        return view('formularioEntregas.HistorialEntregas', compact('entregas','operations'));
    }

    /** Mostrar historial unificado de entregas y recepciones */
    public function historialUnificado(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();
        
        // Obtener entregas
        $queryEntregas = DB::table('entregas')
            ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('sub_areas', 'entregas.operacion_id', '=', 'sub_areas.id')
            ->select([
                'entregas.id',
                'entregas.created_at',
                'entregas.tipo_entrega as tipo',
                DB::raw("'entrega' as registro_tipo"),
                DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'entregas.recibido'
            ])
            ->whereNull('entregas.deleted_at');

        // Obtener recepciones
        $queryRecepciones = DB::table('recepciones')
            ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
            ->select([
                'recepciones.id',
                'recepciones.created_at',
                'recepciones.tipo_recepcion as tipo',
                DB::raw("'recepcion' as registro_tipo"),
                DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'recepciones.entregado as recibido'
            ])
            ->whereNull('recepciones.deleted_at');

        // Aplicar filtros
        if ($request->filled('q')) {
            $q = $request->input('q');
            $queryEntregas->where(function($query) use ($q) {
                $query->where('entregas.numero_documento', 'like', "%{$q}%")
                      ->orWhere('entregas.nombres', 'like', "%{$q}%")
                      ->orWhere('entregas.apellidos', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
            $queryRecepciones->where(function($query) use ($q) {
                $query->where('recepciones.numero_documento', 'like', "%{$q}%")
                      ->orWhere('recepciones.nombres', 'like', "%{$q}%")
                      ->orWhere('recepciones.apellidos', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                      ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
        }

        if ($request->filled('operacion')) {
            $queryEntregas->where('entregas.operacion_id', $request->input('operacion'));
            $queryRecepciones->where('recepciones.operacion_id', $request->input('operacion'));
        }

        if ($request->filled('tipo_registro')) {
            $tipoRegistro = $request->input('tipo_registro');
            if ($tipoRegistro === 'entrega') {
                $queryRecepciones = null;
            } elseif ($tipoRegistro === 'recepcion') {
                $queryEntregas = null;
            }
        }

        // Unir ambas consultas
        $registros = collect();
        if ($queryEntregas) {
            $registros = $registros->merge($queryEntregas->get());
        }
        if ($queryRecepciones) {
            $registros = $registros->merge($queryRecepciones->get());
        }

        // Ordenar por fecha descendente
        $registros = $registros->sortByDesc('created_at')->values();

        // Paginar manualmente
        $perPage = 15;
        $currentPage = $request->input('page', 1);
        $total = $registros->count();
        $registros = $registros->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedRegistros = new \Illuminate\Pagination\LengthAwarePaginator(
            $registros,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Cargar elementos para cada registro
        $registros = $paginatedRegistros->getCollection()->map(function($registro) {
            if ($registro->registro_tipo === 'entrega') {
                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $registro->id)
                    ->select(['sku', 'cantidad'])
                    ->get();
            } else {
                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $registro->id)
                    ->select(['sku', 'cantidad'])
                    ->get();
            }
            $registro->elementos = $elementos;
            return $registro;
        });

        $paginatedRegistros->setCollection($registros);

        return view('historico.historialUnificado', compact('paginatedRegistros', 'operations'));
    }

    /** Procesar el envío del formulario, generar PDF y devolver descarga */
    public function store(Request $request)
    {
        try {
            Log::info('🔍 Iniciando store de entrega', [
                'tipo_documento' => $request->tipo_documento,
                'numero_documento' => $request->numberDocumento,
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'operacion_id' => $request->operacion_id,
                'elementos_count' => $request->elementos ? strlen($request->elementos) : 0
            ]);

            // Validación
            $validated = $request->validate([
                'tipo_documento' => 'required|string',
                'numberDocumento' => 'required|string',
                'nombre' => 'required|string',
                'apellidos' => 'nullable|string',
                'tipo' => 'required|string',
                'operacion_id' => 'required|integer',
                'elementos' => 'required|string',
                'firma' => 'required|string',
                'comprobante_path' => 'nullable|string',
                'recepcion_id' => 'nullable|integer',
                'cargo_id' => 'nullable|integer',
            ]);

            Log::info('✅ Validación exitosa');

            // Parsear elementos
            $elementos = json_decode($validated['elementos'], true);
            
            if (!$elementos || !is_array($elementos) || count($elementos) === 0) {
                Log::error('❌ Elementos vacíos o inválidos', ['elementos_raw' => $validated['elementos']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Debe agregar al menos un elemento a la entrega'
                ], 400);
            }

            Log::info('📦 Elementos parseados', ['count' => count($elementos), 'elementos' => $elementos]);

            // Crear la entrega
            $entrega = \App\Models\Entrega::create([
                'tipo_documento' => $validated['tipo_documento'],
                'numero_documento' => $validated['numberDocumento'],
                'nombres' => $validated['nombre'],
                'apellidos' => $validated['apellidos'] ?? '',
                'tipo' => $validated['tipo'],
                'operacion_id' => $validated['operacion_id'],
                'cargo_id' => $validated['cargo_id'] ?? null,
                'firma_entrega' => $validated['firma'],
                'comprobante_path' => $validated['comprobante_path'] ?? null,
                'recepcion_id' => $validated['recepcion_id'] ?? null,
                'recibido' => false,
            ]);

            Log::info('✅ Entrega creada', ['entrega_id' => $entrega->id]);

            // Guardar elementos
            foreach ($elementos as $elem) {
                $elementoData = [
                    'entrega_id' => $entrega->id,
                    'sku' => $elem['sku'] ?? null,
                    'nombre' => $elem['nombre'] ?? $elem['producto'] ?? 'Sin nombre',
                    'cantidad' => isset($elem['cantidad']) ? (int)$elem['cantidad'] : 1,
                ];
                
                $elementoCreado = \App\Models\EntregaElemento::create($elementoData);
                
                Log::info('✅ Elemento guardado', [
                    'elemento_id' => $elementoCreado->id,
                    'sku' => $elementoData['sku'],
                    'nombre' => $elementoData['nombre']
                ]);
            }

            Log::info('🎉 Entrega registrada exitosamente', ['entrega_id' => $entrega->id]);

            return response()->json([
                'success' => true,
                'message' => 'Entrega registrada exitosamente',
                'entrega_id' => $entrega->id
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación', [
                'errors' => $e->errors(),
                'request' => $request->except(['firma'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('❌ Error al guardar entrega', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request' => $request->except(['firma'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la entrega: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Lista de productos permitidos según cargo y subárea.
     * Retorna [{sku, name_produc}] usando la tabla local cargo_productos.
     */
    public function cargoProductos(Request $request)
    {
        $cargoId = (int) $request->query('cargo_id');
        $subAreaId = (int) $request->query('sub_area_id');
        try {
            $query = DB::table('cargo_productos')->select(['sku', 'name_produc']);
            if ($cargoId) { $query->where('cargo_id', $cargoId); }
            if ($subAreaId) { $query->where('sub_area_id', $subAreaId); }
            $rows = $query->orderBy('name_produc')->get();
            $data = $rows->map(function ($r) {
                return [
                    'sku' => (string) ($r->sku ?? ''),
                    'name_produc' => (string) ($r->name_produc ?? ''),
                ];
            })->filter(fn($x) => !empty($x['sku']))->values();
            return response()->json($data, 200);
        } catch (\Throwable $e) {
            Log::warning('cargo_productos query failed', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    /**
     * API: Buscar recepciones por número de documento
     */
    public function buscarRecepciones(Request $request)
    {
        $numero = $request->query('numero');
        try {
            $query = DB::table('recepciones')
                ->join('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                ->select([
                    'recepciones.id',
                    'recepciones.created_at',
                    'recepciones.nombres',
                    'recepciones.apellidos',
                    'recepciones.numero_documento',
                    'recepciones.tipo_documento',
                    'sub_areas.operationName as operacion'
                ])
                ->where('recepciones.entregado', false) // Solo recepciones no entregadas
                ->whereNull('recepciones.deleted_at')
                ->orderBy('recepciones.created_at', 'desc');

            if ($numero) {
                $query->where('recepciones.numero_documento', 'like', "%{$numero}%");
            }

            $recepciones = $query->limit(50)->get();

            // Cargar elementos de cada recepción
            $data = $recepciones->map(function ($r) {
                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $r->id)
                    ->select(['sku', 'cantidad'])
                    ->get();

                return [
                    'id' => $r->id,
                    'fecha' => $r->created_at,
                    'nombres' => $r->nombres,
                    'apellidos' => $r->apellidos ?? '',
                    'numero_documento' => $r->numero_documento,
                    'tipo_documento' => $r->tipo_documento,
                    'operacion' => $r->operacion,
                    'elementos' => $elementos->map(fn($e) => [
                        'sku' => $e->sku,
                        'cantidad' => $e->cantidad
                    ])->toArray()
                ];
            });

            return response()->json($data, 200);
        } catch (\Throwable $e) {
            Log::error('Error buscando recepciones', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    /**
     * API: Obtener nombres de productos por SKUs desde cargo_productos
     */
    public function obtenerNombresProductos(Request $request)
    {
        $skus = $request->input('skus', []);
        
        if (empty($skus) || !is_array($skus)) {
            return response()->json([], 200);
        }

        try {
            $productos = DB::table('cargo_productos')
                ->select(['sku', 'name_produc'])
                ->whereIn('sku', $skus)
                ->groupBy('sku', 'name_produc')
                ->get();

            return response()->json($productos, 200);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo nombres de productos', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }

    /**
     * API: Descargar PDF individual de una entrega o recepción
     */
    public function descargarPDFIndividual(Request $request)
    {
        try {
            $tipo = $request->query('tipo'); // 'entrega' o 'recepcion'
            $id = $request->query('id');

            if (!in_array($tipo, ['entrega', 'recepcion'])) {
                return response()->json(['error' => 'Tipo inválido'], 400);
            }

            if ($tipo === 'entrega') {
                $registro = DB::table('entregas')
                    ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'entregas.operacion_id', '=', 'sub_areas.id')
                    ->select([
                        'entregas.id',
                        'entregas.created_at',
                        'entregas.tipo_entrega as tipo',
                        'entregas.entrega_user',
                        DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'entregas.recibido'
                    ])
                    ->where('entregas.id', $id)
                    ->whereNull('entregas.deleted_at')
                    ->first();

                if (!$registro) {
                    return response()->json(['error' => 'Entrega no encontrada'], 404);
                }

                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $id)
                    ->select(['sku', 'cantidad'])
                    ->get();

            } else {
                $registro = DB::table('recepciones')
                    ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                    ->select([
                        'recepciones.id',
                        'recepciones.created_at',
                        'recepciones.tipo_recepcion as tipo',
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido'
                    ])
                    ->where('recepciones.id', $id)
                    ->whereNull('recepciones.deleted_at')
                    ->first();

                if (!$registro) {
                    return response()->json(['error' => 'Recepción no encontrada'], 404);
                }

                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $id)
                    ->select(['sku', 'cantidad'])
                    ->get();
            }

            $pdf = Pdf::loadView('pdf.comprobante', [
                'tipo' => $tipo,
                'registro' => $registro,
                'elementos' => $elementos
            ]);

            // Formatear fecha de creación del registro
            $fechaRegistro = \Carbon\Carbon::parse($registro->created_at)->format('Y-m-d');
            $tipoTexto = $tipo === 'entrega' ? 'Entrega' : 'Recepcion';
            $nombreArchivo = "{$tipoTexto}_{$fechaRegistro}_#{$id}.pdf";

            return $pdf->download($nombreArchivo);

        } catch (\Exception $e) {
            Log::error('Error descargando PDF individual', [
                'error' => $e->getMessage(),
                'tipo' => $request->query('tipo'),
                'id' => $request->query('id')
            ]);
            return response()->json(['error' => 'Error al generar PDF'], 500);
        }
    }

    /**
     * API: Descargar PDF masivo en ZIP
     */
    public function descargarPDFMasivo(Request $request)
    {
        try {
            $tipoRegistro = $request->query('tipo_registro');
            $operacionId = $request->query('operacion_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');

            // Validaciones
            if (!in_array($tipoRegistro, ['entrega', 'recepcion', 'todos'])) {
                return response()->json(['error' => 'Tipo de registro inválido'], 400);
            }

            // Obtener registros
            $registros = collect();

            if (in_array($tipoRegistro, ['entrega', 'todos'])) {
                $queryEntregas = DB::table('entregas')
                    ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'entregas.operacion_id', '=', 'sub_areas.id')
                    ->select([
                        'entregas.id',
                        'entregas.created_at',
                        'entregas.tipo_entrega as tipo',
                        'entregas.entrega_user',
                        DB::raw("'entrega' as registro_tipo"),
                        DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'entregas.recibido'
                    ])
                    ->whereNull('entregas.deleted_at')
                    ->whereBetween('entregas.created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

                if ($operacionId) {
                    $queryEntregas->where('entregas.operacion_id', $operacionId);
                }

                $registros = $registros->merge($queryEntregas->get());
            }

            if (in_array($tipoRegistro, ['recepcion', 'todos'])) {
                $queryRecepciones = DB::table('recepciones')
                    ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                    ->select([
                        'recepciones.id',
                        'recepciones.created_at',
                        'recepciones.tipo_recepcion as tipo',
                        DB::raw("NULL as entrega_user"),
                        DB::raw("'recepcion' as registro_tipo"),
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido'
                    ])
                    ->whereNull('recepciones.deleted_at')
                    ->whereBetween('recepciones.created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

                if ($operacionId) {
                    $queryRecepciones->where('recepciones.operacion_id', $operacionId);
                }

                $registros = $registros->merge($queryRecepciones->get());
            }

            if ($registros->isEmpty()) {
                return response()->json(['error' => 'No se encontraron registros'], 404);
            }

            // Crear directorio temporal
            $tempDir = storage_path('app/temp/pdf_masivo_' . time());
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            // Generar PDFs
            foreach ($registros as $registro) {
                $tipo = $registro->registro_tipo;
                
                if ($tipo === 'entrega') {
                    $elementos = DB::table('elemento_x_entrega')
                        ->where('entrega_id', $registro->id)
                        ->select(['sku', 'cantidad'])
                        ->get();
                } else {
                    $elementos = DB::table('elemento_x_recepcion')
                        ->where('recepcion_id', $registro->id)
                        ->select(['sku', 'cantidad'])
                        ->get();
                }

                $pdf = Pdf::loadView('pdf.comprobante', [
                    'tipo' => $tipo,
                    'registro' => $registro,
                    'elementos' => $elementos
                ]);

                // Formatear nombre con fecha de creación del registro
                $fechaRegistro = \Carbon\Carbon::parse($registro->created_at)->format('Y-m-d');
                $tipoTexto = $tipo === 'entrega' ? 'Entrega' : 'Recepcion';
                $nombreArchivo = "{$tipoTexto}_{$fechaRegistro}_#{$registro->id}.pdf";
                
                $pdf->save("{$tempDir}/{$nombreArchivo}");
            }

            // Crear ZIP
            $zipName = "registros_" . now()->format('Y-m-d_His') . ".zip";
            $zipPath = storage_path("app/temp/{$zipName}");
            
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('No se pudo crear el archivo ZIP');
            }

            // Agregar archivos al ZIP
            $files = glob("{$tempDir}/*.pdf");
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Limpiar archivos temporales
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($tempDir);

            return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error descargando PDF masivo', [
                'error' => $e->getMessage(),
                'params' => $request->query()
            ]);
            return response()->json(['error' => 'Error al generar ZIP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar inventario según tipo de entrega
     */
    private function actualizarInventarioEntrega($tipoEntrega, $items, $entregaId)
    {
        try {
            foreach ($items as $item) {
                if (empty($item['sku'])) continue;

                $sku = $item['sku'];
                $cantidad = (int) ($item['cantidad'] ?? 1);

                // Obtener ubicación por defecto
                $ubicacionId = $this->getOrCreateDefaultUbicacion();

                switch ($tipoEntrega) {
                    case 'prestamo':
                        // Restar de disponible y sumar a prestado
                        $this->transferirInventario($sku, $cantidad, 'disponible', 'prestado', $ubicacionId);
                        Log::info('Inventario actualizado: préstamo', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad,
                            'de' => 'disponible',
                            'a' => 'prestado'
                        ]);
                        break;

                    case 'primera vez':
                    case 'periodica':
                        // Restar de disponible (entrega definitiva)
                        $this->restarInventario($sku, $cantidad, 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: entrega definitiva', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad,
                            'tipo' => $tipoEntrega
                        ]);
                        break;

                    case 'cambio':
                        // Restar de disponible (se entrega artículo nuevo)
                        $this->restarInventario($sku, $cantidad, 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: cambio', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad
                        ]);
                        break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error actualizando inventario en entrega', [
                'error' => $e->getMessage(),
                'entrega_id' => $entregaId,
                'tipo' => $tipoEntrega
            ]);
            throw $e;
        }
    }

    /**
     * Transferir inventario entre estados
     */
    private function transferirInventario($sku, $cantidad, $estatusOrigen, $estatusDestino, $ubicacionId)
    {
        // Buscar inventario origen
        $inventarioOrigen = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatusOrigen)
            ->first();

        if (!$inventarioOrigen || (int)$inventarioOrigen->stock < $cantidad) {
            throw new \Exception("No hay suficiente stock disponible para SKU: {$sku}");
        }

        // Restar del origen
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

        // Buscar o crear inventario destino
        $inventarioDestino = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatusDestino)
            ->first();

        if ($inventarioDestino) {
            // Sumar al destino existente
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventarioDestino->id)
                ->update(['stock' => (int)$inventarioDestino->stock + $cantidad]);
        } else {
            // Crear nuevo registro de destino
            DB::connection('mysql_third')
                ->table('inventarios')
                ->insert([
                    'sku' => $sku,
                    'stock' => $cantidad,
                    'estatus' => $estatusDestino,
                    'ubicaciones_id' => $ubicacionId
                ]);
        }
    }

    /**
     * Restar inventario de un estado específico
     */
    private function restarInventario($sku, $cantidad, $estatus, $ubicacionId)
    {
        $inventario = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatus)
            ->first();

        if (!$inventario || (int)$inventario->stock < $cantidad) {
            throw new \Exception("No hay suficiente stock {$estatus} para SKU: {$sku}");
        }

        $nuevoStock = (int)$inventario->stock - $cantidad;
        if ($nuevoStock > 0) {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->update(['stock' => $nuevoStock]);
        } else {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->delete();
        }
    }

    /**
     * Sumar inventario a un estado específico
     */
    private function sumarInventario($sku, $cantidad, $estatus, $ubicacionId)
    {
        $inventario = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatus)
            ->first();

        if ($inventario) {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->update(['stock' => (int)$inventario->stock + $cantidad]);
        } else {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->insert([
                    'sku' => $sku,
                    'stock' => $cantidad,
                    'estatus' => $estatus,
                    'ubicaciones_id' => $ubicacionId
                ]);
        }
    }

    /**
     * Obtener o crear ubicación por defecto
     */
    private function getOrCreateDefaultUbicacion()
    {
        $ubicacion = DB::connection('mysql_third')
            ->table('ubicaciones')
            ->where('bodega', 'General')
            ->where('ubicacion', 'Almacén Principal')
            ->first();

        if ($ubicacion) {
            return (int)$ubicacion->id;
        }

        return (int) DB::connection('mysql_third')
            ->table('ubicaciones')
            ->insertGetId([
                'bodega' => 'General',
                'ubicacion' => 'Almacén Principal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
    }
}