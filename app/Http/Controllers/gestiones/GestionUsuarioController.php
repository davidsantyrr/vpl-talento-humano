<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use App\Models\SubArea;
use App\Models\Area;

class gestionUsuarioController extends Controller
{
    public function index()
    {
        $usuarios   = Usuarios::orderBy('id', 'desc')->get();
        $operations = SubArea::orderBy('operationName')->get();
        $areas      = Area::orderBy('nombre_area')->get();

        return view('gestiones.gestionUsuarios', compact(
        'usuarios',
        'operations',
        'areas'
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