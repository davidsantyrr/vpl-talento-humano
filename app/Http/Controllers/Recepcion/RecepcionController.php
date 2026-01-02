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
            'usuarios_id' => ['required','integer'],
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
            $recepcionId = DB::table('recepciones')->insertGetId([
                'rol_recepcion' => $primerRol,
                'recepcion_user' => $nombreUsuario,
                'usuarios_id' => (int) $data['usuarios_id'],
                'operacion_id' => (int) $data['operation_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items = json_decode($data['items'] ?? '[]', true) ?: [];
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
            return redirect()->back()->with('status', 'Se ha registrado la recepción');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error guardando recepción', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Ocurrió un error al registrar la recepción.');
        }
    }
}
