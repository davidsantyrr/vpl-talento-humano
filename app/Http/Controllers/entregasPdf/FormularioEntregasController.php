<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\SubArea;
use App\Models\Usuarios;
use App\Models\Cargo;
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
			'enviar_a_gestion_correos' => ['nullable','boolean'],
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
			// Determinar rol_entrega preferiblemente a partir del destinatario (cargo),
			// si no está disponible, usar el primer rol del usuario en sesión.
			$rolEntrega = $primerRol;
			if (!empty($data['cargo_id'])) {
				try {
					$c = Cargo::find($data['cargo_id']);
					if ($c && !empty($c->nombre)) {
						$rolEntrega = $c->nombre;
					}
				} catch (\Throwable $e) {
					// ignore and fallback
				}
			} elseif (!empty($data['numberDocumento'])) {
				try {
					$u = Usuarios::where('numero_documento', $data['numberDocumento'])->first();
					if ($u && $u->cargo && !empty($u->cargo->nombre)) {
						$rolEntrega = $u->cargo->nombre;
					}
				} catch (\Throwable $e) {
					// ignore
				}
			}

			$entregaData = [
				'rol_entrega' => $rolEntrega,
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
				// Normalizar SKU y cantidad
				$sku = trim((string) ($it['sku'] ?? ''));
				// Normalizaciones adicionales: quitar espacios extra y caracteres no alfanuméricos comunes
				$skuNorm = preg_replace('/[^A-Za-z0-9_\-]/u', '', $sku);
				$skuUpper = mb_strtoupper($skuNorm);
				$cantidadRaw = (string) ($it['cantidad'] ?? '1');
				$cantidad = floatval(str_replace(',', '.', $cantidadRaw));
				if ($cantidad <= 0) $cantidad = 1;

				DB::table('elemento_x_entrega')->insert([
					'entrega_id' => $entregaId,
					'sku' => $sku,
					'cantidad' => (string) $cantidad,
					'created_at' => now(),
					'updated_at' => now(),
				]);

				// Intentar actualizar stock en la tabla productos (buscar por sku o por nombre)
				try {
					// Intentar búsqueda por varias formas normalizadas
					$producto = Producto::where('sku', $sku)->first();
					if (!$producto && $skuNorm !== $sku) {
						$producto = Producto::where('sku', $skuNorm)->first();
					}
					if (!$producto) {
						$producto = Producto::where('sku', $skuUpper)->first();
					}
					if (!$producto) {
						$producto = Producto::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
					}
					if (!$producto) {
						$producto = Producto::whereRaw('LOWER(name_produc) = ?', [mb_strtolower($sku)])->first();
					}

					if ($producto) {
						$current = (float) ($producto->stock_produc ?? 0);
						$new = max(0, $current - $cantidad);
						try {
							$connName = $producto->getConnectionName() ?: config('database.default');
							$affected = DB::connection($connName)
								->table($producto->getTable())
								->where('sku', $producto->sku)
								->update(['stock_produc' => $new]);
							Log::info('Stock actualizado por entrega', ['sku_busqueda' => $sku, 'sku_norm' => $skuNorm, 'sku_found' => $producto->sku, 'cantidad_entregada' => $cantidad, 'stock_antes' => $current, 'stock_despues' => $new, 'db_rows_affected' => $affected, 'connection' => $connName]);
						} catch (\Throwable $e) {
							Log::warning('Fallo actualizando tabla productos por entrega', ['sku' => $sku, 'error' => $e->getMessage()]);
						}
						// Además, intentar restar la cantidad de la tabla `inventarios` en la conexión mysql_third
						try {
							$remaining = $cantidad;
							$invConn = 'mysql_third';
							$invRows = DB::connection($invConn)->table('inventarios')
								->where('sku', $producto->sku)
								->where('estatus', 'disponible')
								->orderBy('id')
								->get();

							foreach ($invRows as $invRow) {
								if ($remaining <= 0) break;
								$stockInv = (int) $invRow->stock;
								if ($stockInv <= 0) continue;
								$toTake = min($stockInv, (int)$remaining);
								$newInvStock = $stockInv - $toTake;
								if ($newInvStock > 0) {
									DB::connection($invConn)->table('inventarios')->where('id', $invRow->id)->update(['stock' => $newInvStock]);
								} else {
									DB::connection($invConn)->table('inventarios')->where('id', $invRow->id)->delete();
										}
										Log::info('Inventario actualizado por entrega (bd3)', ['sku' => $producto->sku, 'inventario_id' => $invRow->id, 'restado' => $toTake, 'stock_antes' => $stockInv, 'stock_despues' => $newInvStock]);
										$remaining -= $toTake;
									}

									if ($remaining > 0) {
										Log::warning('Entrega excedió stock en inventarios (bd3)', ['sku' => $producto->sku, 'faltante' => $remaining]);
									}
								} catch (\Throwable $e) {
									Log::warning('No se pudo actualizar inventarios en bd3 para entrega', ['sku' => $producto->sku, 'error' => $e->getMessage()]);
								}
					} else {
						Log::warning('Producto no encontrado al intentar descontar stock en entrega', ['sku' => $sku, 'sku_norm' => $skuNorm]);
					}
				} catch (\Throwable $e) {
					Log::warning('No se pudo actualizar stock en productos para entrega', ['sku' => $sku, 'sku_norm' => $skuNorm, 'error' => $e->getMessage()]);
				}
			}

			DB::commit();

			// Obtener entrega para enviar correo
			$entrega = DB::table('entregas')->where('id', $entregaId)->first();

			// Disparar job de correo si hay email válido
			$incluirCorreosGestion = $request->boolean('enviar_a_gestion_correos');
			if (!empty($emailUsuario) && $emailUsuario !== 'sin-email@example.com') {
				try {
					EnviarCorreoEntrega::dispatchSync(
						$entrega,
						$items,
						$emailUsuario,
						$entregaData['comprobante_path'] ?? null,
						$incluirCorreosGestion,
						$entregaData['rol_entrega'] ?? null
					);
				} catch (\Exception $e) {
					Log::error('Error al enviar correo de entrega', ['error' => $e->getMessage(), 'entrega_id' => $entregaId]);
				}
			}

			// Si la petición espera JSON (AJAX), devolver mapa de stocks actualizados para que el cliente
			// pueda refrescar la tabla en la vista sin recargar toda la página.
			if ($request->wantsJson() || $request->ajax()) {
				try {
					// colectar SKUs afectados
					$skus = collect($items)->map(fn($it) => trim((string)($it['sku'] ?? '')))->filter()->unique()->values()->all();
					$updatedStocks = [];
					if (!empty($skus)) {
						$prodModel = new Producto();
						$prodConn = $prodModel->getConnectionName() ?: config('database.default');
						$rows = DB::connection($prodConn)->table($prodModel->getTable())->whereIn('sku', $skus)->select('sku', 'stock_produc')->get();
						foreach ($rows as $r) { $updatedStocks[(string)$r->sku] = (int) $r->stock_produc; }
					}
				} catch (\Throwable $e) {
					Log::warning('No se pudo obtener stocks actualizados tras entrega', ['error' => $e->getMessage()]);
					$updatedStocks = [];
				}

				return response()->json(['success' => true, 'message' => 'Entrega registrada correctamente', 'entrega_id' => $entregaId, 'updatedStocks' => $updatedStocks], 200);
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
		$payload = $request->getContent();
		$skus = null;
		try {
			$data = json_decode($payload, true);
			if (is_array($data) && isset($data['skus']) && is_array($data['skus'])) {
				$skus = $data['skus'];
			} elseif ($request->has('skus') && is_array($request->input('skus'))) {
				$skus = $request->input('skus');
			}
		} catch (\Throwable $e) {
			$skus = null;
		}

		if (empty($skus) || !is_array($skus)) {
			return response()->json([], 200);
		}

		try {
			$prodModel = new Producto();
			$conn = $prodModel->getConnectionName() ?: config('database.default');
			$table = $prodModel->getTable();
			$rows = DB::connection($conn)
				->table($table)
				->whereIn('sku', $skus)
				->select('sku', 'name_produc')
				->get();

			$result = $rows->map(function($r){
				return ['sku' => (string)($r->sku ?? ''), 'name_produc' => (string)($r->name_produc ?? '')];
			})->values();

			return response()->json($result, 200);
		} catch (\Throwable $e) {
			Log::warning('obtenerNombresProductos failed', ['error' => $e->getMessage()]);
			// Return empty array to the client so frontend can fallback to SKU labels
			return response()->json([], 200);
		}
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
