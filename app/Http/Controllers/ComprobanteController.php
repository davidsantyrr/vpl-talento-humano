<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

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

            // Convertir firmas base64 a archivos temporales para Dompdf
            $firmaProcessed = [];
            foreach ($firma as $key => $base64Data) {
                if (!empty($base64Data) && strpos($base64Data, 'data:image') === 0) {
                    // Extraer el base64 puro
                    $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
                    $imageData = base64_decode($base64);
                    
                    if ($imageData !== false) {
                        // Crear archivo temporal
                        $tmpFile = tempnam(sys_get_temp_dir(), 'firma_') . '.png';
                        file_put_contents($tmpFile, $imageData);
                        
                        // Guardar ruta del archivo temporal
                        $firmaProcessed[$key] = $tmpFile;
                    }
                } else {
                    $firmaProcessed[$key] = $base64Data;
                }
            }

            // Preparar datos para la vista
            $viewData = [
                'tipo' => $tipo,
                'registro' => (object) $registro,
                'elementos' => $elementos,
                'firma' => $firmaProcessed
            ];

            // Renderizar vista a HTML
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

            // Intentar generar PDF con Dompdf si está disponible
            try {
                // Usar facade de Laravel Dompdf
                $pdf = Pdf::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                
                // Guardar PDF al disco
                file_put_contents($fullPath, $pdf->output());
                $publicPath = $dir . '/' . $filename;

                Log::info('Comprobante generado', ['tipo' => $tipo, 'file' => $fullPath]);

                // Limpiar archivos temporales de firmas
                foreach ($firmaProcessed as $tmpFile) {
                    if (is_string($tmpFile) && file_exists($tmpFile) && strpos($tmpFile, sys_get_temp_dir()) === 0) {
                        @unlink($tmpFile);
                    }
                }

                return response()->json(['success' => true, 'path' => $publicPath, 'message' => 'Comprobante guardado correctamente']);

            } catch (\Exception $pdfEx) {
                // Limpiar archivos temporales en caso de error
                foreach ($firmaProcessed as $tmpFile) {
                    if (is_string($tmpFile) && file_exists($tmpFile) && strpos($tmpFile, sys_get_temp_dir()) === 0) {
                        @unlink($tmpFile);
                    }
                }
                
                // Fallback: guardar HTML
                $htmlPath = $storageDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.html';
                file_put_contents($htmlPath, $html);
                Log::error('Error generando PDF', ['error' => $pdfEx->getMessage()]);

                return response()->json(['success' => true, 'path' => $dir . '/' . basename($htmlPath), 'message' => 'Se guardó HTML como fallback por error al generar PDF']);
            }

        } catch (\Exception $e) {
            Log::error('Error en generar comprobante', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error al generar comprobante: ' . $e->getMessage()], 500);
        }
    }
}
