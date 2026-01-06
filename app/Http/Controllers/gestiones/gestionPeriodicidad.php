<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class gestionPeriodicidad extends Controller
{
	public function index()
	{
		// Obtener el rol principal del usuario autenticado
		$user = session('auth.user');
		$rolUsuario = null;
		
		Log::info('🔍 DEBUG index() - Usuario en sesión:', [
			'user_exists' => !is_null($user),
			'user_data' => $user
		]);
		
		if ($user && isset($user['roles']) && is_array($user['roles']) && count($user['roles']) > 0) {
			// Extraer texto del rol principal como string
			$rolUsuario = isset($user['roles'][0]['roles']) ? (string) $user['roles'][0]['roles'] : null;
			$rolUsuario = trim((string) $rolUsuario) === '' ? null : $rolUsuario;
			Log::info('✅ Rol extraído en index():', [
				'rol' => $rolUsuario,
				'primer_rol_completo' => $user['roles'][0]
			]);
		} else {
			Log::warning('⚠️ No se pudo extraer el rol del usuario en index()');
		}

		// Cargar todas las periodicidades sin filtrar por rol (para testing)
		// Más adelante puedes activar el filtro por rol
		$periodicidades = \App\Models\periodicidad::paginate(10);

		// Cargar productos para el select (mostrar sku + nombre)
		$productos = \App\Models\Producto::select('sku','name_produc')->orderBy('name_produc')->get();

		return view('gestiones.gestionPeriodicidad', compact('periodicidades','productos', 'rolUsuario'));
	}

	public function store(Request $request)
	{
		try {
			$data = $request->validate([
				'nombre' => 'required|string|max:191',
				'sku' => 'required|string|max:191',
				'periodicidad' => 'required|string|max:50',
				'aviso_rojo' => 'required|string|max:50',
				'aviso_amarillo' => 'required|string|max:50',
				'aviso_verde' => 'required|string|max:50',
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
				'aviso_rojo' => $data['aviso_rojo'],
				'aviso_amarillo' => $data['aviso_amarillo'],
				'aviso_verde' => $data['aviso_verde'],
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
		try {
			$data = $request->validate([
				'nombre' => 'required|string|max:191',
				'sku' => 'required|string|max:191',
				'periodicidad' => 'required|string|max:50',
				'aviso_rojo' => 'required|string|max:50',
				'aviso_amarillo' => 'required|string|max:50',
				'aviso_verde' => 'required|string|max:50',
			]);

			$model = \App\Models\periodicidad::find($id);
			if (!$model) {
				return redirect()->route('gestionPeriodicidad.index')->with('error', 'Elemento no encontrado.');
			}

			// Actualizar sin tocar el rol
			$model->sku = $data['sku'];
			$model->nombre = $data['nombre'];
			$model->periodicidad = $data['periodicidad'];
			$model->aviso_rojo = $data['aviso_rojo'];
			$model->aviso_amarillo = $data['aviso_amarillo'];
			$model->aviso_verde = $data['aviso_verde'];
			$model->save();

			return redirect()->route('gestionPeriodicidad.index')->with('success', 'Elemento actualizado exitosamente.');
		} catch (\Exception $e) {
			Log::error('Error actualizando periodicidad', ['error' => $e->getMessage()]);
			return redirect()->route('gestionPeriodicidad.index')->with('error', 'Error al actualizar: ' . $e->getMessage());
		}
	}
}
