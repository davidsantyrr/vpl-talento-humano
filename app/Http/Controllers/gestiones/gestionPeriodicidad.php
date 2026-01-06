<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class gestionPeriodicidad extends Controller
{
	public function index()
	{
		// Cargar periodicidades (paginado para la vista)
		$periodicidades = \App\Models\periodicidad::paginate(10);

		// Cargar productos para el select (mostrar sku + nombre)
		$productos = \App\Models\Producto::select('sku','name_produc')->get();

		return view('gestiones.gestionPeriodicidad', compact('periodicidades','productos'));
	}

	public function store(Request $request)
	{
		$data = $request->validate([
			'nombre' => 'required|string|max:191',
			'periodicidad' => 'nullable|string|max:50',
			'aviso_rojo' => 'nullable|string|max:50',
			'aviso_amarillo' => 'nullable|string|max:50',
			'aviso_verde' => 'nullable|string|max:50',
		]);

		\App\Models\periodicidad::create($data);

		return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento agregado.');
	}

	/**
	 * Guardar actualizaciones masivas desde la tabla
	 */
	public function saveAll(Request $request)
	{
		$periods = $request->input('periodicidad', []);
		$rojos = $request->input('rojo', []);
		$amarillos = $request->input('amarillo', []);
		$verdes = $request->input('verde', []);

		foreach ($periods as $id => $p) {
			$model = \App\Models\periodicidad::find($id);
			if ($model) {
				$model->periodicidad = $p;
				$model->aviso_rojo = $rojos[$id] ?? null;
				$model->aviso_amarillo = $amarillos[$id] ?? null;
				$model->aviso_verde = $verdes[$id] ?? null;
				$model->save();
			}
		}

		return redirect()->route('gestionPeriodicidad.index')->with('success', 'Cambios guardados.');
	}

	public function destroy($id)
	{
		$model = \App\Models\periodicidad::find($id);
		if ($model) {
			$model->delete();
			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento eliminado.');
		}

		return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento no encontrado.');
	}

	/**
	 * Mostrar formulario de creación (si existe la vista), o devolver JSON
	 */
	public function create(Request $request)
	{
		if (view()->exists('gestiones.createPeriodicidad')) {
			return view('gestiones.createPeriodicidad');
		}

		return response()->json(['message' => 'Use POST /gestiones/gestionPeriodicidad to create'], 200);
	}

	/**
	 * Mostrar datos de un elemento (para edición). Devuelve vista si existe, sino JSON
	 */
	public function edit($id)
	{
		$model = \App\Models\periodicidad::find($id);
		if (!$model) {
			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento no encontrado.');
		}

		if (view()->exists('gestiones.editPeriodicidad')) {
			return view('gestiones.editPeriodicidad', compact('model'));
		}

		return response()->json($model);
	}

	/**
	 * Actualizar un elemento existente
	 */
	public function update(Request $request, $id)
	{
		$data = $request->validate([
			'nombre' => 'required|string|max:191',
			'periodicidad' => 'nullable|string|max:50',
			'aviso_rojo' => 'nullable|string|max:50',
			'aviso_amarillo' => 'nullable|string|max:50',
			'aviso_verde' => 'nullable|string|max:50',
		]);

		$model = \App\Models\periodicidad::find($id);
		if (!$model) {
			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento no encontrado.');
		}

		$model->fill($data);
		$model->save();

		return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento actualizado.');
	}

    
}
