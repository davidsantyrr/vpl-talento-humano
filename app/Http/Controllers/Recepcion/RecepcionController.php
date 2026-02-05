<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\EnviarCorreoRecepcion;

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
            'comprobante_path' => ['nullable','string'],
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

        // Email del usuario - EXTRAER SOLO EL CAMPO email
        $emailUsuario = 'sin-email@example.com';
        if (is_array($authUser) && isset($authUser['email'])) {
            $emailUsuario = $authUser['email'];
        } elseif (is_object($authUser) && isset($authUser->email)) {
            $emailUsuario = $authUser->email;
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
            'emailUsuario' => $emailUsuario,
            'primerRol' => $primerRol,
            'tipo_recepcion' => $data['tipo']
        ]);

        DB::beginTransaction();
        try {
            // Preparar datos para la recepción
            $recepcionData = [
                'rol_recepcion' => $primerRol,
                'recepcion_user' => $nombreUsuario,
                'recepcion_email' => $emailUsuario,
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

            // Si se proporciona entrega_id (vincular recepción con entrega), agregarlo siempre
            if (!empty($data['entrega_id'])) {
                $recepcionData['entregas_id'] = (int) $data['entrega_id'];
            }

            // Normalizar comprobante_path si viene (guardar ruta relativa en DB)
            $comprobantePath = $request->input('comprobante_path') ?? ($data['comprobante_path'] ?? null);
            if (!empty($comprobantePath)) {
                $comprobantePath = preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $comprobantePath);
                $comprobantePath = ltrim($comprobantePath, '/');
                $recepcionData['comprobante_path'] = $comprobantePath;
                Log::info('Comprobante path para recepcion será guardado', ['comprobante_path' => $comprobantePath]);
            }

            $recepcionId = DB::table('recepciones')->insertGetId($recepcionData);

            $items = json_decode($data['items'] ?? '[]', true) ?: [];
            
            if (empty($items)) {
                throw new \Exception('Debe agregar al menos un elemento a la recepción');
            }
            
            $insertedItems = [];
            foreach ($items as $it) {
                if (empty($it['sku'])) continue;
                $cantidad = isset($it['cantidad']) ? (int)$it['cantidad'] : 1;
                DB::table('elemento_x_recepcion')->insert([
                    'recepcion_id' => $recepcionId,
                    'sku' => (string) $it['sku'],
                    'cantidad' => $cantidad,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $insertedItems[] = ['sku' => (string)$it['sku'], 'cantidad' => $cantidad];
            }

            Log::info('Elementos insertados en elemento_x_recepcion', ['recepcion_id' => $recepcionId, 'items' => $insertedItems]);

            // Si se proporcionó entrega_id, marcar la entrega como recibida
            // y vincular la recepción con la entrega (aplica para préstamos y otros tipos)
            if (!empty($data['entrega_id'])) {
                try {
                    DB::table('entregas')
                        ->where('id', $data['entrega_id'])
                        ->update([
                            'recibido' => true,
                            'recepciones_id' => $recepcionId,
                            'updated_at' => now()
                        ]);

                    Log::info('Entrega marcada como recibida y vinculada con recepción', [
                        'entrega_id' => $data['entrega_id'],
                        'recepcion_id' => $recepcionId
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Error vinculando entrega con recepción', ['entrega_id' => $data['entrega_id'], 'recepcion_id' => $recepcionId, 'err' => $e->getMessage()]);
                }
            }

            // Marcar esta recepción como entregada (completada) siempre que haya sido creada
            try {
                DB::table('recepciones')
                    ->where('id', $recepcionId)
                    ->update([
                        'entregado' => true,
                        'updated_at' => now()
                    ]);
                Log::info('Recepción marcada como entregada', ['recepcion_id' => $recepcionId]);
            } catch (\Throwable $e) {
                Log::error('Error marcando recepción como entregada', ['recepcion_id' => $recepcionId, 'err' => $e->getMessage()]);
            }

            // Actualizar inventario según tipo de recepción
            $this->actualizarInventarioRecepcion($data['tipo'], $items, $recepcionId);

            DB::commit();
            
            Log::info('Recepción creada exitosamente', [
                'recepcion_id' => $recepcionId,
                'tipo' => $data['tipo'],
                'usuario_id' => !empty($data['usuarios_id']) ? $data['usuarios_id'] : null,
                'datos_manuales' => empty($data['usuarios_id']),
                'elementos_count' => count($items)
            ]);
            
            // Obtener recepción creada para enviar correo
            $recepcion = DB::table('recepciones')->where('id', $recepcionId)->first();
            
            // Disparar Job para enviar correo
            if (!empty($emailUsuario) && $emailUsuario !== 'sin-email@example.com') {
                try {
                    // Enviar correo de forma síncrona (inmediata)
                    EnviarCorreoRecepcion::dispatchSync(
                        $recepcion,
                        $items,
                        $emailUsuario,
                        $data['comprobante_path'] ?? $request->input('comprobante_path', null)
                    );

                    Log::info('Correo de recepción enviado exitosamente', [
                        'recepcion_id' => $recepcionId,
                        'email' => $emailUsuario
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al enviar correo de recepción', [
                        'recepcion_id' => $recepcionId,
                        'email' => $emailUsuario,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Recepción registrada correctamente',
                    'recepcion_id' => $recepcionId
                ], 200);
            }
            
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
            // Determinar rol del usuario en sesión (primer rol)
            $authUser = session('auth.user');
            $primerRol = null;
            if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles']) && !empty($authUser['roles'])) {
                $first = $authUser['roles'][0] ?? null;
                if (is_array($first) && isset($first['roles'])) { $primerRol = $first['roles']; }
                elseif (is_object($first) && isset($first->roles)) { $primerRol = $first->roles; }
                elseif (is_string($first)) { $primerRol = $first; }
            } elseif (is_object($authUser) && isset($authUser->roles) && is_array($authUser->roles) && !empty($authUser->roles)) {
                $first = $authUser->roles[0] ?? null;
                if (is_object($first) && isset($first->roles)) { $primerRol = $first->roles; }
                elseif (is_string($first)) { $primerRol = $first; }
            }

            $query = DB::table('entregas')
                ->join('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
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

            // Filtrar por rol de sesión si está disponible
            if (!empty($primerRol)) {
                $query->where('entregas.rol_entrega', $primerRol);
            }

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

    /**
     * Actualizar inventario según tipo de recepción
     */
    private function actualizarInventarioRecepcion($tipoRecepcion, $items, $recepcionId)
    {
        try {
            foreach ($items as $item) {
                if (empty($item['sku'])) continue;

                $sku = $item['sku'];
                $cantidad = (int) ($item['cantidad'] ?? 1);

                // Obtener ubicación por defecto
                $ubicacionId = $this->getOrCreateDefaultUbicacion();

                switch ($tipoRecepcion) {
                    case 'prestamo':
                        // Restar de prestado y sumar a disponible (devolución de préstamo)
                        $this->transferirInventario($sku, $cantidad, 'prestado', 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: devolución de préstamo', [
                            'recepcion_id' => $recepcionId,
                            'sku' => $sku,
                            'cantidad' => $cantidad,
                            'de' => 'prestado',
                            'a' => 'disponible'
                        ]);
                        break;

                    case 'cambio':
                        // Sumar a disponible (artículo devuelto para cambio)
                        $this->sumarInventario($sku, $cantidad, 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: recepción para cambio', [
                            'recepcion_id' => $recepcionId,
                            'sku' => $sku,
                            'cantidad' => $cantidad
                        ]);
                        break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error actualizando inventario en recepción', [
                'error' => $e->getMessage(),
                'recepcion_id' => $recepcionId,
                'tipo' => $tipoRecepcion
            ]);
            throw $e;
        }
    }

    /**
     * Transferir inventario entre estados
     */
    private function transferirInventario($sku, $cantidad, $estatusOrigen, $estatusDestino, $ubicacionId)
    {
        // Consumir filas de inventario origen por orden hasta cubrir la cantidad requerida
        $remaining = (int)$cantidad;
        $rows = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatusOrigen)
            ->orderBy('id')
            ->get();

        $totalAvailable = $rows->sum(fn($r) => (int)$r->stock);
        if ($totalAvailable <= 0) {
            Log::warning('No hay inventario origen para transferir', ['sku' => $sku, 'estatusOrigen' => $estatusOrigen]);
        }

        foreach ($rows as $row) {
            if ($remaining <= 0) break;
            $stockInv = (int)$row->stock;
            if ($stockInv <= 0) continue;
            $toTake = min($stockInv, $remaining);
            $newStock = $stockInv - $toTake;
            if ($newStock > 0) {
                DB::connection('mysql_third')->table('inventarios')->where('id', $row->id)->update(['stock' => $newStock]);
            } else {
                DB::connection('mysql_third')->table('inventarios')->where('id', $row->id)->delete();
            }
            Log::info('Inventario origen consumido', ['sku' => $sku, 'inventario_id' => $row->id, 'consumido' => $toTake, 'stock_antes' => $stockInv, 'stock_despues' => $newStock]);
            $remaining -= $toTake;
        }

        $transferred = (int)$cantidad - max(0, $remaining);
        if ($remaining > 0) {
            Log::warning('No se pudo cubrir la cantidad completa en origen durante transferencia', ['sku' => $sku, 'requested' => $cantidad, 'transferred' => $transferred, 'missing' => $remaining]);
        }

        // Agregar la cantidad transferida al inventario destino (si es > 0)
        if ($transferred > 0) {
            $inventarioDestino = DB::connection('mysql_third')
                ->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $estatusDestino)
                ->first();

            if ($inventarioDestino) {
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioDestino->id)
                    ->update(['stock' => (int)$inventarioDestino->stock + $transferred]);
            } else {
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->insert([
                        'sku' => $sku,
                        'stock' => $transferred,
                        'estatus' => $estatusDestino,
                        'ubicaciones_id' => $ubicacionId
                    ]);
            }
            Log::info('Inventario destino actualizado por transferencia', ['sku' => $sku, 'transferred' => $transferred, 'estatusDestino' => $estatusDestino]);
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