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

    $nombre = $this->cleanText($request->input('areaName'));
    Area::create(['nombre_area' => $nombre]);

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
    $nombre = $this->cleanText($request->input('areaName'));
    $Ar->update(['nombre_area' => $nombre]);

    return redirect()->route('gestionArea.index')->with('success', 'Área actualizada');

}

public function destroy($id)
{
    $Ar = Area::findOrFail($id);
    $Ar->delete();
    return redirect()->route('gestionArea.index')->with('success','Área eliminada');
}

// helper: limpia texto (elimina BOM y caracteres no imprimibles, normaliza espacios)
private function cleanText($value): string
{
    $orig = (string)($value ?? '');
    $clean = preg_replace('/[[:^print:]]+/u', '', $orig);
    $clean = preg_replace('/[\x{FEFF}\x{00A0}]/u', '', $clean);
    return preg_replace('/\s+/u', ' ', trim($clean));
}
}