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
            'items' => ['required','string'],
            'firma' => ['nullable','string'],
        ]);

        // Usuario en sesión desde API (estructura: ['id','email','name','roles'=>[{'roles'=>...}], ...])
        $authUser = session('auth.user');
        
        // Log para debugging
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
        
        // Log para verificar valores antes de guardar
        Log::info('Valores a guardar:', [
            'nombreUsuario' => $nombreUsuario,
            'primerRol' => $primerRol
        ]);

        DB::beginTransaction();
        try {
            // Preparar datos para la recepción
            $recepcionData = [
                'rol_recepcion' => $primerRol,
                'recepcion_user' => $nombreUsuario,
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
                // Usuario no existe, se guardarán solo los datos manuales
                $recepcionData['usuarios_id'] = null;
                Log::info('Usuario no encontrado, guardando datos manuales', [
                    'numero_documento' => $data['num_doc']
                ]);
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

            DB::commit();
            
            Log::info('Recepción creada exitosamente', [
                'recepcion_id' => $recepcionId,
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
}
