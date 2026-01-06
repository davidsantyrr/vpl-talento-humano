<?php

namespace App\Http\Controllers\consultaEementosUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class controllerConsulta extends Controller
{
    public function index(Request $request)
    {
        $numeroDocumento = $request->input('usuario');
        $elementoFiltro = $request->input('elemento');

        $resultados = collect();

        $usuario_info = null;

        if ($numeroDocumento) {
            // Buscar usuario por número de documento
            $usuario = \App\Models\Usuarios::where('numero_documento', $numeroDocumento)->first();

            if ($usuario) {
                $usuario_info = $usuario;
            }

            // Log para depuración: indicar si se encontró usuario
            Log::info('consultaDocumento: búsqueda', ['numero_documento' => $numeroDocumento, 'usuario_encontrado' => (bool) $usuario]);

            // Obtener entregas y recepciones relacionadas (por usuario_id si existe, o por número de documento)
            $entregasQuery = \App\Models\Entrega::query();
            $recepcionesQuery = \App\Models\Recepcion::query();

            if ($usuario) {
                $entregasQuery->where('usuarios_id', $usuario->id);
                $recepcionesQuery->where('usuarios_id', $usuario->id);
            } else {
                $entregasQuery->where('numero_documento', $numeroDocumento);
                $recepcionesQuery->where('numero_documento', $numeroDocumento);
            }

            $entregas = $entregasQuery->with('elementos')->get();
            $recepciones = $recepcionesQuery->with('elementos')->get();

            // Log con conteos
            Log::info('consultaDocumento: conteos', ['entregas' => $entregas->count(), 'recepciones' => $recepciones->count()]);

            // Si no existe registro en usuarios_entregas, intentar obtener nombres desde entregas/recepciones
            $fallbackNombre = null;
            if (! $usuario) {
                $primer = $entregas->first() ?? $recepciones->first();
                if ($primer && (isset($primer->nombres) || isset($primer->apellidos))) {
                    $fallbackNombre = trim(($primer->nombres ?? '') . ' ' . ($primer->apellidos ?? ''));
                }
            }

            // Agrupar por SKU/elemento
            $skus = collect();

            foreach ($entregas as $e) {
                foreach ($e->elementos as $el) {
                    if ($elementoFiltro && stripos($el->sku, $elementoFiltro) === false) continue;
                    $skus->push(["sku" => $el->sku, "tipo" => 'entrega', 'cantidad' => $el->cantidad, 'fecha' => $e->created_at]);
                }
            }
            foreach ($recepciones as $r) {
                foreach ($r->elementos as $el) {
                    if ($elementoFiltro && stripos($el->sku, $elementoFiltro) === false) continue;
                    $skus->push(["sku" => $el->sku, "tipo" => 'recepcion', 'cantidad' => $el->cantidad, 'fecha' => $r->created_at]);
                }
            }

            $grouped = $skus->groupBy('sku');

            foreach ($grouped as $sku => $items) {
                $totalEntregado = $items->where('tipo', 'entrega')->sum('cantidad');
                $totalRecepcionado = $items->where('tipo', 'recepcion')->sum('cantidad');
                $ultimaEntrega = $items->where('tipo', 'entrega')->max('fecha');
                $ultimaRecepcion = $items->where('tipo', 'recepcion')->max('fecha');

                $usuarioDisplay = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : ($fallbackNombre ?: $numeroDocumento);

                // intentar obtener nombre del producto por SKU (modelo Producto o Articulos)
                $producto = null;
                try {
                    $producto = \App\Models\Producto::find($sku);
                } catch (\Exception $e) {
                    $producto = null;
                }
                if (! $producto) {
                    $producto = \App\Models\Articulos::where('sku', $sku)->first();
                }

                $resultado = (object) [
                    'usuario' => $usuarioDisplay,
                    'elemento' => $sku,
                    'elemento_nombre' => $producto ? ($producto->name_produc ?? ($producto->nombre ?? null)) : null,
                    'ultima_entrega' => $ultimaEntrega ? $ultimaEntrega->toDateTimeString() : null,
                    'ultima_recepcion' => $ultimaRecepcion ? $ultimaRecepcion->toDateTimeString() : null,
                    'cantidad' => $totalEntregado - $totalRecepcionado,
                    'proxima_entrega' => null,
                ];

                $resultados->push($resultado);
            }
        }

        return view('consultaElementoUsuario.consulta', compact('resultados', 'usuario_info'));
    }
    
}