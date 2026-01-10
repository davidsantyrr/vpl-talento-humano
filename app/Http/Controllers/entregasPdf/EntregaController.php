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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EntregaController extends Controller
{
    /** Mostrar el formulario de entregas */
    public function create()
    {
        $operations = SubArea::orderBy('operationName')->get();
        // Attempt to select expected columns; if the external table uses different column names,
        // fall back to reading rows and map candidate fields.
        $allProducts = collect();
        try {
            $conn = (new Producto())->getConnectionName() ?: config('database.default');
            $hasSku = Schema::connection($conn)->hasColumn('productos', 'sku');
            $hasName = Schema::connection($conn)->hasColumn('productos', 'name_produc');
            if ($hasSku && $hasName) {
                $allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();
            } else {
                // fallback: pull some rows and map likely fields
                $rows = Producto::limit(500)->get();
                $allProducts = $rows->map(function($r){
                    $sku = $r->sku ?? $r->codigo ?? $r->id ?? null;
                    $name = $r->name_produc ?? $r->nombre ?? $r->name ?? '';
                    return (object)['sku' => $sku, 'name_produc' => $name];
                })->filter(fn($x) => $x->sku !== null)->values();
            }
        } catch (\Exception $e) {
            // if anything fails, return empty collection to avoid breaking the view
            $allProducts = collect();
        }

        return view('formularioEntregas.formularioEntregas', compact('operations','allProducts'));
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
            $query->where('sub_area_id', $request->input('operacion'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? $perPage : 10;
        $entregas = $query->paginate($perPage)->withQueryString();

        return view('formularioEntregas.HistorialEntregas', compact('entregas','operations'));
    }

    /** Mostrar historial unificado de entregas y recepciones */
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
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'numberDocumento' => 'required|string',
            'elementos' => 'required|string',
            'firma' => 'nullable|string',
            'tipo' => 'required|string',
            'operacion_id' => 'required|integer|exists:sub_areas,id',
            'tipo_documento' => 'required|string',
            'recepcion_id' => 'nullable|integer|exists:recepciones,id',
            'comprobante_path' => 'nullable|string',
        ]);

        // Usuario en sesión desde API
        $authUser = session('auth.user');
        
        Log::info('Auth user en sesión:', ['auth_user' => $authUser]);
        
        // Nombre del usuario que hace la entrega
        $nombreUsuario = 'usuario';
        if (is_array($authUser) && isset($authUser['name'])) {
            $nombreUsuario = $authUser['name'];
        } elseif (is_object($authUser) && isset($authUser->name)) {
            $nombreUsuario = $authUser->name;
        }

        // Email del usuario que hace la entrega
        $emailUsuario = 'sin-email@example.com';
        if (is_array($authUser) && isset($authUser['email'])) {
            $emailUsuario = $authUser['email'];
        } elseif (is_object($authUser) && isset($authUser->email)) {
            $emailUsuario = $authUser->email;
        }

        // Primer rol del usuario
        $primerRol = 'web';
        if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles']) && !empty($authUser['roles'])) {
            $first = $authUser['roles'][0] ?? null;
            if (is_array($first) && isset($first['roles'])) { 
                $primerRol = $first['roles']; 
            } elseif (is_object($first) && isset($first->roles)) { 
                $primerRol = $first->roles; 
            }
        }
        
        Log::info('Valores a guardar:', [
            'nombreUsuario' => $nombreUsuario,
            'emailUsuario' => $emailUsuario,
            'primerRol' => $primerRol
        ]);

        DB::beginTransaction();
        try {
            // ensure uploads dir
            if (!Storage::exists('public/entregas')) {
                Storage::makeDirectory('public/entregas');
            }

            // Intentar encontrar al usuario en la base de datos
            $usuario = Usuarios::where('numero_documento', $data['numberDocumento'])->first();
            
            // Preparar datos para la entrega
            // Las entregas tipo "periodica" y "primera vez" se marcan como recibidas automáticamente
            $recibidoAutomatico = in_array($data['tipo'], ['periodica', 'primera vez']);
            
            $entregaData = [
                'rol_entrega' => $primerRol,
                'entrega_user' => $nombreUsuario,
                'entrega_email' => $emailUsuario,
                'tipo_entrega' => $data['tipo'] ?? null,
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => $data['numberDocumento'],
                'nombres' => $data['nombre'],
                'apellidos' => $data['apellidos'] ?? null,
                'sub_area_id' => $data['operacion_id'] ?? null,
                'recepciones_id' => !empty($data['recepcion_id']) ? $data['recepcion_id'] : null,
                'recibido' => $recibidoAutomatico,
            ];
            
            // Si el usuario existe en BD, agregar su ID
            if ($usuario) {
                $entregaData['usuarios_id'] = $usuario->id;
                Log::info('Usuario encontrado en BD', ['usuario_id' => $usuario->id]);
            } else {
                // Usuario no existe, se guardarán solo los datos manuales
                $entregaData['usuarios_id'] = null;
                Log::info('Usuario no encontrado, guardando datos manuales', [
                    'numero_documento' => $data['numberDocumento']
                ]);
            }

            Log::info('Datos completos a insertar en entregas:', $entregaData);

            // Asegurar que tomamos comprobante_path si viene en FormData o JSON
            $comprobantePath = $request->input('comprobante_path');
            if (is_null($comprobantePath)) {
                // también soportar get() por si acaso
                $comprobantePath = $request->get('comprobante_path');
            }
            // Normalizar: si viene con prefijo storage/app/ o con / al inicio, quitar prefijos innecesarios
            if (!empty($comprobantePath)) {
                // Guardar solo la ruta relativa esperada (ej: comprobantes_entregas/archivo.pdf)
                $comprobantePath = preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $comprobantePath);
                $comprobantePath = ltrim($comprobantePath, '/');
                $entregaData['comprobante_path'] = $comprobantePath;
            }
            Log::info('Comprobante path recibido para guardar en DB', ['comprobante_path' => $comprobantePath]);

            // Crear entrega con datos completos
            $entrega = Entrega::create($entregaData);

            // Asegurar persistencia de comprobante_path por si mass-assignment no lo grabó.
            try {
                if (!empty($entregaData['comprobante_path'])) {
                    // Verificar que la columna existe antes de intentar update
                    if (\Illuminate\Support\Facades\Schema::hasColumn('entregas', 'comprobante_path')) {
                        // Usar update directo para evitar problemas de fillable
                        \Illuminate\Support\Facades\DB::table('entregas')
                            ->where('id', $entrega->id)
                            ->update(['comprobante_path' => $entregaData['comprobante_path']]);
                        Log::info('Comprobante_path actualizado en DB para entrega', ['entrega_id' => $entrega->id, 'comprobante_path' => $entregaData['comprobante_path']]);
                    } else {
                        Log::warning('La columna comprobante_path no existe en la tabla entregas. Ejecuta la migración.', ['entrega_id' => $entrega->id]);
                    }
                }
            } catch (\Throwable $uEx) {
                Log::error('Error actualizando comprobante_path tras crear entrega', ['error' => $uEx->getMessage(), 'entrega_id' => $entrega->id]);
            }

            // save elementos if present
            $items = json_decode($data['elementos'] ?? '[]', true) ?: [];
            
            if (empty($items)) {
                throw new Exception('Debe agregar al menos un elemento a la entrega');
            }
            
            foreach ($items as $it) {
                if (empty($it['sku'])) continue;
                ElementoXEntrega::create([
                    'entrega_id' => $entrega->id,
                    'sku' => $it['sku'],
                    'cantidad' => $it['cantidad'] ?? 1,
                ]);
            }

            // Si es tipo "cambio" y tiene recepcion_id, marcar la recepción como entregada
            // y vincular la entrega con la recepción
            if ($data['tipo'] === 'cambio' && !empty($data['recepcion_id'])) {
                // Marcar recepción como entregada (completada)
                DB::table('recepciones')
                    ->where('id', $data['recepcion_id'])
                    ->update([
                        'entregado' => true,
                        'updated_at' => now()
                    ]);
                
                // Marcar esta entrega como recibida (completada)
                DB::table('entregas')
                    ->where('id', $entrega->id)
                    ->update([
                        'recibido' => true,
                        'updated_at' => now()
                    ]);
                
                Log::info('Recepción marcada como entregada y entrega marcada como recibida', [
                    'recepcion_id' => $data['recepcion_id'],
                    'entrega_id' => $entrega->id
                ]);
            }

            // Actualizar inventario según tipo de entrega
            $this->actualizarInventarioEntrega($data['tipo'], $items, $entrega->id);

            DB::commit();

            // Refrescar la entidad y loguear el comprobante_path final
            try {
                $entrega = \App\Models\Entrega::find($entrega->id);
                Log::info('Entrega finalizada - comprobante_path en DB', ['entrega_id' => $entrega->id, 'comprobante_path_db' => $entrega->comprobante_path ?? null]);
            } catch (\Throwable $refreshEx) {
                Log::warning('No se pudo refrescar entrega tras commit', ['error' => $refreshEx->getMessage()]);
            }

            Log::info('Entrega creada exitosamente', [
                'entrega_id' => $entrega->id,
                'tipo_entrega' => $data['tipo'],
                'recibido_automatico' => $recibidoAutomatico,
                'usuario_id' => $usuario ? $usuario->id : null,
                'datos_manuales' => !$usuario,
                'elementos_count' => count($items)
            ]);

            // Disparar Job para enviar correo
            if (!empty($emailUsuario) && $emailUsuario !== 'sin-email@example.com') {
                try {
                    // Enviar correo de forma síncrona (inmediata)
                    EnviarCorreoEntrega::dispatchSync(
                        $entrega,
                        $items,
                        $emailUsuario,
                        $data['comprobante_path'] ?? null
                    );

                    Log::info('Correo de entrega enviado exitosamente', [
                        'entrega_id' => $entrega->id,
                        'email' => $emailUsuario
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al enviar correo de entrega', [
                        'entrega_id' => $entrega->id,
                        'email' => $emailUsuario,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Entrega registrada correctamente',
                    'comprobante_path' => $entrega->comprobante_path ?? null,
                    'entrega_id' => $entrega->id
                ], 200);
            }

            // Para peticiones normales, redirigimos de vuelta con mensaje de éxito
            return redirect()->back()
                ->with('status', 'Entrega registrada correctamente')
                ->with('comprobante_path', $entrega->comprobante_path ?? null);
                
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creando entrega y registros', [
                'error' => $e->getMessage(),
                'request' => $request->except(['firma'])
            ]);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error al procesar la entrega: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Ocurrió un error al procesar la entrega: ' . $e->getMessage())
                ->withInput();
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
        // Registrar acceso para debugging
        Log::info('Hit /historial/pdf', ['query' => $request->all(), 'url' => $request->fullUrl()]);

        $tipo = $request->query('tipo');
        $id = $request->query('id');

        if (!$tipo || !$id) {
            abort(404);
        }

        if ($tipo === 'entrega') {
            $comprobante = DB::table('entregas')->where('id', $id)->value('comprobante_path');
        } elseif ($tipo === 'recepcion') {
            $comprobante = DB::table('recepciones')->where('id', $id)->value('comprobante_path');
        } else {
            abort(404);
        }

        Log::info('Comprobante consultado desde DB', ['tipo' => $tipo, 'id' => $id, 'comprobante' => $comprobante]);

        if (!$comprobante) {
            Log::warning('Comprobante no encontrado en DB', ['tipo' => $tipo, 'id' => $id]);
            abort(404);
        }

        // Normalizar ruta relativa esperada (ej: comprobantes_entregas/archivo.pdf)
        $relativePath = ltrim(preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $comprobante), '/');
        $fullPath = storage_path('app/' . $relativePath);

        // Datos de diagnóstico
        $disk = Storage::disk('local');
        $diskExists = $disk->exists($relativePath);
        $fileExists = file_exists($fullPath);
        $isReadable = is_readable($fullPath);

        Log::info('Diagnóstico comprobante', [
            'relativePath' => $relativePath,
            'fullPath' => $fullPath,
            'diskExists' => $diskExists,
            'fileExists' => $fileExists,
            'isReadable' => $isReadable,
        ]);

        try {
            if (!$diskExists || !$fileExists) {
                Log::warning('Comprobante no encontrado en storage (diagnóstico) ', ['relativePath' => $relativePath, 'fullPath' => $fullPath]);
                abort(404);
            }

            // Forzar nombre de descarga claro
            $downloadName = basename($fullPath);

            // Forzar descarga usando BinaryFileResponse con Content-Disposition attachment
            $response = new BinaryFileResponse($fullPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
            $response->headers->set('Content-Type', 'application/octet-stream');
            return $response;
        } catch (\Throwable $e) {
            Log::error('Error sirviendo comprobante', ['error' => $e->getMessage(), 'relativePath' => $relativePath ?? null, 'fullPath' => $fullPath ?? null]);
            // Intentar servir con streamDownload como fallback forzando descarga
            try {
                if (isset($fullPath) && file_exists($fullPath)) {
                    $downloadName = basename($fullPath);
                    return response()->streamDownload(function() use ($fullPath) {
                        readfile($fullPath);
                    }, $downloadName, ['Content-Type' => 'application/octet-stream']);
                }
            } catch (\Throwable $e2) {
                Log::error('Fallback streamDownload failed', ['error' => $e2->getMessage()]);
            }

            abort(500);
        }
    }

    /**
     * API: Descargar CSV masivo con los registros (entregas/recepciones)
     */
    public function descargarPDFMasivo(Request $request)
    {
        try {
            $tipoRegistro = $request->query('tipo_registro');
            $operacionId = $request->query('operacion_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');

            // Validaciones simples
            if (!in_array($tipoRegistro, ['entrega', 'recepcion', 'todos'])) {
                return response()->json(['error' => 'Tipo de registro inválido'], 400);
            }
            if (empty($fechaInicio) || empty($fechaFin)) {
                return response()->json(['error' => 'Fecha inicio y fin son requeridas'], 400);
            }

            $registros = collect();

            if (in_array($tipoRegistro, ['entrega', 'todos'])) {
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
                        'entregas.comprobante_path'
                    ])
                    ->whereNull('entregas.deleted_at')
                    ->whereBetween('entregas.created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

                if ($operacionId) {
                    $queryEntregas->where('entregas.sub_area_id', $operacionId);
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
                        DB::raw("'recepcion' as registro_tipo"),
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido',
                        'recepciones.comprobante_path'
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

            // Construir filas con elementos ya concatenados para la vista
            $rows = collect();
            foreach ($registros as $registro) {
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

                $elementosTexto = $elementos->map(fn($e) => "{$e->sku}({$e->cantidad})")->implode('; ');

                $rows->push([
                    'id' => $registro->id,
                    'registro_tipo' => $registro->registro_tipo,
                    'tipo' => $registro->tipo,
                    'fecha' => isset($registro->created_at) ? (string)$registro->created_at : '',
                    'numero_documento' => $registro->numero_documento ?? '',
                    'tipo_documento' => $registro->tipo_documento ?? '',
                    'nombres' => $registro->nombres ?? '',
                    'apellidos' => $registro->apellidos ?? '',
                    'operacion' => $registro->operacion ?? '',
                    'recibido' => (isset($registro->recibido) ? ($registro->recibido ? '1' : '0') : ''),
                    'comprobante_path' => $registro->comprobante_path ?? '',
                    'elementos' => $elementosTexto,
                ]);
            }

            $fileName = "registros_" . now()->format('Y-m-d_His') . ".xlsx";

            // Si la librería Maatwebsite/Excel está disponible, usarla; si no, caer a CSV.
            if (class_exists('Maatwebsite\\Excel\\Facades\\Excel') && class_exists(\App\Exports\TempRegistrosExport::class)) {
                $export = new \App\Exports\TempRegistrosExport($rows->toArray());
                return call_user_func(['Maatwebsite\\Excel\\Facades\\Excel', 'download'], $export, $fileName);
            }
 
             // Fallback: generar CSV compatible con Excel
            $fileNameCsv = pathinfo($fileName, PATHINFO_FILENAME) . '.csv';
            $handle = fopen('php://temp', 'r+');
            // Encabezados
            fputcsv($handle, ['id','registro_tipo','tipo','fecha','numero_documento','tipo_documento','nombres','apellidos','operacion','recibido','comprobante_path','elementos']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['id'] ?? '',
                    $r['registro_tipo'] ?? '',
                    $r['tipo'] ?? '',
                    $r['fecha'] ?? '',
                    $r['numero_documento'] ?? '',
                    $r['tipo_documento'] ?? '',
                    $r['nombres'] ?? '',
                    $r['apellidos'] ?? '',
                    $r['operacion'] ?? '',
                    $r['recibido'] ?? '',
                    $r['comprobante_path'] ?? '',
                    $r['elementos'] ?? ''
                ]);
            }
            rewind($handle);
            $stream = function() use ($handle) {
                while (!feof($handle)) {
                    echo fgets($handle);
                }
                fclose($handle);
            };
            return response()->streamDownload($stream, $fileNameCsv, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileNameCsv . '"',
            ]);
 
         } catch (\Exception $e) {
             Log::error('Error generando Excel masivo', [
                 'error' => $e->getMessage(),
                 'params' => $request->query()
             ]);
             return response()->json(['error' => 'Error al generar Excel: ' . $e->getMessage()], 500);
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

    // Nuevo endpoint para registrar hits de comprobantes desde el cliente
    public function logComprobanteHit(Request $request)
    {
        $payload = $request->getContent();
        $data = null;
        try {
            $data = json_decode($payload, true);
        } catch (\Throwable $e) {
            $data = ['raw' => $payload];
        }
        Log::info('Comprobante hit desde cliente', ['data' => $data, 'ip' => $request->ip()]);
        return response()->json(['ok' => true]);
    }

    // Nuevo método: servir comprobante desde storage (forzar descarga)
    public function downloadComprobante($dir, $file)
    {
        $relativePath = $dir . '/' . $file;
        $fullPath = storage_path('app/' . $relativePath);

        Log::info('Solicitud descarga comprobante (controller)', ['relativePath' => $relativePath, 'fullPath' => $fullPath, 'exists' => file_exists($fullPath)]);

        try {
            $disk = Storage::disk('local');
            if (!$disk->exists($relativePath) || !file_exists($fullPath)) {
                Log::warning('Comprobante no encontrado en storage (controller)', ['relativePath' => $relativePath]);
                abort(404);
            }

            $downloadName = basename($fullPath);
            $response = new BinaryFileResponse($fullPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
            $response->headers->set('Content-Type', 'application/octet-stream');
            return $response;
        } catch (\Throwable $e) {
            Log::error('Error descargando comprobante (controller)', ['error' => $e->getMessage(), 'relativePath' => $relativePath]);
            abort(500);
        }
    }
}