<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;

class gestionAreaController extends Controller
{
    public function index()
    {
        $areas = Area::orderBy('id', 'desc')->get();
        return view('gestiones.gestionArea', compact('areas'));
    }


public function store(Request $request)
{
    $request->validate([
        'areaName' => 'required|string|max:255',
    ]);

    Area::create([
        'nombre_area' => $request->input('areaName'),
    ]);

    return redirect()->route('gestionArea.index')->with('success', 'Área creada exitosamente.');
}

public function edit($id)
{
    $editArea = Area::findOrFail($id);
    $areas = Area::orderBy('id', 'desc')->get();
    return view('gestiones.gestionArea', compact('areas', 'editArea'));
}

public function update(Request $request, $id)
{
    $request->validate([
        'areaName' => 'required|string|max:255',
    ]);

    $Ar = Area::findOrFail($id);
    $Ar->update([
        'nombre_area' => $request->input('areaName'),
    ]);

    return redirect()->route('gestionArea.index')->with('success', 'Área actualizada');

}

public function destroy($id)
{
    $Ar = Area::findOrFail($id);
    $Ar->delete();
    return redirect()->route('gestionArea.index')->with('success','Área eliminada');
}
}