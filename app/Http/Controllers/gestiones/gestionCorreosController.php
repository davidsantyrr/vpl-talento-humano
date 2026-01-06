<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class gestionCorreosController extends Controller
{
    public function index()
    {
        // Cargar correos (paginado para la vista)
        $correos = \App\Models\Correos::paginate(10);

        // Obtener roles disponibles desde periodicidad (columna rol_periodicidad)
        $rolesDisponibles = \App\Models\periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();

        return view('gestiones.gestionCorreos', compact('correos', 'rolesDisponibles'));
    }

    public function create()
    {
        return view('gestiones.createGestionCorreos');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rol' => 'required|string|max:191',
            'correo' => 'required|email|max:191',
        ]);

        \App\Models\Correos::create($data);

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo agregado.');
    }

    public function edit($id)
    {
        $correo = \App\Models\Correos::findOrFail($id);
        
        // Obtener roles disponibles para el select
        $rolesDisponibles = \App\Models\periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();
            
        return view('gestiones.editGestionCorreos', compact('correo', 'rolesDisponibles'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'rol' => 'required|string|max:191',
            'correo' => 'required|email|max:191',
        ]);

        $correo = \App\Models\Correos::findOrFail($id);
        $correo->update($data);

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo actualizado.');
    }

    public function destroy($id)
    {
        $correo = \App\Models\Correos::findOrFail($id);
        $correo->delete();

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo eliminado.');
    }
}