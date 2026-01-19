<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubArea;
use App\Models\Entrega;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Descargar comprobante individual (entrega o recepción) renderizado con la plantilla PDF.
     * Soporta query params: tipo=entrega|recepcion, id=<int>
     */
    public function descargarPDFIndividual(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required','in:entrega,recepcion'],
            'id' => ['required','integer']
        ]);

        $tipo = $data['tipo'];
        $id = (int) $data['id'];

        try {
            if ($tipo === 'entrega') {
                $registro = DB::table('entregas')
                    ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
                    ->select([
                        'entregas.id',
                        'entregas.created_at',
                        'entregas.tipo_entrega as tipo',
                        'entregas.comprobante_path as comprobante_path',
                        DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'entregas.recibido'
                    ])
                    ->where('entregas.id', $id)
                    ->first();

                if (!$registro) { abort(404, 'Entrega no encontrada'); }

                $elementos = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $id)
                    ->select(['sku', 'cantidad', DB::raw('NULL as name_produc')])
                    ->get();
            } else { // recepcion
                $registro = DB::table('recepciones')
                    ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
                    ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
                    ->select([
                        'recepciones.id',
                        'recepciones.created_at',
                        'recepciones.tipo_recepcion as tipo',
                        'recepciones.comprobante_path as comprobante_path',
                        DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                        DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                        DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                        DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                        'sub_areas.operationName as operacion',
                        'recepciones.entregado as recibido'
                    ])
                    ->where('recepciones.id', $id)
                    ->first();

                if (!$registro) { abort(404, 'Recepción no encontrada'); }

                $elementos = DB::table('elemento_x_recepcion')
                    ->where('recepcion_id', $id)
                    ->select(['sku', 'cantidad', DB::raw('NULL as name_produc')])
                    ->get();
            }

            // Si existe comprobante guardado, servir ese archivo (incluye firma si se generó con ella)
            if (!empty($registro->comprobante_path) && !$request->boolean('force')) {
                $relative = ltrim(preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $registro->comprobante_path), '/');
                $candidates = [
                    storage_path('app/' . $relative),
                    storage_path('app/public/' . $relative),
                ];
                foreach ($candidates as $p) {
                    if (is_string($p) && file_exists($p)) {
                        $downloadName = basename($p);
                        return response()->download($p, $downloadName, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"'
                        ]);
                    }
                }
            }

            // Si se pasa ?debug=1, devolver la vista HTML para diagnóstico
            if ($request->boolean('debug')) {
                return view('pdf.comprobante', [
                    'tipo' => $tipo,
                    'registro' => (object) $registro,
                    'elementos' => $elementos,
                    'firma' => []
                ]);
            }

            $pdf = Pdf::loadView('pdf.comprobante', [
                'tipo' => $tipo,
                'registro' => (object) $registro,
                'elementos' => $elementos,
                // La plantilla actual no requiere imagen de firma
                'firma' => []
            ])->setPaper('A4', 'portrait');

            $numeroDoc = $registro->numero_documento ?? ($registro->nombres ?? 'registro');
            $numeroDoc = preg_replace('/[^A-Za-z0-9\-_]/', '_', substr($numeroDoc, 0, 40));
            $filename = strtoupper($tipo) . '_' . $numeroDoc . '_' . now()->format('Ymd_His') . '.pdf';

            // Guardar temporalmente y forzar descarga como adjunto
            $tmpDir = storage_path('app/tmp_downloads');
            if (!file_exists($tmpDir)) { mkdir($tmpDir, 0755, true); }
            $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($fullPath, $pdf->output());

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error descargando PDF individual', ['tipo' => $tipo, 'id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'No se pudo generar el comprobante: ' . $e->getMessage());
        }
    }

    /**
     * Descargar comprobantes previamente guardados en storage/app/{dir}/{file}
     */
    public function downloadComprobante($dir, $file)
    {
        $path = storage_path('app/' . $dir . '/' . $file);
        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }
        return response()->download($path);
    }

    /**
     * Placeholder: descarga masiva. Pendiente de implementar según requisitos.
     */
    public function descargarPDFMasivo(Request $request)
    {
        return back()->with('error', 'Descarga masiva aún no implementada.');
    }
}
