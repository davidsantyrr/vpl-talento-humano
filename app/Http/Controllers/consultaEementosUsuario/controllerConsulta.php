<?php

namespace App\Http\Controllers\consultaEementosUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

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
            if ($usuario) $usuario_info = $usuario;

            Log::info('consultaDocumento: búsqueda', ['numero_documento' => $numeroDocumento, 'usuario_encontrado' => (bool) $usuario]);

            // Preparar queries sin asumir columnas inexistentes
            $entregasQuery = \App\Models\Entrega::with('elementos')->orderBy('created_at','desc');
            $recepcionesQuery = \App\Models\Recepcion::with('elementos')->orderBy('created_at','desc');

            $hasEntregasUi = Schema::hasColumn('entregas', 'usuarios_id');
            $hasEntregasUe = Schema::hasColumn('entregas', 'usuarios_entregas_id');
            $hasRecepUi = Schema::hasColumn('recepciones', 'usuarios_id');
            $hasRecepUe = Schema::hasColumn('recepciones', 'usuarios_entregas_id');

            if ($usuario) {
                $uid = $usuario->id;
                $doc = $usuario->numero_documento;

                // Entregas: usuarios_id OR usuarios_entregas_id (si existe) OR numero_documento
                $entregasQuery->where(function($q) use ($uid, $doc, $hasEntregasUi, $hasEntregasUe) {
                    if ($hasEntregasUi) $q->orWhere('usuarios_id', $uid);
                    if ($hasEntregasUe) $q->orWhere('usuarios_entregas_id', $uid);
                    if (!empty($doc)) $q->orWhere('numero_documento', $doc);
                });

                // Recepciones: usuarios_id OR usuarios_entregas_id (si existe) OR numero_documento
                $recepcionesQuery->where(function($q) use ($uid, $doc, $hasRecepUi, $hasRecepUe) {
                    if ($hasRecepUi) $q->orWhere('usuarios_id', $uid);
                    if ($hasRecepUe) $q->orWhere('usuarios_entregas_id', $uid);
                    if (!empty($doc)) $q->orWhere('numero_documento', $doc);
                });
            } else {
                // búsqueda por documento (cuando no hay registro en usuarios)
                $entregasQuery->where('numero_documento', $numeroDocumento);
                $recepcionesQuery->where('numero_documento', $numeroDocumento);
            }

            $entregas = $entregasQuery->get();
            $recepciones = $recepcionesQuery->get();

            Log::info('consultaDocumento: conteos', ['entregas' => $entregas->count(), 'recepciones' => $recepciones->count()]);

            // Si no existe registro en usuarios_entregas, intentar obtener nombres desde entregas/recepciones
            $fallbackNombre = null;
            if (!$usuario) {
                $primer = $entregas->first() ?? $recepciones->first();
                if ($primer && (isset($primer->nombres) || isset($primer->apellidos))) {
                    $fallbackNombre = trim(($primer->nombres ?? '') . ' ' . ($primer->apellidos ?? ''));
                }
            }

            // Agrupar por SKU/elemento
            $skus = collect();

            foreach ($entregas as $e) {
                $fechaEntrega = $e->fecha ?? $e->created_at;
                foreach ($e->elementos as $el) {
                    $sku = (string) ($el->sku ?? '');
                    if ($elementoFiltro && stripos($sku, $elementoFiltro) === false) continue;
                    $skus->push([
                        'sku' => $sku,
                        'tipo' => 'entrega',
                        'cantidad' => (int) ($el->cantidad ?? 1),
                        'fecha' => $fechaEntrega instanceof Carbon ? $fechaEntrega : Carbon::parse($fechaEntrega ?? $e->created_at),
                    ]);
                }
            }
            foreach ($recepciones as $r) {
                $fechaRecep = $r->fecha ?? $r->created_at;
                foreach ($r->elementos as $el) {
                    $sku = (string) ($el->sku ?? '');
                    if ($elementoFiltro && stripos($sku, $elementoFiltro) === false) continue;
                    $skus->push([
                        'sku' => $sku,
                        'tipo' => 'recepcion',
                        'cantidad' => (int) ($el->cantidad ?? 1),
                        'fecha' => $fechaRecep instanceof Carbon ? $fechaRecep : Carbon::parse($fechaRecep ?? $r->created_at),
                    ]);
                }
            }

            $grouped = $skus->groupBy('sku');

            foreach ($grouped as $sku => $items) {
                $totalEntregado = $items->where('tipo', 'entrega')->sum(fn($it)=> (int)$it['cantidad']);
                $totalRecepcionado = $items->where('tipo', 'recepcion')->sum(fn($it)=> (int)$it['cantidad']);

                $ultimaEntregaRaw = $items->where('tipo','entrega')->pluck('fecha')->filter()->max();
                $ultimaEntrega = $ultimaEntregaRaw ? ($ultimaEntregaRaw instanceof Carbon ? $ultimaEntregaRaw : Carbon::parse($ultimaEntregaRaw)) : null;

                $ultimaRecepcionRaw = $items->where('tipo','recepcion')->pluck('fecha')->filter()->max();
                $ultimaRecepcion = $ultimaRecepcionRaw ? ($ultimaRecepcionRaw instanceof Carbon ? $ultimaRecepcionRaw : Carbon::parse($ultimaRecepcionRaw)) : null;

                $usuarioDisplay = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : ($fallbackNombre ?: $numeroDocumento);

                // intentar obtener nombre del producto por SKU (modelo Producto o Articulos)
                $producto = null;
                try { $producto = \App\Models\Producto::where('sku', $sku)->first(); } catch (\Exception $e) { $producto = null; }
                if (!$producto) { $producto = \App\Models\Articulos::where('sku', $sku)->first(); }

                $resultados->push((object)[
                    'usuario' => $usuarioDisplay,
                    'elemento' => $sku,
                    'elemento_nombre' => $producto ? ($producto->name_produc ?? ($producto->nombre ?? null)) : null,
                    'ultima_entrega' => $ultimaEntrega ? $ultimaEntrega->toDateTimeString() : null,
                    'ultima_recepcion' => $ultimaRecepcion ? $ultimaRecepcion->toDateTimeString() : null,
                    'cantidad' => ($totalEntregado - $totalRecepcionado),
                    'proxima_entrega' => null,
                ]);
            }
        }

        return view('consultaElementoUsuario.consulta', compact('resultados', 'usuario_info'));
    }
}