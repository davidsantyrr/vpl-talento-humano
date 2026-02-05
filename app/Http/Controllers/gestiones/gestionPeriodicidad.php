<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class gestionPeriodicidad extends Controller
{
	public function index()
	{
		// Obtener rol del usuario en sesión y normalizarlo
		$user = session('auth.user');
		$rolUsuario = null;
		if ($user && isset($user['roles']) && is_array($user['roles']) && count($user['roles']) > 0) {
			$rawRol = $user['roles'][0]['roles'] ?? ($user['roles'][0]['name'] ?? null);
			$rolUsuario = is_scalar($rawRol) ? trim((string)$rawRol) : null;
		}

		// Filtrar periodicidades por rol_periodicidad (solo las del rol)
		$query = \App\Models\periodicidad::query();
		if (!empty($rolUsuario)) {
			$query->whereRaw('LOWER(rol_periodicidad) LIKE ?', ['%'.strtolower($rolUsuario).'%']);
		}
		$periodicidades = $query->paginate(10);

		// Cargar productos para el select, filtrando por categoría según rol (HSEQ/EPP vs Dotación)
		$prodQuery = \App\Models\Producto::select('sku','name_produc')->orderBy('name_produc');
		$filtros = [];
		$normRol = strtolower(str_replace(' ', '', (string)$rolUsuario));
		if ($normRol !== '') {
			if (strpos($normRol, 'hseq') !== false || strpos($normRol, 'seguridad') !== false) {
				$filtros = array_map('trim', explode(',', config('vpl.role_filters.hseq', '')));
			} elseif (strpos($normRol, 'talento') !== false || strpos($normRol, 'humano') !== false || $normRol === 'th') {
				$filtros = array_map('trim', explode(',', config('vpl.role_filters.talento', '')));
			}
		}
		$filtros = array_values(array_filter(array_unique(array_map(function($t){ return mb_strtolower($t); }, $filtros))));
		if (!empty($filtros)) {
			$prodQuery->where(function($q) use ($filtros){
				foreach ($filtros as $i => $term) {
					$like = '%'.$term.'%';
					if ($i === 0) $q->whereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
					else $q->orWhereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
				}
			});
		}
		$productos = $prodQuery->get();

		return view('gestiones.gestionPeriodicidad', compact('periodicidades','productos', 'rolUsuario'));
	}

	public function store(Request $request)
	{
		try {
			$data = $request->validate([
				'nombre' => 'required|string|max:191',
				'sku' => 'required|string|max:191',
				'periodicidad' => 'required|string|max:50',
				'aviso_rojo' => 'required|integer|min:0',
				'aviso_amarillo' => 'required|integer|min:0',
				'aviso_verde' => 'required|integer|min:0',
			]);

			// Obtener rol del usuario desde sesión (igual que en entregas)
			$user = session('auth.user');
			$rolUsuario = null;
			
			if ($user && isset($user['roles']) && is_array($user['roles']) && count($user['roles']) > 0) {
				$rawRol = $user['roles'][0]['roles'] ?? null;
				$rolUsuario = is_scalar($rawRol) ? trim((string)$rawRol) : null;
			}
			
			if (!$rolUsuario) {
				return redirect()->route('gestionPeriodicidad.index')->with('error', 'No se pudo determinar el rol del usuario en sesión.');
			}

			// Evitar duplicados por SKU + ROL_PERIODICIDAD
			$existente = \App\Models\periodicidad::where('sku', $data['sku'])
				->where('rol_periodicidad', $rolUsuario)
				->first();
				
			if ($existente) {
				return redirect()->route('gestionPeriodicidad.index')->with('error', 'Este producto ya tiene una configuración para tu rol.');
			}

			// Crear registro con rol_periodicidad
			\App\Models\periodicidad::create([
				'sku' => $data['sku'],
				'nombre' => $data['nombre'],
				'rol_periodicidad' => $rolUsuario, // ← USAR rol_periodicidad
				'periodicidad' => $data['periodicidad'],
				'aviso_rojo' => (string)$data['aviso_rojo'],
				'aviso_amarillo' => (string)$data['aviso_amarillo'],
				'aviso_verde' => (string)$data['aviso_verde'],
			]);

			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento agregado exitosamente.');
		} catch (\Exception $e) {
			Log::error('Error guardando periodicidad', ['error' => $e->getMessage()]);
			return redirect()->route('gestionPeriodicidad.index')->with('error', 'Error al guardar: ' . $e->getMessage());
		}
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
				$model->aviso_rojo = isset($rojos[$id]) ? (string)intval($rojos[$id]) : null;
				$model->aviso_amarillo = isset($amarillos[$id]) ? (string)intval($amarillos[$id]) : null;
				$model->aviso_verde = isset($verdes[$id]) ? (string)intval($verdes[$id]) : null;
				$model->save();
			}
		}

		// También actualizar registros que no cambiaron periodicidad (si llegan sin 'periodicidad' por estar disabled)
		foreach ($rojos as $id => $v) {
			$model = \App\Models\periodicidad::find($id);
			if ($model) {
				$model->aviso_rojo = (string)intval($v);
				if (isset($amarillos[$id])) $model->aviso_amarillo = (string)intval($amarillos[$id]);
				if (isset($verdes[$id])) $model->aviso_verde = (string)intval($verdes[$id]);
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
		try {
			$data = $request->validate([
				'nombre' => 'required|string|max:191',
				'sku' => 'required|string|max:191',
				'periodicidad' => 'required|string|max:50',
				'aviso_rojo' => 'required|integer|min:0',
				'aviso_amarillo' => 'required|integer|min:0',
				'aviso_verde' => 'required|integer|min:0',
			]);

			$model = \App\Models\periodicidad::find($id);
			if (!$model) {
				return redirect()->route('gestionPeriodicidad.index')->with('error', 'Elemento no encontrado.');
			}

			$model->sku = $data['sku'];
			$model->nombre = $data['nombre'];
			$model->periodicidad = $data['periodicidad'];
			$model->aviso_rojo = (string)$data['aviso_rojo'];
			$model->aviso_amarillo = (string)$data['aviso_amarillo'];
			$model->aviso_verde = (string)$data['aviso_verde'];
			$model->save();

			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento actualizado exitosamente.');
		} catch (\Exception $e) {
			Log::error('Error actualizando periodicidad', ['error' => $e->getMessage()]);
			return redirect()->route('gestionPeriodicidad.index')->with('error', 'Error al actualizar: ' . $e->getMessage());
		}
	}
}
