<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use App\Models\SubArea;
use App\Models\Area;
use App\Models\Producto;
use App\Models\ElementoXUsuario;

class gestionUsuarioController extends Controller
{
    public function index()
    {
        $usuarios   = Usuarios::orderBy('id', 'desc')->get();
        $operations = SubArea::orderBy('operationName')->get();
        $areas      = Area::orderBy('nombre_area')->get();
        $productos  = Producto::orderBy('name_produc')->get(['sku','name_produc']);

        return view('gestiones.gestionUsuarios', compact(
        'usuarios',
        'operations',
        'areas',
        'productos'
    ));
}

    public function store(Request $request)
    {
        $request->validate([
        'nombres' => 'required|string|max:255',
        'apellidos'=> 'nullable|string|max:255',
        'tipo_documento'    => 'nullable|string|max:100',
        'numero_documento'  => 'required|string|max:100|unique:usuarios_entregas,numero_documento',
        'email'  => 'required|email|unique:usuarios_entregas,email',
        'fecha_ingreso'=> 'required|date',
        'operacion_id'=> 'required|exists:sub_areas,id',
        'area_id'=> 'required|exists:area,id',
]);

        Usuarios::create([
            'nombres' => $request->input('nombres'),
            'apellidos' => $request->input('apellidos'),
            'tipo_documento' => $request->input('tipo_documento'),
            'numero_documento' => $request->input('numero_documento'),
            'email' => $request->input('email'),
            'fecha_ingreso' => $request->input('fecha_ingreso'),
            'operacion_id' => $request->input('operacion_id'),
            'area_id' => $request->input('area_id'),
        ]);

        return redirect()->route('gestionUsuario.index')->with('success', 'Usuario creado exitosamente.');
    }

    /**
     * Asignar un producto (SKU) a un usuario.
     * Persiste en tabla elemento_x_usuario.
     */
    public function asignarProducto(Request $request, $id)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        $usuario = Usuarios::findOrFail($id);
        $producto = Producto::where('sku', $request->input('sku'))->firstOrFail();

        // Evitar duplicados: mismo usuario + SKU
        $existe = ElementoXUsuario::where('usuarios_entregas_id', $usuario->id)
            ->where('sku', $producto->sku)
            ->exists();

        if (!$existe) {
            $registro = ElementoXUsuario::create([
                'usuarios_entregas_id' => $usuario->id,
                'sku' => $producto->sku,
                'name_produc' => $producto->name_produc,
            ]);
        } else {
            $registro = ElementoXUsuario::where('usuarios_entregas_id', $usuario->id)
                ->where('sku', $producto->sku)
                ->first();
        }

        return response()->json([
            'ok' => true,
            'message' => $existe ? 'Ya estaba asignado' : 'Producto asignado',
            'data' => [
                'usuario_id' => $usuario->id,
                'sku' => $producto->sku,
                'name_produc' => $producto->name_produc,
                'id' => $registro->id,
            ],
        ]);
    }

    /**
     * Listar productos asignados a un usuario (para precargar en modal)
     */
    public function productosAsignados($id)
    {
        $usuario = Usuarios::findOrFail($id);
        $items = ElementoXUsuario::where('usuarios_entregas_id', $usuario->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'sku', 'name_produc']);

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    /**
     * Eliminar asignación de producto a usuario
     */
    public function eliminarProductoAsignado($asignacionId)
    {
        $registro = ElementoXUsuario::findOrFail($asignacionId);
        $registro->delete();
        return response()->json(['ok' => true]);
    }

    public function edit($id)
    {
        $editUsuario = Usuarios::findOrFail($id);
        $usuarios    = Usuarios::orderBy('id', 'desc')->get();
        $operations  = SubArea::orderBy('operationName')->get();
        $areas       = Area::orderBy('nombre_area')->get();

        return view('gestiones.gestionUsuarios', compact(
        'usuarios',
        'editUsuario',
        'operations',
        'areas'
    ));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
        'nombres'           => 'required|string|max:255',
        'apellidos'         => 'nullable|string|max:255',
        'tipo_documento'    => 'nullable|string|max:100',
        'numero_documento'  => 'required|string|max:100|unique:usuarios_entregas,numero_documento,' . $id,
        'email'             => 'required|email|unique:usuarios_entregas,email,' . $id,
        'fecha_ingreso'     => 'required|date',
        'operacion_id'      => 'required|exists:sub_areas,id',
        'area_id'           => 'required|exists:area,id',
]);

        $usuario = Usuarios::findOrFail($id);
        $usuario->update([
            'nombres' => $request->input('nombres'),
            'apellidos' => $request->input('apellidos'),
            'tipo_documento' => $request->input('tipo_documento'),
            'numero_documento' => $request->input('numero_documento'),
            'email' => $request->input('email'),
            'fecha_ingreso' => $request->input('fecha_ingreso'),
            'operacion_id' => $request->input('operacion_id'),
            'area_id' => $request->input('area_id'),
        ]);

        return redirect()->route('gestionUsuario.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy($id)
    {
        $usuario = Usuarios::findOrFail($id);
        $usuario->delete();
        return redirect()->route('gestionUsuario.index')->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Buscar usuario por número de documento (AJAX)
     */
    public function findByDocumento(Request $request)
    {
        $numero = $request->query('numero');
        \Illuminate\Support\Facades\Log::info('findByDocumento called', ['numero' => $numero]);
        if (!$numero) {
            \Illuminate\Support\Facades\Log::info('findByDocumento missing numero');
            return response()->json(['error' => 'missing_number'], 400);
        }

        $usuario = Usuarios::where('numero_documento', $numero)->first();
        \Illuminate\Support\Facades\Log::info('findByDocumento result', ['usuario' => $usuario ? $usuario->toArray() : null]);

        if (!$usuario) {
            return response()->json(null, 204);
        }

        return response()->json($usuario);
    }
}