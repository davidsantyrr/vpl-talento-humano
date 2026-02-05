<?php

namespace App\Http\Controllers\ElementoXcargo;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 10;
        $q = trim((string) $request->get('q', ''));

        $query = Cargo::query();
        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }
        $cargos = $query->orderBy('nombre')->paginate($perPage)->appends($request->only('q', 'per_page'));

        return view('elementoxcargo.cargos', compact('cargos', 'q', 'perPage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255', 'unique:cargos,nombre']]);
        Cargo::create($data);
        return back()->with('status', 'Cargo creado');
    }

    public function update(Request $request, Cargo $cargo)
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255', 'unique:cargos,nombre,' . $cargo->id]]);
        $cargo->update($data);
        return back()->with('status', 'Cargo actualizado');
    }

    public function destroy(Cargo $cargo)
    {
        $cargo->delete();
        return back()->with('status', 'Cargo eliminado');
    }
}
