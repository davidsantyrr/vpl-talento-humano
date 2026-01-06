<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\CorreosNotificacion;

class gestionCorreos extends Controller
{
	/**
	 * Obtener roles disponibles desde periodicidad
	 */
	private function getRolesDisponibles()
	{
		return \App\Models\periodicidad::whereNotNull('rol_periodicidad')
			->where('rol_periodicidad', '!=', '')
			->distinct()
			->pluck('rol_periodicidad')
			->filter()
			->sort()
			->values()
			->toArray();
	}

	public function index()
	{
		Log::info('🔍 Iniciando gestionCorreos.index');
		
		try {
			// Obtener correos paginados
			$correos = CorreosNotificacion::paginate(15);
			Log::info('✅ Correos obtenidos', ['count' => $correos->count()]);

			// Obtener roles disponibles desde periodicidad (columna rol_periodicidad)
			$rolesDisponibles = $this->getRolesDisponibles();
			Log::info('✅ Roles disponibles obtenidos', ['roles' => $rolesDisponibles]);

			return view('gestiones.gestionCorreos', compact('correos', 'rolesDisponibles'));
		} catch (\Exception $e) {
			Log::error('❌ Error en gestionCorreos.index', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			
			// Fallback con array vacío
			$correos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
			$rolesDisponibles = [];
			
			return view('gestiones.gestionCorreos', compact('correos', 'rolesDisponibles'))
				->with('error', 'Error al cargar los datos: ' . $e->getMessage());
		}
	}

	public function store(Request $request)
	{
		$data = $request->validate([
			'rol' => 'required|string|max:191',
			'correo' => 'required|email|max:191',
		]);

		CorreosNotificacion::create($data);

		return redirect()->route('gestionCorreos.index')->with('success', 'Correo agregado exitosamente.');
	}

	public function edit($id)
	{
		$correo = CorreosNotificacion::findOrFail($id);
		
		// Obtener roles disponibles para el select
		$rolesDisponibles = $this->getRolesDisponibles();

		if (view()->exists('gestiones.editCorreo')) {
			return view('gestiones.editCorreo', compact('correo', 'rolesDisponibles'));
		}

		return response()->json($correo);
	}

	public function update(Request $request, $id)
	{
		$data = $request->validate([
			'rol' => 'required|string|max:191',
			'correo' => 'required|email|max:191',
		]);

		$model = \App\Models\CorreosNotificacion::findOrFail($id);
		$model->update($data);

		return redirect()->route('gestionCorreos.index')->with('success', 'Correo actualizado exitosamente.');
	}

	public function destroy($id)
	{
		$model = \App\Models\CorreosNotificacion::findOrFail($id);
		$model->delete();

		return redirect()->route('gestionCorreos.index')->with('success', 'Correo eliminado exitosamente.');
	}
}