<?php

namespace App\Http\Controllers\PDF;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;

class ComprobanteController extends Controller
{
    /**
     * Generar y guardar comprobante (entrega o recepcion)
     */
    public function generar(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:entrega,recepcion'],
            'registro' => ['required', 'array'],
            'elementos' => ['nullable', 'array'],
            'firma' => ['nullable', 'array'], // ['entrega' => base64?, 'recepcion' => base64?]
            'guardar_en' => ['nullable', 'string']
        ]);

        try {
            $tipo = $data['tipo'];
            $registro = $data['registro'];
            $elementos = $data['elementos'] ?? [];
            $firma = $data['firma'] ?? [];

            // Log para depuración de los datos recibidos
            Log::info('ComprobanteController::generar - Datos recibidos', [
                'tipo' => $tipo,
                'registro_cargo' => $registro['cargo'] ?? 'NO ENVIADO',
                'registro_entrega_user' => $registro['entrega_user'] ?? 'NO ENVIADO',
                'registro_operacion' => $registro['operacion'] ?? 'NO ENVIADO',
                'elementos_count' => count($elementos),
                'elementos_sample' => array_slice($elementos, 0, 2) // primeros 2 elementos para ver estructura
            ]);

            // Normalizar firmas para la vista: preferimos data-uri (base64) si viene así,
            // o file://absolute_path si la firma es una ruta en storage.
            $firmaProcessed = [];
            foreach ($firma as $key => $value) {
                if (is_string($value) && strpos($value, 'data:image') === 0) {
                    // Data URI → guardar como archivo temporal y usar file:// path para máxima compatibilidad
                    try {
                        [$meta, $content] = explode(',', $value, 2);
                        $ext = 'png';
                        if (strpos($meta, 'image/jpeg') !== false) { $ext = 'jpg'; }
                        elseif (strpos($meta, 'image/webp') !== false) { $ext = 'webp'; }
                        $bin = base64_decode($content);
                        $dir = storage_path('app/tmp_firmas');
                        if (!file_exists($dir)) { mkdir($dir, 0755, true); }
                        $file = $dir . '/' . uniqid('firma_', true) . '.' . $ext;
                        file_put_contents($file, $bin);
                        $firmaProcessed[$key] = 'file://' . $file;
                    } catch (\Throwable $e) {
                        // Si falla, usar el data-uri como fallback
                        $firmaProcessed[$key] = $value;
                    }
                } elseif (is_string($value) && Storage::exists($value)) {
                    // Ruta en storage (ej: public/firmas/xxx.png) → pasar file://absolute
                    $firmaProcessed[$key] = 'file://' . storage_path('app/' . ltrim($value, '/'));
                } else {
                    // No sabemos, pasar como está (posible URL o path absoluto)
                    $firmaProcessed[$key] = $value;
                }
            }

            // Preparar datos para la vista
            $viewData = [
                'tipo' => $tipo,
                'registro' => (object) $registro,
                'elementos' => $elementos,
                'firma' => $firmaProcessed
            ];

            // Renderizar vista a HTML (para debugging/fallback)
            $html = view('pdf.comprobante', $viewData)->render();

            // Crear directorio
            $dir = ($tipo === 'entrega') ? 'comprobantes_entregas' : 'comprobantes_recepciones';
            $storageDir = storage_path('app/' . $dir);
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            // Nombre de archivo: DOC_NumeroDocumento_FECHA_TIMESTAMP.pdf
            $numeroDoc = $registro['numero_documento'] ?? ($registro['nombres'] ?? 'registro');
            $numeroDoc = preg_replace('/[^A-Za-z0-9\-_]/', '_', substr($numeroDoc, 0, 40));
            $fecha = now()->format('Y-m-d');
            $timestamp = now()->timestamp;
            $filename = strtoupper($tipo) . "_{$numeroDoc}_{$fecha}_{$timestamp}.pdf";
            $fullPath = $storageDir . '/' . $filename;

            // Validar existencia de firma requerida
            $firmaRequerida = null;
            if ($tipo === 'entrega') {
                $firmaRequerida = $firma['entrega'] ?? null;
            } elseif ($tipo === 'recepcion') {
                $firmaRequerida = $firma['recepcion'] ?? null;
            }

            if (empty($firmaRequerida) || (is_string($firmaRequerida) && strlen($firmaRequerida) < 80)) {
                Log::error('Firma inválida o vacía', [
                    'tipo' => $tipo,
                    'firma_length' => is_string($firmaRequerida) ? strlen($firmaRequerida) : null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'La firma es obligatoria para generar el comprobante'
                ], 400);
            }

            // Generar PDF con la plantilla - PASAR LA FIRMA PROCESADA
            try {
                Log::info('Generando comprobante PDF - usando firma procesada', [
                    'tipo' => $tipo,
                    'registro_id' => $registro['id'] ?? 'temporal',
                    'firma_keys' => array_keys($firmaProcessed)
                ]);

                $pdf = Pdf::loadView('pdf.comprobante', [
                    'tipo' => $tipo,
                    'registro' => (object) $registro,
                    'elementos' => $elementos,
                    'firma' => $firmaProcessed
                ]);

                $pdf->setPaper('A4', 'portrait');
                file_put_contents($fullPath, $pdf->output());
                $publicPath = $dir . '/' . $filename;

                Log::info('Comprobante generado', ['tipo' => $tipo, 'file' => $fullPath]);

                return response()->json(['success' => true, 'path' => $publicPath, 'message' => 'Comprobante guardado correctamente']);

            } catch (\Exception $pdfEx) {
                Log::error('Error generando PDF', ['error' => $pdfEx->getMessage()]);
                // Fallback: guardar HTML
                $htmlPath = $storageDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.html';
                file_put_contents($htmlPath, $html);
                return response()->json(['success' => true, 'path' => $dir . '/' . basename($htmlPath), 'message' => 'Se guardó HTML como fallback por error al generar PDF']);
            }

        } catch (\Exception $e) {
            Log::error('Error en generar comprobante', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error al generar comprobante: ' . $e->getMessage()], 500);
        }
    }
}
