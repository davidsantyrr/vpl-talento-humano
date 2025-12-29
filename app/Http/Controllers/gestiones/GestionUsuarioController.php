<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use App\Models\Operation;
use App\Models\Area;

class gestionUsuarioController extends Controller
{
    public function index()
    {
        $usuarios   = Usuarios::orderBy('id', 'desc')->get();
        $operations = Operation::orderBy('operationName')->get();
        $areas      = Area::orderBy('areaName')->get();

        return view('gestiones.gestionUsuarios', compact(
        'usuarios',
        'operations',
        'areas'
    ));
}

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'tipo_documento' => 'nullable|string|max:100',
            'numero_documento' => 'required|string|max:100|unique:usuarios,numero_documento',
            'email' => 'required|email|unique:usuarios,email',
            'fecha_ingreso' => 'required|date',
            'operacion_id' => 'nullable|exists:operation,id',
            'area_id' => 'nullable|exists:area,id',
        ]);

        Usuarios::create([
            'nombre' => $request->input('nombre'),
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
        $usuarios = Usuarios::orderBy('id', 'desc')->get();

        return view('gestiones.gestionUsuarios', compact('usuarios', 'editUsuario'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'tipo_documento' => 'nullable|string|max:100',
            'numero_documento' => 'required|string|max:100|unique:usuarios,numero_documento,' . $id,
            'email' => 'required|email|unique:usuarios,email,' . $id,
            'fecha_ingreso' => 'required|date',
            'operacion_id' => 'nullable|exists:operation,id',
            'area_id' => 'nullable|exists:area,id',
        ]);

        $usuario = Usuarios::findOrFail($id);
        $usuario->update([
            'nombre' => $request->input('nombre'),
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
}