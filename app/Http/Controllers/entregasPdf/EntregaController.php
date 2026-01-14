<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\SubArea;
use App\Models\Entrega;
use App\Models\Producto;
use App\Models\Usuarios;
use App\Models\ElementoXEntrega;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Http\Controllers\entregasPdf\FormularioEntregasController;
use App\Http\Controllers\entregasPdf\HistorialEntregaController;

class EntregaController extends Controller
{
    // <-- Proxy: listar historial (delegar al nuevo HistorialEntregaController) -->
    public function index(Request $request)
    {
        return (new HistorialEntregaController())->index($request);
    }

    // <-- Proxy: mostrar detalle de una entrega -->
    public function show($entrega)
    {
        return (new HistorialEntregaController())->show($entrega);
    }

    // <-- Proxy: formulario de entregas (delegar al nuevo FormularioEntregasController) -->
    public function create()
    {
        return (new FormularioEntregasController())->create();
    }

    // <-- Proxy: procesar envío del formulario -->
    public function store(Request $request)
    {
        return (new FormularioEntregasController())->store($request);
    }

    // <-- Proxy: historial unificado -->
    public function historialUnificado(Request $request)
    {
        return (new HistorialEntregaController())->historialUnificado($request);
    }

    /**
     * API: Descargar PDF individual de una entrega o recepción
     */
    public function descargarPDFIndividual(Request $request)
    {
        // Registrar acceso para debugging
        Log::info('Hit /historial/pdf', ['query' => $request->all(), 'url' => $request->fullUrl()]);

        $tipo = $request->query('tipo');
        $id = $request->query('id');

        if (!$tipo || !$id) {
            abort(404);
        }

        if ($tipo === 'entrega') {
            $comprobante = DB::table('entregas')->where('id', $id)->value('comprobante_path');
        } elseif ($tipo === 'recepcion') {
            $comprobante = DB::table('recepciones')->where('id', $id)->value('comprobante_path');
        } else {
            abort(404);
        }

        Log::info('Comprobante consultado desde DB', ['tipo' => $tipo, 'id' => $id, 'comprobante' => $comprobante]);

        if (!$comprobante) {
            Log::warning('Comprobante no encontrado en DB', ['tipo' => $tipo, 'id' => $id]);
            abort(404);
        }

        // Normalizar ruta relativa esperada (ej: comprobantes_entregas/archivo.pdf)
        $relativePath = ltrim(preg_replace('#^(/storage/|storage/app/|storage/app/public/)#', '', $comprobante), '/');
        $fullPath = storage_path('app/' . $relativePath);

        // Datos de diagnóstico
        $disk = Storage::disk('local');
        $diskExists = $disk->exists($relativePath);
        $fileExists = file_exists($fullPath);
        $isReadable = is_readable($fullPath);

        Log::info('Diagnóstico comprobante', [
            'relativePath' => $relativePath,
            'fullPath' => $fullPath,
            'diskExists' => $diskExists,
            'fileExists' => $fileExists,
            'isReadable' => $isReadable,
        ]);

        try {
            if (!$diskExists || !$fileExists) {
                Log::warning('Comprobante no encontrado en storage (diagnóstico) ', ['relativePath' => $relativePath, 'fullPath' => $fullPath]);
                abort(404);
            }

            // Forzar nombre de descarga claro
            $downloadName = basename($fullPath);

            // Forzar descarga usando BinaryFileResponse con Content-Disposition attachment
            $response = new BinaryFileResponse($fullPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
            $response->headers->set('Content-Type', 'application/octet-stream');
            return $response;
        } catch (\Throwable $e) {
            Log::error('Error sirviendo comprobante', ['error' => $e->getMessage(), 'relativePath' => $relativePath ?? null, 'fullPath' => $fullPath ?? null]);
            // Intentar servir con streamDownload como fallback forzando descarga
            try {
                if (isset($fullPath) && file_exists($fullPath)) {
                    $downloadName = basename($fullPath);
                    return response()->streamDownload(function() use ($fullPath) {
                        readfile($fullPath);
                    }, $downloadName, ['Content-Type' => 'application/octet-stream']);
                }
            } catch (\Throwable $e2) {
                Log::error('Fallback streamDownload failed', ['error' => $e2->getMessage()]);
            }

            abort(500);
        }
    }

    /**
     * API: Descargar CSV masivo con los registros (entregas/recepciones)
     */
    public function descargarPDFMasivo(Request $request)
    {
        try {
            $tipoRegistro = $request->query('tipo_registro');
            $operacionId = $request->query('operacion_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');

            // Validaciones simples
            if (!in_array($tipoRegistro, ['entrega', 'recepcion', 'todos'])) {
                return response()->json(['error' => 'Tipo de registro inválido'], 400);
            }
            if (empty($fechaInicio) || empty($fechaFin)) {
                return response()->json(['error' => 'Fecha inicio y fin son requeridas'], 400);
            }

            $registros = collect();

            if (in_array($tipoRegistro, ['entrega', 'todos'])) {
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
                        'entregas.recibido',
                        'entregas.comprobante_path'
                    ])
                    ->whereNull('entregas.deleted_at')
                    ->whereBetween('entregas.created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

                if ($operacionId) {
                    $queryEntregas->where('entregas.sub_area_id', $operacionId);
                }

                $registros = $registros->merge($queryEntregas->get());
            }

            if (in_array($tipoRegistro, ['recepcion', 'todos'])) {
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
                        'recepciones.entregado as recibido',
                        'recepciones.comprobante_path'
                    ])
                    ->whereNull('recepciones.deleted_at')
                    ->whereBetween('recepciones.created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

                if ($operacionId) {
                    $queryRecepciones->where('recepciones.operacion_id', $operacionId);
                }

                $registros = $registros->merge($queryRecepciones->get());
            }

            if ($registros->isEmpty()) {
                return response()->json(['error' => 'No se encontraron registros'], 404);
            }

            // Construir filas con elementos ya concatenados para la vista
            $rows = collect();
            foreach ($registros as $registro) {
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

                $elementosTexto = $elementos->map(fn($e) => "{$e->sku}({$e->cantidad})")->implode('; ');

                $rows->push([
                    'id' => $registro->id,
                    'registro_tipo' => $registro->registro_tipo,
                    'tipo' => $registro->tipo,
                    'fecha' => isset($registro->created_at) ? (string)$registro->created_at : '',
                    'numero_documento' => $registro->numero_documento ?? '',
                    'tipo_documento' => $registro->tipo_documento ?? '',
                    'nombres' => $registro->nombres ?? '',
                    'apellidos' => $registro->apellidos ?? '',
                    'operacion' => $registro->operacion ?? '',
                    'recibido' => (isset($registro->recibido) ? ($registro->recibido ? '1' : '0') : ''),
                    'comprobante_path' => $registro->comprobante_path ?? '',
                    'elementos' => $elementosTexto,
                ]);
            }

            $fileName = "registros_" . now()->format('Y-m-d_His') . ".xlsx";

            // Si la librería Maatwebsite/Excel está disponible, usarla; si no, caer a CSV.
            if (class_exists('Maatwebsite\\Excel\\Facades\\Excel') && class_exists(\App\Exports\TempRegistrosExport::class)) {
                $export = new \App\Exports\TempRegistrosExport($rows->toArray());
                return call_user_func(['Maatwebsite\\Excel\\Facades\\Excel', 'download'], $export, $fileName);
            }
 
             // Fallback: generar CSV compatible con Excel
            $fileNameCsv = pathinfo($fileName, PATHINFO_FILENAME) . '.csv';
            $handle = fopen('php://temp', 'r+');
            // Encabezados
            fputcsv($handle, ['id','registro_tipo','tipo','fecha','numero_documento','tipo_documento','nombres','apellidos','operacion','recibido','comprobante_path','elementos']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['id'] ?? '',
                    $r['registro_tipo'] ?? '',
                    $r['tipo'] ?? '',
                    $r['fecha'] ?? '',
                    $r['numero_documento'] ?? '',
                    $r['tipo_documento'] ?? '',
                    $r['nombres'] ?? '',
                    $r['apellidos'] ?? '',
                    $r['operacion'] ?? '',
                    $r['recibido'] ?? '',
                    $r['comprobante_path'] ?? '',
                    $r['elementos'] ?? ''
                ]);
            }
            rewind($handle);
            $stream = function() use ($handle) {
                while (!feof($handle)) {
                    echo fgets($handle);
                }
                fclose($handle);
            };
            return response()->streamDownload($stream, $fileNameCsv, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileNameCsv . '"',
            ]);
 
         } catch (\Exception $e) {
             Log::error('Error generando Excel masivo', [
                 'error' => $e->getMessage(),
                 'params' => $request->query()
             ]);
             return response()->json(['error' => 'Error al generar Excel: ' . $e->getMessage()], 500);
         }
     }

     /**
     * Actualizar inventario según tipo de entrega
     */
    private function actualizarInventarioEntrega($tipoEntrega, $items, $entregaId)
    {
        try {
            foreach ($items as $item) {
                if (empty($item['sku'])) continue;

                $sku = $item['sku'];
                $cantidad = (int) ($item['cantidad'] ?? 1);

                // Obtener ubicación por defecto
                $ubicacionId = $this->getOrCreateDefaultUbicacion();

                switch ($tipoEntrega) {
                    case 'prestamo':
                        // Restar de disponible y sumar a prestado
                        $this->transferirInventario($sku, $cantidad, 'disponible', 'prestado', $ubicacionId);
                        Log::info('Inventario actualizado: préstamo', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad,
                            'de' => 'disponible',
                            'a' => 'prestado'
                        ]);
                        break;

                    case 'primera vez':
                    case 'periodica':
                        // Restar de disponible (entrega definitiva)
                        $this->restarInventario($sku, $cantidad, 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: entrega definitiva', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad,
                            'tipo' => $tipoEntrega
                        ]);
                        break;

                    case 'cambio':
                        // Restar de disponible (se entrega artículo nuevo)
                        $this->restarInventario($sku, $cantidad, 'disponible', $ubicacionId);
                        Log::info('Inventario actualizado: cambio', [
                            'entrega_id' => $entregaId,
                            'sku' => $sku,
                            'cantidad' => $cantidad
                        ]);
                        break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error actualizando inventario en entrega', [
                'error' => $e->getMessage(),
                'entrega_id' => $entregaId,
                'tipo' => $tipoEntrega
            ]);
            throw $e;
        }
    }

    /**
     * Transferir inventario entre estados
     */
    private function transferirInventario($sku, $cantidad, $estatusOrigen, $estatusDestino, $ubicacionId)
    {
        // Buscar inventario origen
        $inventarioOrigen = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatusOrigen)
            ->first();

        if (!$inventarioOrigen || (int)$inventarioOrigen->stock < $cantidad) {
            throw new \Exception("No hay suficiente stock disponible para SKU: {$sku}");
        }

        // Restar del origen
        $nuevoStockOrigen = (int)$inventarioOrigen->stock - $cantidad;
        if ($nuevoStockOrigen > 0) {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventarioOrigen->id)
                ->update(['stock' => $nuevoStockOrigen]);
        } else {
            // Si llega a 0, eliminar la fila
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventarioOrigen->id)
                ->delete();
        }

        // Buscar o crear inventario destino
        $inventarioDestino = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatusDestino)
            ->first();

        if ($inventarioDestino) {
            // Sumar al destino existente
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventarioDestino->id)
                ->update(['stock' => (int)$inventarioDestino->stock + $cantidad]);
        } else {
            // Crear nuevo registro de destino
            DB::connection('mysql_third')
                ->table('inventarios')
                ->insert([
                    'sku' => $sku,
                    'stock' => $cantidad,
                    'estatus' => $estatusDestino,
                    'ubicaciones_id' => $ubicacionId
                ]);
        }
    }

    /**
     * Restar inventario de un estado específico
     */
    private function restarInventario($sku, $cantidad, $estatus, $ubicacionId)
    {
        $inventario = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatus)
            ->first();

        if (!$inventario || (int)$inventario->stock < $cantidad) {
            throw new \Exception("No hay suficiente stock {$estatus} para SKU: {$sku}");
        }

        $nuevoStock = (int)$inventario->stock - $cantidad;
        if ($nuevoStock > 0) {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->update(['stock' => $nuevoStock]);
        } else {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->delete();
        }
    }

    /**
     * Sumar inventario a un estado específico
     */
    private function sumarInventario($sku, $cantidad, $estatus, $ubicacionId)
    {
        $inventario = DB::connection('mysql_third')
            ->table('inventarios')
            ->where('sku', $sku)
            ->where('estatus', $estatus)
            ->first();

        if ($inventario) {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->where('id', $inventario->id)
                ->update(['stock' => (int)$inventario->stock + $cantidad]);
        } else {
            DB::connection('mysql_third')
                ->table('inventarios')
                ->insert([
                    'sku' => $sku,
                    'stock' => $cantidad,
                    'estatus' => $estatus,
                    'ubicaciones_id' => $ubicacionId
                ]);
        }
    }

    /**
     * Obtener o crear ubicación por defecto
     */
    private function getOrCreateDefaultUbicacion()
    {
        $ubicacion = DB::connection('mysql_third')
            ->table('ubicaciones')
            ->where('bodega', 'General')
            ->where('ubicacion', 'Almacén Principal')
            ->first();

        if ($ubicacion) {
            return (int)$ubicacion->id;
        }

        return (int) DB::connection('mysql_third')
            ->table('ubicaciones')
            ->insertGetId([
                'bodega' => 'General',
                'ubicacion' => 'Almacén Principal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
    }
}