<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;
use App\Models\Entrega;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HistorialEntregaController extends Controller
{
    // Mostrar historial de entregas (página)
    public function index(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();

        $query = Entrega::with(['operacion','usuario','elementos'])->orderBy('created_at', 'desc');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->whereHas('usuario', function($uq) use ($q){
                $uq->where('nombres', 'like', "%{$q}%")
                   ->orWhere('apellidos', 'like', "%{$q}%")
                   ->orWhere('numero_documento', 'like', "%{$q}%");
            });
        }

        if ($request->filled('operacion')) {
            $query->where('sub_area_id', $request->input('operacion'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? $perPage : 10;
        $entregas = $query->paginate($perPage)->withQueryString();

        return view('formularioEntregas.HistorialEntregas', compact('entregas','operations'));
    }

    // Mostrar detalle de una entrega (para route entregas.show)
    public function show($entrega)
    {
        try {
            $entrega = Entrega::with(['operacion','usuario','elementos'])->findOrFail($entrega);
            return view('formularioEntregas.entregaShow', compact('entrega'));
        } catch (\Throwable $e) {
            Log::warning('HistorialEntregaController@show error', ['id' => $entrega, 'msg' => $e->getMessage()]);
            abort(404);
        }
    }

    // Agregar historialUnificado (combina entregas y recepciones)
    public function historialUnificado(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();
        
        // Obtener entregas
        $queryEntregas = DB::table('entregas')
            ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
            ->select([
                'entregas.id',
                'entregas.created_at',
                'entregas.tipo_entrega as tipo',
                DB::raw("'entrega' as registro_tipo"),
                DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'entregas.recibido'
            ])
            ->whereNull('entregas.deleted_at');

        // Obtener recepciones
        $queryRecepciones = DB::table('recepciones')
            ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
            ->select([
                'recepciones.id',
                'recepciones.created_at',
                'recepciones.tipo_recepcion as tipo',
                DB::raw("'recepcion' as registro_tipo"),
                DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'recepciones.entregado as recibido'
            ])
            ->whereNull('recepciones.deleted_at');

        // Aplicar filtros
        if ($request->filled('q')) {
            $q = $request->input('q');
            $queryEntregas->where(function($query) use ($q) {
                $query->where('entregas.numero_documento', 'like', "%{$q}%")
                    ->orWhere('entregas.nombres', 'like', "%{$q}%")
                    ->orWhere('entregas.apellidos', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
            $queryRecepciones->where(function($query) use ($q) {
                $query->where('recepciones.numero_documento', 'like', "%{$q}%")
                    ->orWhere('recepciones.nombres', 'like', "%{$q}%")
                    ->orWhere('recepciones.apellidos', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                    ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
        }

        if ($request->filled('operacion')) {
            $queryEntregas->where('entregas.sub_area_id', $request->input('operacion'));
            $queryRecepciones->where('recepciones.operacion_id', $request->input('operacion'));
        }

        if ($request->filled('tipo_registro')) {
            $tipoRegistro = $request->input('tipo_registro');
            if ($tipoRegistro === 'entrega') {
                $queryRecepciones = null;
            } elseif ($tipoRegistro === 'recepcion') {
                $queryEntregas = null;
            }
        }

        // Unir ambas consultas
        $registros = collect();
        if ($queryEntregas) {
            $registros = $registros->merge($queryEntregas->get());
        }
        if ($queryRecepciones) {
            $registros = $registros->merge($queryRecepciones->get());
        }

        // Ordenar por fecha descendente
        $registros = $registros->sortByDesc('created_at')->values();

        // Paginar manualmente (respetar per_page)
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? $perPage : 10;
        $currentPage = (int) $request->input('page', 1);
        $total = $registros->count();
        $slice = $registros->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedRegistros = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Cargar elementos para cada registro
        $collectionWithElements = $paginatedRegistros->getCollection()->map(function($registro) {
            if ($registro->registro_tipo === 'entrega') {
                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $registro->id)
                    ->select(['sku', 'cantidad'])
                    ->get();
            } else {
                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $registro->id)
                    ->select(['sku', 'cantidad'])
                    ->get();
            }
            $registro->elementos = $elementos;
            return $registro;
        });

        $paginatedRegistros->setCollection($collectionWithElements);

        return view('historico.historialUnificado', ['paginatedRegistros' => $paginatedRegistros, 'operations' => $operations]);
    }
}
