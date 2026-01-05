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
            $entregaData = [
                'rol_entrega' => $primerRol,
                'entrega_user' => $nombreUsuario,
                'tipo_entrega' => $data['tipo'] ?? null,
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => $data['numberDocumento'],
                'nombres' => $data['nombre'],
                'apellidos' => $data['apellidos'] ?? null,
                'operacion_id' => $data['operacion_id'] ?? null,
                'recepciones_id' => !empty($data['recepcion_id']) ? $data['recepcion_id'] : null,
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

            // Crear entrega con datos completos
            $entrega = Entrega::create($entregaData);

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
            if ($data['tipo'] === 'cambio' && !empty($data['recepcion_id'])) {
                DB::table('recepciones')
                    ->where('id', $data['recepcion_id'])
                    ->update([
                        'entregado' => true,
                        'updated_at' => now()
                    ]);
                
                Log::info('Recepción marcada como entregada', [
                    'recepcion_id' => $data['recepcion_id'],
                    'entrega_id' => $entrega->id
                ]);
            }

            // TODO: Generar PDF más adelante
            // Por ahora solo guardamos los datos
            
            DB::commit();

            Log::info('Entrega creada exitosamente', [
                'entrega_id' => $entrega->id,
                'usuario_id' => $usuario ? $usuario->id : null,
                'datos_manuales' => !$usuario,
                'elementos_count' => count($items)
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Entrega registrada correctamente'
                ], 200);
            }

            // Para peticiones normales, redirigimos de vuelta con mensaje de éxito
            return redirect()->back()
                ->with('status', 'Entrega registrada correctamente');
                
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
}