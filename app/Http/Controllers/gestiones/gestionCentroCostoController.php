<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CentroCosto;

class gestionCentroCostoController extends Controller
{
    /** Mostrar listado de centros de costo y formulario de creación */
    public function index()
    {
        $centros = CentroCosto::orderBy('id', 'desc')->get();
        return view('gestiones.gestionCentroCosto', compact('centros'));
    }

    /** Guardar nuevo centro de costo */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        CentroCosto::create([
            'nombre_centro_costo' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
        ]);

        return redirect()->route('gestionCentroCosto.index')->with('success', 'Centro de costo creado correctamente');
    }

    /** Mostrar el formulario de edición */
    public function edit($id)
    {
        $editCentro = CentroCosto::findOrFail($id);
        $centros = CentroCosto::orderBy('id', 'desc')->get();
        return view('gestiones.gestionCentroCosto', compact('centros', 'editCentro'));
    }

    /** Actualizar centro de costo */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $centro = CentroCosto::findOrFail($id);
        $centro->update([
            'nombre_centro_costo' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
        ]);

        return redirect()->route('gestionCentroCosto.index')->with('success', 'Centro de costo actualizado');
    }

    /** Eliminar centro de costo */
    public function destroy($id)
    {
        $centro = CentroCosto::findOrFail($id);
        $centro->delete();
        return redirect()->route('gestionCentroCosto.index')->with('success', 'Centro de costo eliminado');
    }
}