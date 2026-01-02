<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecepcionController extends Controller
{
    public function create()
    {
        $operations = SubArea::orderBy('operationName')->get();
        $allProducts = Producto::select('sku','name_produc')->orderBy('name_produc')->get();
        return view('recepcion.recepcion', compact('operations','allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo_doc' => ['required','string'],
            'num_doc' => ['required','string'],
            'nombres' => ['required','string'],
            'apellidos' => ['nullable','string'],
            'usuarios_id' => ['nullable','integer'],
            'operation_id' => ['required','integer','exists:sub_areas,id'],
            'tipo' => ['required','string','in:cambio,prestamo'],
            'entrega_id' => ['nullable','integer','exists:entregas,id'],
            'items' => ['required','string'],
            'firma' => ['nullable','string'],
        ]);

        // Usuario en sesión desde API
        $authUser = session('auth.user');
        
        Log::info('Auth user en sesión:', ['auth_user' => $authUser]);

        // Nombre del usuario - EXTRAER SOLO EL CAMPO name
        $nombreUsuario = 'usuario';
        if (is_array($authUser) && isset($authUser['name'])) {
            $nombreUsuario = $authUser['name'];
        } elseif (is_object($authUser) && isset($authUser->name)) {
            $nombreUsuario = $authUser->name;
        }

        // Primer rol del usuario (roles[0].roles) - EXTRAER SOLO EL CAMPO roles
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
            'primerRol' => $primerRol,
            'tipo_recepcion' => $data['tipo']
        ]);

        DB::beginTransaction();
        try {
            // Preparar datos para la recepción
            $recepcionData = [
                'rol_recepcion' => $primerRol,
                'recepcion_user' => $nombreUsuario,
                'tipo_recepcion' => $data['tipo'],
                'tipo_documento' => $data['tipo_doc'],
                'numero_documento' => $data['num_doc'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'] ?? null,
                'operacion_id' => (int) $data['operation_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Si se proporciona usuarios_id (usuario encontrado en BD), agregarlo
            if (!empty($data['usuarios_id'])) {
                $recepcionData['usuarios_id'] = (int) $data['usuarios_id'];
                Log::info('Usuario encontrado en BD', ['usuario_id' => $data['usuarios_id']]);
            } else {
                $recepcionData['usuarios_id'] = null;
                Log::info('Usuario no encontrado, guardando datos manuales', [
                    'numero_documento' => $data['num_doc']
                ]);
            }

            // Si es tipo "prestamo" y tiene entrega_id, agregarlo
            if ($data['tipo'] === 'prestamo' && !empty($data['entrega_id'])) {
                $recepcionData['entregas_id'] = (int) $data['entrega_id'];
            }

            $recepcionId = DB::table('recepciones')->insertGetId($recepcionData);

            $items = json_decode($data['items'] ?? '[]', true) ?: [];
            
            if (empty($items)) {
                throw new \Exception('Debe agregar al menos un elemento a la recepción');
            }
            
            foreach ($items as $it) {
                if (empty($it['sku'])) continue;
                DB::table('elemento_x_recepcion')->insert([
                    'recepcion_id' => $recepcionId,
                    'sku' => (string) $it['sku'],
                    'cantidad' => (string) ($it['cantidad'] ?? 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Si es tipo "prestamo" y tiene entrega_id, marcar la entrega como recibida
            if ($data['tipo'] === 'prestamo' && !empty($data['entrega_id'])) {
                DB::table('entregas')
                    ->where('id', $data['entrega_id'])
                    ->update([
                        'recibido' => true,
                        'updated_at' => now()
                    ]);
                
                Log::info('Entrega marcada como recibida', [
                    'entrega_id' => $data['entrega_id'],
                    'recepcion_id' => $recepcionId
                ]);
            }

            DB::commit();
            
            Log::info('Recepción creada exitosamente', [
                'recepcion_id' => $recepcionId,
                'tipo' => $data['tipo'],
                'usuario_id' => !empty($data['usuarios_id']) ? $data['usuarios_id'] : null,
                'datos_manuales' => empty($data['usuarios_id']),
                'elementos_count' => count($items)
            ]);
            
            return redirect()->back()->with('status', 'Recepción registrada correctamente');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error guardando recepción', [
                'error' => $e->getMessage(),
                'request' => $request->except(['firma'])
            ]);
            return redirect()->back()->with('error', 'Ocurrió un error al registrar la recepción: ' . $e->getMessage());
        }
    }

    /**
     * API: Buscar entregas tipo préstamo por número de documento
     */
    public function buscarEntregas(Request $request)
    {
        $numero = $request->query('numero');
        try {
            $query = DB::table('entregas')
                ->join('sub_areas', 'entregas.operacion_id', '=', 'sub_areas.id')
                ->select([
                    'entregas.id',
                    'entregas.created_at',
                    'entregas.nombres',
                    'entregas.apellidos',
                    'entregas.numero_documento',
                    'entregas.tipo_documento',
                    'sub_areas.operationName as operacion'
                ])
                ->where('entregas.tipo_entrega', 'prestamo') // Solo entregas tipo préstamo
                ->where('entregas.recibido', false) // Solo entregas no recibidas
                ->whereNull('entregas.deleted_at')
                ->orderBy('entregas.created_at', 'desc');

            if ($numero) {
                $query->where('entregas.numero_documento', 'like', "%{$numero}%");
            }

            $entregas = $query->limit(50)->get();

            // Cargar elementos de cada entrega
            $data = $entregas->map(function ($e) {
                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $e->id)
                    ->select(['sku', 'cantidad'])
                    ->get();

                return [
                    'id' => $e->id,
                    'fecha' => $e->created_at,
                    'nombres' => $e->nombres,
                    'apellidos' => $e->apellidos ?? '',
                    'numero_documento' => $e->numero_documento,
                    'tipo_documento' => $e->tipo_documento,
                    'operacion' => $e->operacion,
                    'elementos' => $elementos->map(fn($el) => [
                        'sku' => $el->sku,
                        'cantidad' => $el->cantidad
                    ])->toArray()
                ];
            });

            return response()->json($data, 200);
        } catch (\Throwable $e) {
            Log::error('Error buscando entregas', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }
}
