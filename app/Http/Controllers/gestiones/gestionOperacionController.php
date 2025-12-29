<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Operation;

class gestionOperacionController extends Controller
{
	/** Mostrar listado de operaciones y formulario de creación */
	public function index()
	{
		$operations = Operation::orderBy('id', 'desc')->get();
		return view('gestiones.gestionOperacion', compact('operations'));
	}

	/** Guardar nueva operación */
	public function store(Request $request)
	{
		$request->validate([
			'operationName' => 'required|string|max:255',
		]);

		Operation::create($request->only('operationName'));

		return redirect()->route('gestionOperacion.index')->with('success', 'Operación creada correctamente');
	}

	/** Mostrar el formulario de edición */
	public function edit($id)
	{
		$editOperation = Operation::findOrFail($id);
		$operations = Operation::orderBy('id', 'desc')->get();
		return view('gestiones.gestionOperacion', compact('operations', 'editOperation'));
	}

	/** Actualizar operación */
	public function update(Request $request, $id)
	{
		$request->validate([
			'operationName' => 'required|string|max:255',
		]);

		$op = Operation::findOrFail($id);
		$op->update($request->only('operationName'));

		return redirect()->route('gestionOperacion.index')->with('success', 'Operación actualizada');
	}

	/** Eliminar operación */
	public function destroy($id)
	{
		$op = Operation::findOrFail($id);
		$op->delete();
		return redirect()->route('gestionOperacion.index')->with('success', 'Operación eliminada');
	}
}

