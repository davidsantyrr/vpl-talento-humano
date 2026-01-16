<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\SubArea;
use App\Models\Usuarios;
use App\Models\Entrega;
use App\Models\ElementoXEntrega;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Jobs\EnviarCorreoEntrega;

class FormularioEntregasController extends Controller
{
	// Mostrar el formulario de entregas (migrado desde EntregaController::create)
	public function create()
	{
		$operations = SubArea::orderBy('operationName')->get();
		$allProducts = collect();
		try {
			$conn = (new Producto())->getConnectionName() ?: config('database.default');
			$hasSku = Schema::connection($conn)->hasColumn('productos', 'sku');
			$hasName = Schema::connection($conn)->hasColumn('productos', 'name_produc');
			if ($hasSku && $hasName) {
				$allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();
			} else {
				$rows = Producto::limit(500)->get();
				$allProducts = $rows->map(function($r){
					$sku = $r->sku ?? $r->codigo ?? $r->id ?? null;
					$name = $r->name_produc ?? $r->nombre ?? $r->name ?? '';
					return (object)['sku' => $sku, 'name_produc' => $name];
				})->filter(fn($x) => $x->sku !== null)->values();
			}
		} catch (\Exception $e) {
			$allProducts = collect();
		}

		return view('formularioEntregas.formularioEntregas', compact('operations','allProducts'));
	}

	// Procesar el envío del formulario, generar PDF y devolver redirección/JSON (migrado desde EntregaController::store)
	public function store(Request $request)
	{
		// Procesar el formulario de entregas (validación mínima y persistencia)
		$data = $request->validate([
			'tipo_documento' => ['nullable','string'],
			'numberDocumento' => ['nullable','string'],
			'nombre' => ['nullable','string'],
			'apellidos' => ['nullable','string'],
			'tipo' => ['required','string','in:prestamo,primera vez,periodica,cambio'],
			'operacion_id' => ['nullable','integer','exists:sub_areas,id'],
			'cargo_id' => ['nullable','integer'],
			'elementos' => ['required','string'], // JSON
			'recepcion_id' => ['nullable','integer','exists:recepciones,id'],
			'firma' => ['nullable','string'],
			'comprobante_path' => ['nullable','string'],
		]);

		// Usuario en sesión (misma convención usada en RecepcionController)
		$authUser = session('auth.user');
		$nombreUsuario = 'usuario';
		$emailUsuario = 'sin-email@example.com';
		$primerRol = 'web';

		if (is_array($authUser) && isset($authUser['name'])) { $nombreUsuario = $authUser['name']; }
		elseif (is_object($authUser) && isset($authUser->name)) { $nombreUsuario = $authUser->name; }

		if (is_array($authUser) && isset($authUser['email'])) { $emailUsuario = $authUser['email']; }
		elseif (is_object($authUser) && isset($authUser->email)) { $emailUsuario = $authUser->email; }

		if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles']) && !empty($authUser['roles'])) {
			$first = $authUser['roles'][0] ?? null;
			if (is_array($first) && isset($first['roles'])) { $primerRol = $first['roles']; }
			elseif (is_object($first) && isset($first->roles)) { $primerRol = $first->roles; }
		}

		DB::beginTransaction();
		try {
			$entregaData = [
				'rol_entrega' => $primerRol,
				'entrega_user' => $nombreUsuario,
				'entrega_email' => $emailUsuario,
				'tipo_entrega' => $data['tipo'],
				'tipo_documento' => $data['tipo_documento'] ?? null,
				'numero_documento' => $data['numberDocumento'] ?? null,
				'nombres' => $data['nombre'] ?? null,
				'apellidos' => $data['apellidos'] ?? null,
				'sub_area_id' => !empty($data['operacion_id']) ? (int)$data['operacion_id'] : null,
				'usuarios_id' => null,
				'recepciones_id' => !empty($data['recepcion_id']) ? (int)$data['recepcion_id'] : null,
				'recibido' => false,
				'created_at' => now(),
				'updated_at' => now(),
			];

			// Normalizar comprobante_path si viene desde la generación de PDF cliente
			$comprobantePath = $request->input('comprobante_path') ?? ($data['comprobante_path'] ?? null);
			if (!empty($comprobantePath)) {
				$comprobantePath = preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $comprobantePath);
				$comprobantePath = ltrim($comprobantePath, '/');
				$entregaData['comprobante_path'] = $comprobantePath;
			}

			$entregaId = DB::table('entregas')->insertGetId($entregaData);

			$items = json_decode($data['elementos'] ?? '[]', true) ?: [];
			if (empty($items)) {
				throw new \Exception('Debe agregar al menos un elemento a la entrega');
			}

			foreach ($items as $it) {
				if (empty($it['sku'])) continue;
				DB::table('elemento_x_entrega')->insert([
					'entrega_id' => $entregaId,
					'sku' => (string) ($it['sku'] ?? ''),
					'cantidad' => (string) ($it['cantidad'] ?? 1),
					'created_at' => now(),
					'updated_at' => now(),
				]);
			}

			DB::commit();

			// Obtener entrega para enviar correo
			$entrega = DB::table('entregas')->where('id', $entregaId)->first();

			// Disparar job de correo si hay email válido
			if (!empty($emailUsuario) && $emailUsuario !== 'sin-email@example.com') {
				try {
					EnviarCorreoEntrega::dispatchSync($entrega, $items, $emailUsuario, $entregaData['comprobante_path'] ?? null);
				} catch (\Exception $e) {
					Log::error('Error al enviar correo de entrega', ['error' => $e->getMessage(), 'entrega_id' => $entregaId]);
				}
			}

			if ($request->wantsJson() || $request->ajax()) {
				return response()->json(['success' => true, 'message' => 'Entrega registrada correctamente', 'entrega_id' => $entregaId], 200);
			}

			return redirect()->back()->with('status', 'Entrega registrada correctamente');
		} catch (\Throwable $e) {
			DB::rollBack();
			Log::error('Error guardando entrega desde formulario', ['error' => $e->getMessage(), 'request' => $request->except(['firma'])]);
			if ($request->wantsJson() || $request->ajax()) {
				return response()->json(['success' => false, 'message' => 'Ocurrió un error al registrar la entrega: ' . $e->getMessage()], 500);
			}
			return redirect()->back()->with('error', 'Ocurrió un error al registrar la entrega: ' . $e->getMessage());
		}
	}
	
	// API: Lista de productos de cargo_productos (mantenida aquí para uso en formulario)
	public function cargoProductos(Request $request)
	{
		$cargoId = (int) $request->query('cargo_id');
		$subAreaId = (int) $request->query('sub_area_id');
		try {
			$query = DB::table('cargo_productos')->select(['sku', 'name_produc']);
			if ($cargoId) { $query->where('cargo_id', $cargoId); }
			if ($subAreaId) { $query->where('sub_area_id', $subAreaId); }
			$rows = $query->orderBy('name_produc')->get();
			$data = $rows->map(function ($r) {
				return ['sku' => (string) ($r->sku ?? ''), 'name_produc' => (string) ($r->name_produc ?? '')];
			})->filter(fn($x) => !empty($x['sku']))->values();
			return response()->json($data, 200);
		} catch (\Throwable $e) {
			Log::warning('cargo_productos query failed', ['error' => $e->getMessage()]);
			return response()->json([], 200);
		}
	}

	// API: Buscar recepciones (usada por el formulario)
	public function buscarRecepciones(Request $request)
	{
		// ...copiar lógica de EntregaController::buscarRecepciones tal cual...
	}

	// API: Obtener nombres de productos por SKUs (usada por el formulario)
	public function obtenerNombresProductos(Request $request)
	{
		// ...copiar lógica de EntregaController::obtenerNombresProductos tal cual...
	}

	// Endpoint: logging cliente (migrado desde EntregaController::logComprobanteHit)
	public function logComprobanteHit(Request $request)
	{
		$payload = $request->getContent();
		$data = null;
		try { $data = json_decode($payload, true); } catch (\Throwable $e) { $data = ['raw' => $payload]; }
		Log::info('Comprobante hit desde cliente', ['data' => $data, 'ip' => $request->ip()]);
		return response()->json(['ok' => true]);
	}
}
