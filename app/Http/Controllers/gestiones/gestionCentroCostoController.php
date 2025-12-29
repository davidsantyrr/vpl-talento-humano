<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CentroCosto;

class GestionCentroCostoController extends Controller
{
    public function index()
    {
        $centros = CentroCosto::orderBy('id', 'desc')->get();
        return view('gestiones.gestionCentroCosto', compact('centros'));
    }
    public function store(Request $request)
    {
    $request->validate([
        'centroCostoName' => 'required|string|max:255',
    ]);

    CentroCosto::create([
        'nombre_centro_costo' => $request->centroCostoName,
    ]);

    return redirect()
        ->route('gestionCentroCosto.index')
        ->with('success', 'Centro de costo creado correctamente');
    }

    public function edit($id)
    {
        $editCentro = CentroCosto::findOrFail($id);
        $centros = CentroCosto::orderBy('id', 'desc')->get();

        return view('gestiones.gestionCentroCosto', compact('centros', 'editCentro'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'centroCostoName' => 'required|string|max:255',
        ]);

        $centro = CentroCosto::findOrFail($id);
        $centro->update([
        'nombre_centro_costo' => $request->centroCostoName,
]);

        return redirect()
            ->route('gestionCentroCosto.index')
            ->with('success', 'Centro de costo actualizado correctamente');
    }

    public function destroy($id)
    {
        CentroCosto::findOrFail($id)->delete();

        return redirect()
            ->route('gestionCentroCosto.index')
            ->with('success', 'Centro de costo eliminado correctamente');
    }
}