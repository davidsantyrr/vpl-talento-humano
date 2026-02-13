<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;
use App\Models\Entrega;
use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class HistorialEntregaController extends Controller
{
    // Mostrar historial de entregas (página)
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
            $query->where('sub_area_id', $request->input('operacion'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? $perPage : 10;
        $entregas = $query->paginate($perPage)->withQueryString();

        return view('formularioEntregas.HistorialEntregas', compact('entregas','operations'));
    }

    // Mostrar detalle de una entrega (para route entregas.show)
    public function show($entrega)
    {
        try {
            $entrega = Entrega::with(['operacion','usuario','elementos'])->findOrFail($entrega);
            return view('formularioEntregas.entregaShow', compact('entrega'));
        } catch (\Throwable $e) {
            Log::warning('HistorialEntregaController@show error', ['id' => $entrega, 'msg' => $e->getMessage()]);
            abort(404);
        }
    }

    // Agregar historialUnificado (combina entregas y recepciones)
    public function historialUnificado(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();
        
        // Obtener entregas
        $queryEntregas = DB::table('entregas')
            ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
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
                'entregas.recibido',
                DB::raw('entregas.entrega_user as realizado_por')
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
                'recepciones.entregado as recibido',
                DB::raw('recepciones.recepcion_user as realizado_por')
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
            $queryEntregas->where('entregas.sub_area_id', $request->input('operacion'));
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

        // Paginar manualmente (respetar per_page)
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? $perPage : 10;
        $currentPage = (int) $request->input('page', 1);
        $total = $registros->count();
        $slice = $registros->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedRegistros = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Cargar elementos para cada registro
        $collectionWithElements = $paginatedRegistros->getCollection()->map(function($registro) {
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

        $paginatedRegistros->setCollection($collectionWithElements);

        return view('historico.historialUnificado', ['paginatedRegistros' => $paginatedRegistros, 'operations' => $operations]);
    }

    /**
     * Descargar comprobante individual (entrega o recepción) renderizado con la plantilla PDF.
     * Soporta query params: tipo=entrega|recepcion, id=<int>
     */
    public function descargarPDFIndividual(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required','in:entrega,recepcion'],
            'id' => ['required','integer']
        ]);

        $tipo = $data['tipo'];
        $id = (int) $data['id'];

        try {
            if ($tipo === 'entrega') {
                $registro = DB::table('entregas')
                    ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
                    ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                    ->select([
                        'entregas.id',
                        'entregas.created_at',
                        'entregas.tipo_entrega as tipo',
                        'entregas.comprobante_path as comprobante_path',
                        'entregas.entrega_user',
                        'entregas.usuarios_id as entrega_usuarios_id',
                        DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'usuarios_entregas.id as usuario_id',
                        'usuarios_entregas.cargo_id as cargo_id',
                        'sub_areas.operationName as operacion',
                        'entregas.recibido',
                        'cargos.nombre as cargo'
                    ])
                    ->where('entregas.id', $id)
                    ->first();

                Log::info('PDF Entrega - Registro obtenido', [
                    'id' => $id,
                    'usuarios_id' => $registro->entrega_usuarios_id ?? null,
                    'numero_documento' => $registro->numero_documento ?? null,
                    'cargo_join' => $registro->cargo ?? null,
                    'cargo_id' => $registro->cargo_id ?? null
                ]);

                // si no se obtuvo nombre de cargo por join, intentar resolver por cargo_id
                if ($registro && empty($registro->cargo) && !empty($registro->cargo_id)) {
                    $registro->cargo = DB::table('cargos')->where('id', $registro->cargo_id)->value('nombre');
                    Log::info('PDF Entrega - Cargo encontrado por cargo_id', ['cargo' => $registro->cargo]);
                }

                // Si aún no hay cargo, intentar buscar por numero_documento en usuarios_entregas
                if ($registro && empty($registro->cargo) && !empty($registro->numero_documento)) {
                    // Buscar con el numero_documento exacto y también normalizado
                    $docNum = trim($registro->numero_documento);
                    $usuarioEntrega = DB::table('usuarios_entregas')
                        ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                        ->where(function($q) use ($docNum) {
                            $q->where('usuarios_entregas.numero_documento', $docNum)
                              ->orWhere('usuarios_entregas.numero_documento', preg_replace('/[^0-9]/', '', $docNum));
                        })
                        ->select('cargos.nombre as cargo', 'usuarios_entregas.id as usr_id', 'usuarios_entregas.cargo_id as cargo_id_usr')
                        ->first();
                    
                    Log::info('PDF Entrega - Busqueda de cargo por numero_documento', [
                        'numero_documento' => $docNum,
                        'usuario_encontrado' => $usuarioEntrega ? true : false,
                        'cargo_encontrado' => $usuarioEntrega->cargo ?? null,
                        'usr_id' => $usuarioEntrega->usr_id ?? null,
                        'cargo_id' => $usuarioEntrega->cargo_id_usr ?? null
                    ]);
                    
                    if ($usuarioEntrega && !empty($usuarioEntrega->cargo)) {
                        $registro->cargo = $usuarioEntrega->cargo;
                    }
                }

                if (!$registro) { abort(404, 'Entrega no encontrada'); }

                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $id)
                    ->select(['sku', 'cantidad'])
                    ->get();

                // Obtener nombres de productos desde la base de datos secundaria
                $skus = $elementos->pluck('sku')->filter()->toArray();
                Log::info('PDF Entrega - SKUs a buscar', ['skus' => $skus]);
                
                if (!empty($skus)) {
                    $productosMap = [];
                    try {
                        // Búsqueda principal
                        $productos = Producto::whereIn('sku', $skus)
                            ->orWhereIn(DB::raw('LOWER(sku)'), array_map('mb_strtolower', $skus))
                            ->get();
                        
                        foreach ($productos as $p) {
                            $productosMap[$p->sku] = $p->name_produc;
                        }
                        
                        // Si no se encontraron todos, buscar uno por uno con más flexibilidad
                        foreach ($skus as $sku) {
                            if (isset($productosMap[$sku])) continue;
                            $skuLower = mb_strtolower(trim($sku));
                            
                            // Verificar si ya existe con case diferente
                            foreach ($productosMap as $k => $v) {
                                if (mb_strtolower($k) === $skuLower) {
                                    $productosMap[$sku] = $v;
                                    break;
                                }
                            }
                            
                            if (!isset($productosMap[$sku])) {
                                // Buscar individualmente
                                $producto = Producto::whereRaw('LOWER(sku) = ?', [$skuLower])->first();
                                if ($producto) {
                                    $productosMap[$sku] = $producto->name_produc;
                                }
                            }
                        }
                        
                        Log::info('PDF Entrega - Productos encontrados', ['map' => $productosMap]);
                    } catch (\Throwable $e) {
                        Log::warning('PDF Entrega - Error buscando productos', ['error' => $e->getMessage()]);
                    }
                    
                    // Crear mapa normalizado para búsqueda case-insensitive
                    $productosMapLower = [];
                    foreach ($productosMap as $k => $v) {
                        $productosMapLower[mb_strtolower($k)] = $v;
                    }
                    $elementos = $elementos->map(function ($el) use ($productosMap, $productosMapLower) {
                        $el->name_produc = $productosMap[$el->sku] 
                            ?? $productosMapLower[mb_strtolower($el->sku)] 
                            ?? null;
                        return $el;
                    });
                }
            } else { // recepcion
                $registro = DB::table('recepciones')
                    ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                    ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                    ->select([
                        'recepciones.id',
                        'recepciones.created_at',
                        'recepciones.tipo_recepcion as tipo',
                        'recepciones.comprobante_path as comprobante_path',
                        'recepciones.recepcion_user',
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'usuarios_entregas.id as usuario_id',
                        'usuarios_entregas.cargo_id as cargo_id',
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido',
                        'cargos.nombre as cargo'
                    ])
                    ->where('recepciones.id', $id)
                    ->first();

                // Asignar entrega_user desde recepcion_user para compatibilidad con la plantilla
                if ($registro) {
                    $registro->entrega_user = $registro->recepcion_user ?? null;
                }

                if ($registro && empty($registro->cargo) && !empty($registro->cargo_id)) {
                    $registro->cargo = DB::table('cargos')->where('id', $registro->cargo_id)->value('nombre');
                }

                // Si aún no hay cargo, intentar buscar por numero_documento en usuarios_entregas
                if ($registro && empty($registro->cargo) && !empty($registro->numero_documento)) {
                    $docNum = trim($registro->numero_documento);
                    $usuarioRecepcion = DB::table('usuarios_entregas')
                        ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                        ->where(function($q) use ($docNum) {
                            $q->where('usuarios_entregas.numero_documento', $docNum)
                              ->orWhere('usuarios_entregas.numero_documento', preg_replace('/[^0-9]/', '', $docNum));
                        })
                        ->select('cargos.nombre as cargo')
                        ->first();
                    if ($usuarioRecepcion && !empty($usuarioRecepcion->cargo)) {
                        $registro->cargo = $usuarioRecepcion->cargo;
                    }
                }

                if (!$registro) { abort(404, 'Recepción no encontrada'); }

                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $id)
                    ->select(['sku', 'cantidad'])
                    ->get();

                // Obtener nombres de productos desde la base de datos secundaria
                $skus = $elementos->pluck('sku')->filter()->toArray();
                if (!empty($skus)) {
                    $productosMap = [];
                    try {
                        $productos = Producto::whereIn('sku', $skus)
                            ->orWhereIn(DB::raw('LOWER(sku)'), array_map('mb_strtolower', $skus))
                            ->get();
                        
                        foreach ($productos as $p) {
                            $productosMap[$p->sku] = $p->name_produc;
                        }
                        
                        foreach ($skus as $sku) {
                            if (isset($productosMap[$sku])) continue;
                            $skuLower = mb_strtolower(trim($sku));
                            foreach ($productosMap as $k => $v) {
                                if (mb_strtolower($k) === $skuLower) {
                                    $productosMap[$sku] = $v;
                                    break;
                                }
                            }
                            if (!isset($productosMap[$sku])) {
                                $producto = Producto::whereRaw('LOWER(sku) = ?', [$skuLower])->first();
                                if ($producto) {
                                    $productosMap[$sku] = $producto->name_produc;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('PDF Recepcion - Error buscando productos', ['error' => $e->getMessage()]);
                    }
                    
                    $productosMapLower = [];
                    foreach ($productosMap as $k => $v) {
                        $productosMapLower[mb_strtolower($k)] = $v;
                    }
                    $elementos = $elementos->map(function ($el) use ($productosMap, $productosMapLower) {
                        $el->name_produc = $productosMap[$el->sku] 
                            ?? $productosMapLower[mb_strtolower($el->sku)] 
                            ?? null;
                        return $el;
                    });
                }
            }

            // Si existe comprobante guardado, servir ese archivo (incluye firma si se generó con ella)
            if (!empty($registro->comprobante_path) && !$request->boolean('force')) {
                $relative = ltrim(preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $registro->comprobante_path), '/');
                $candidates = [
                    storage_path('app/' . $relative),
                    storage_path('app/public/' . $relative),
                ];
                foreach ($candidates as $p) {
                    if (is_string($p) && file_exists($p)) {
                        $downloadName = basename($p);
                        return response()->download($p, $downloadName, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"'
                        ]);
                    }
                }
            }

            // Si se pasa ?debug=1, devolver la vista HTML para diagnóstico
            if ($request->boolean('debug')) {
                return view('pdf.comprobante', [
                    'tipo' => $tipo,
                    'registro' => (object) $registro,
                    'elementos' => $elementos,
                    'firma' => []
                ]);
            }

            $pdf = Pdf::loadView('pdf.comprobante', [
                'tipo' => $tipo,
                'registro' => (object) $registro,
                'elementos' => $elementos,
                // La plantilla actual no requiere imagen de firma
                'firma' => []
            ])->setPaper('A4', 'portrait');

            $numeroDoc = $registro->numero_documento ?? ($registro->nombres ?? 'registro');
            $numeroDoc = preg_replace('/[^A-Za-z0-9\-_]/', '_', substr($numeroDoc, 0, 40));
            $filename = strtoupper($tipo) . '_' . $numeroDoc . '_' . now()->format('Ymd_His') . '.pdf';

            // Guardar temporalmente y forzar descarga como adjunto
            $tmpDir = storage_path('app/tmp_downloads');
            if (!file_exists($tmpDir)) { mkdir($tmpDir, 0755, true); }
            $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($fullPath, $pdf->output());

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error descargando PDF individual', ['tipo' => $tipo, 'id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'No se pudo generar el comprobante: ' . $e->getMessage());
        }
    }

    /**
     * Descargar comprobantes previamente guardados en storage/app/{dir}/{file}
     */
    public function downloadComprobante($dir, $file)
    {
        $path = storage_path('app/' . $dir . '/' . $file);
        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }
        return response()->download($path);
    }

    /**
     * Placeholder: descarga masiva. Pendiente de implementar según requisitos.
     */
    public function descargarPDFMasivo(Request $request)
    {
        $data = $request->validate([
            'tipo_registro' => ['required', 'in:entrega,recepcion,todos'],
            'operacion_id' => ['nullable', 'integer'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date'],
        ]);

        $tipo = $data['tipo_registro'];
        $operacionId = $data['operacion_id'] ?? null;
        $inicio = $data['fecha_inicio'];
        $fin = $data['fecha_fin'];

        try {
            $registros = collect();

            if ($tipo === 'entrega' || $tipo === 'todos') {
                $qEnt = DB::table('entregas')
                    ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
                    ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                    ->select([
                        'entregas.id',
                        'entregas.created_at',
                        DB::raw("'entrega' as registro_tipo"),
                        'entregas.tipo_entrega as tipo',
                        'entregas.comprobante_path as comprobante_path',
                        DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'entregas.recibido',
                        'cargos.nombre as cargo'
                    ])
                    ->whereNull('entregas.deleted_at')
                    ->whereBetween('entregas.created_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
                if ($operacionId) { $qEnt->where('entregas.sub_area_id', $operacionId); }
                $registros = $registros->merge($qEnt->get());
            }

            if ($tipo === 'recepcion' || $tipo === 'todos') {
                $qRec = DB::table('recepciones')
                    ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                    ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                    ->select([
                        'recepciones.id',
                        'recepciones.created_at',
                        DB::raw("'recepcion' as registro_tipo"),
                        'recepciones.tipo_recepcion as tipo',
                        'recepciones.comprobante_path as comprobante_path',
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido',
                        'cargos.nombre as cargo'
                    ])
                    ->whereNull('recepciones.deleted_at')
                    ->whereBetween('recepciones.created_at', [$inicio . ' 00:00:00', $fin . ' 23:59:59']);
                if ($operacionId) { $qRec->where('recepciones.operacion_id', $operacionId); }
                $registros = $registros->merge($qRec->get());
            }

            if ($registros->isEmpty()) {
                return back()->with('error', 'No hay registros en el rango seleccionado.');
            }

            // Limitar a un máximo razonable para evitar ZIP gigante
            $maxArchivos = 500;
            if ($registros->count() > $maxArchivos) {
                $registros = $registros->sortByDesc('created_at')->take($maxArchivos)->values();
            }

            // Preparar ZIP temporal
            $tmpDir = storage_path('app/tmp_downloads');
            if (!file_exists($tmpDir)) { mkdir($tmpDir, 0755, true); }
            $zipName = 'comprobantes_' . ($tipo === 'todos' ? 'mixto' : $tipo) . '_' . now()->format('Ymd_His') . '.zip';
            $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'No se pudo crear el archivo ZIP.');
            }

            foreach ($registros as $reg) {
                $tipoReg = $reg->registro_tipo;
                $numeroDoc = $reg->numero_documento ?? ($reg->nombres ?? 'registro');
                $numeroDoc = preg_replace('/[^A-Za-z0-9\-_]/', '_', substr($numeroDoc, 0, 40));
                $fecha = \Carbon\Carbon::parse($reg->created_at)->format('Ymd_His');
                $filename = strtoupper($tipoReg) . '_' . $numeroDoc . '_' . $fecha . '.pdf';

                // Si hay comprobante_path, intentar añadir archivo existente
                $added = false;
                if (!empty($reg->comprobante_path)) {
                    $relative = ltrim(preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $reg->comprobante_path), '/');
                    $candidates = [
                        storage_path('app/' . $relative),
                        storage_path('app/public/' . $relative),
                    ];
                    foreach ($candidates as $p) {
                        if (is_string($p) && file_exists($p)) {
                            $zip->addFile($p, $filename);
                            $added = true;
                            break;
                        }
                    }
                }

                if ($added) { continue; }

                // Si no hay cargo, intentar buscar por numero_documento en usuarios_entregas
                if (empty($reg->cargo) && !empty($reg->numero_documento)) {
                    $usuarioCargo = DB::table('usuarios_entregas')
                        ->leftJoin('cargos', 'usuarios_entregas.cargo_id', '=', 'cargos.id')
                        ->where('usuarios_entregas.numero_documento', $reg->numero_documento)
                        ->select('cargos.nombre as cargo')
                        ->first();
                    if ($usuarioCargo && !empty($usuarioCargo->cargo)) {
                        $reg->cargo = $usuarioCargo->cargo;
                    }
                }

                // Construir datos y renderizar PDF en memoria si no existe comprobante
                if ($tipoReg === 'entrega') {
                    $elementos = DB::table('elemento_x_entrega')
                        ->where('entrega_id', $reg->id)
                        ->select(['sku', 'cantidad'])
                        ->get();
                } else {
                    $elementos = DB::table('elemento_x_recepcion')
                        ->where('recepcion_id', $reg->id)
                        ->select(['sku', 'cantidad'])
                        ->get();
                }

                // Obtener nombres de productos desde la base de datos secundaria
                $skus = $elementos->pluck('sku')->filter()->toArray();
                if (!empty($skus)) {
                    $productosMap = Producto::whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('LOWER(sku)'), array_map('mb_strtolower', $skus))
                        ->pluck('name_produc', 'sku')
                        ->toArray();
                    $productosMapLower = [];
                    foreach ($productosMap as $k => $v) {
                        $productosMapLower[mb_strtolower($k)] = $v;
                    }
                    $elementos = $elementos->map(function ($el) use ($productosMap, $productosMapLower) {
                        $el->name_produc = $productosMap[$el->sku] 
                            ?? $productosMapLower[mb_strtolower($el->sku)] 
                            ?? null;
                        return $el;
                    });
                }

                $pdf = Pdf::loadView('pdf.comprobante', [
                    'tipo' => $tipoReg,
                    'registro' => (object) $reg,
                    'elementos' => $elementos,
                    'firma' => []
                ])->setPaper('A4', 'portrait');

                $zip->addFromString($filename, $pdf->output());
            }

            $zip->close();

            return response()->download($zipPath, $zipName, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $zipName . '"'
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error en descarga masiva', [
                'tipo' => $tipo ?? null,
                'operacion' => $operacionId ?? null,
                'inicio' => $inicio ?? null,
                'fin' => $fin ?? null,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'No se pudo generar la descarga masiva: ' . $e->getMessage());
        }
    }
}
