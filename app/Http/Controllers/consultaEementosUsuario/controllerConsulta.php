<?php

namespace App\Http\Controllers\consultaEementosUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
    
    /**
     * Obtener entregas anteriores de un usuario para vista previa de PDFs
     */
    public function entregasAnteriores(Request $request)
    {
        $numeroDocumento = $request->input('documento');
        $fechaFiltro = $request->input('fecha');
        
        if (!$numeroDocumento) {
            return response()->json(['entregas' => [], 'message' => 'Documento requerido'], 400);
        }
        
        try {
            $query = \App\Models\Entrega::with('elementos')
                ->where('numero_documento', $numeroDocumento)
                ->orderBy('created_at', 'desc');
            
            // Filtrar por fecha si se proporciona
            if ($fechaFiltro) {
                $query->whereDate('created_at', $fechaFiltro);
            }
            
            $entregas = $query->get();
            
            $dir = storage_path('app/comprobantes_entregas');
            
            $resultado = [];
            
            foreach ($entregas as $entrega) {
                $pdfPath = null;
                $pdfUrl = null;
                
                // PRIORIDAD 1: Usar el comprobante_path guardado en la entrega
                if (!empty($entrega->comprobante_path)) {
                    $filename = basename($entrega->comprobante_path);
                    $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
                    
                    if (file_exists($fullPath)) {
                        $pdfPath = $entrega->comprobante_path;
                        $pdfUrl = route('comprobantes.ver', ['filename' => $filename]);
                    }
                }
                
                // Si no tiene comprobante_path guardado, el PDF no está disponible
                // (las entregas antiguas sin PDF generado no se pueden mostrar con firma)
                
                // Obtener nombres de productos
                $elementosConNombre = [];
                foreach ($entrega->elementos as $elem) {
                    $nombre = '';
                    try {
                        $prod = \App\Models\Producto::where('sku', $elem->sku)->first();
                        if ($prod) $nombre = $prod->name_produc ?? '';
                    } catch (\Throwable $e) {}
                    
                    $elementosConNombre[] = [
                        'sku' => $elem->sku,
                        'cantidad' => $elem->cantidad ?? 1,
                        'nombre' => $nombre
                    ];
                }
                
                $resultado[] = [
                    'id' => $entrega->id,
                    'fecha' => $entrega->created_at->format('Y-m-d H:i:s'),
                    'fecha_corta' => $entrega->created_at->format('d/m/Y'),
                    'tipo_entrega' => $entrega->tipo_entrega ?? 'N/A',
                    'entrega_user' => $entrega->entrega_user ?? 'Sistema',
                    'elementos' => $elementosConNombre,
                    'pdf_path' => $pdfPath,
                    'pdf_url' => $pdfUrl
                ];
            }
            
            return response()->json(['entregas' => $resultado]);
            
        } catch (\Throwable $e) {
            Log::error('Error obteniendo entregas anteriores', ['error' => $e->getMessage()]);
            return response()->json(['entregas' => [], 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Servir PDF para vista previa (inline)
     */
    public function verPdf($filename)
    {
        // Buscar en comprobantes_entregas
        $pathEntregas = storage_path('app/comprobantes_entregas/' . $filename);
        if (file_exists($pathEntregas)) {
            return response()->file($pathEntregas, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"'
            ]);
        }
        
        // Buscar en comprobantes_recepciones
        $pathRecepciones = storage_path('app/comprobantes_recepciones/' . $filename);
        if (file_exists($pathRecepciones)) {
            return response()->file($pathRecepciones, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"'
            ]);
        }
        
        // Si no existe, buscar la entrega por comprobante_path para intentar encontrar el archivo
        $entrega = \App\Models\Entrega::where('comprobante_path', 'LIKE', '%' . $filename)->first();
        if ($entrega && !empty($entrega->comprobante_path)) {
            $altPath = storage_path('app/' . ltrim($entrega->comprobante_path, '/'));
            if (file_exists($altPath)) {
                return response()->file($altPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"'
                ]);
            }
        }
        
        Log::warning('verPdf: Archivo no encontrado', [
            'filename' => $filename,
            'path_entregas' => $pathEntregas,
            'path_recepciones' => $pathRecepciones
        ]);
        
        abort(404, 'PDF no encontrado');
    }
    
    /**
     * Endpoint de diagnóstico para verificar estado del storage
     */
    public function diagnosticoStorage()
    {
        $basePath = storage_path('app');
        $entregasPath = storage_path('app/comprobantes_entregas');
        $recepcionesPath = storage_path('app/comprobantes_recepciones');
        
        $info = [
            'storage_path' => storage_path(),
            'app_path' => $basePath,
            'base_exists' => file_exists($basePath),
            'base_writable' => is_writable($basePath),
            'entregas_path' => $entregasPath,
            'entregas_exists' => file_exists($entregasPath),
            'recepciones_path' => $recepcionesPath,
            'recepciones_exists' => file_exists($recepcionesPath),
            'entregas_files' => [],
            'recepciones_files' => [],
        ];
        
        // Listar archivos en comprobantes_entregas
        if (file_exists($entregasPath) && is_dir($entregasPath)) {
            $files = scandir($entregasPath);
            $info['entregas_files'] = array_values(array_filter($files, fn($f) => $f !== '.' && $f !== '..'));
            $info['entregas_count'] = count($info['entregas_files']);
        }
        
        // Listar archivos en comprobantes_recepciones
        if (file_exists($recepcionesPath) && is_dir($recepcionesPath)) {
            $files = scandir($recepcionesPath);
            $info['recepciones_files'] = array_values(array_filter($files, fn($f) => $f !== '.' && $f !== '..'));
            $info['recepciones_count'] = count($info['recepciones_files']);
        }
        
        // Intentar crear archivo de prueba
        $testFile = $basePath . '/test_write_' . time() . '.txt';
        try {
            file_put_contents($testFile, 'test');
            $info['write_test'] = file_exists($testFile) ? 'SUCCESS' : 'FAILED';
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        } catch (\Exception $e) {
            $info['write_test'] = 'ERROR: ' . $e->getMessage();
        }
        
        return response()->json($info, 200, [], JSON_PRETTY_PRINT);
    }
}