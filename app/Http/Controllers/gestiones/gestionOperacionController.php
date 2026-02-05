<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;

class gestionOperacionController extends Controller
{
	/** Mostrar listado de operaciones y formulario de creación */
	public function index()
	{
		$operations = SubArea::orderBy('id', 'desc')->get();
		return view('gestiones.gestionOperacion', compact('operations'));
	}
	public function create()
    {
		$operations = SubArea::orderBy('operationName')->get();

		return view('formularioEntregas.formularioEntregas', compact('operations'));
    }

	/** Guardar nueva operación */
	public function store(Request $request)
	{
		$request->validate([
			'operationName' => 'required|string|max:255',
		]);

		SubArea::create($request->only('operationName'));

		return redirect()->route('gestionOperacion.index')->with('success', 'Operación creada correctamente');
	}

	/** Mostrar el formulario de edición */
	public function edit($id)
	{
		$editOperation = SubArea::findOrFail($id);
		$operations = SubArea::orderBy('id', 'desc')->get();
		return view('gestiones.gestionOperacion', compact('operations', 'editOperation'));
	}

	/** Actualizar operación */
	public function update(Request $request, $id)
	{
		$request->validate([
			'operationName' => 'required|string|max:255',
		]);

		$op = SubArea::findOrFail($id);
		$op->update($request->only('operationName'));

		return redirect()->route('gestionOperacion.index')->with('success', 'Operación actualizada');
	}

	/** Eliminar operación */
	public function destroy($id)
	{
		$op = SubArea::findOrFail($id);
		$op->delete();
		return redirect()->route('gestionOperacion.index')->with('success', 'Operación eliminada');
	}
}

