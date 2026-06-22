<?php

namespace App\Http\Controllers\PDF;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\ElementoXEntrega;

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

            // Cargar historial de entregas anteriores sólo para comprobantes de tipo entrega
            $historialEntregas = [];
            if ($tipo === 'entrega') {
                $authUser = session('auth.user');
                $roleNames = $this->extractRoleNames($authUser);

                $isHSEQ = false;
                $isTalentoHumano = false;
                foreach ($roleNames as $rn) {
                    $normalized = mb_strtolower(trim($rn));
                    if (strpos($normalized, 'hseq') !== false || strpos($normalized, 'seguridad') !== false) {
                        $isHSEQ = true;
                    }
                    if (strpos($normalized, 'talento') !== false || strpos($normalized, 'humano') !== false || $normalized === 'th') {
                        $isTalentoHumano = true;
                    }
                }

                // Si no logramos identificar el rol desde la sesión, usar el rol_entrega enviado en el registro
                if (!$isHSEQ && !$isTalentoHumano && !empty($registro['rol_entrega'])) {
                    $rolEntregaNormalized = mb_strtolower($registro['rol_entrega']);
                    if (strpos($rolEntregaNormalized, 'hseq') !== false || strpos($rolEntregaNormalized, 'seguridad') !== false) {
                        $isHSEQ = true;
                    }
                    if (strpos($rolEntregaNormalized, 'talento') !== false || strpos($rolEntregaNormalized, 'humano') !== false || $rolEntregaNormalized === 'th') {
                        $isTalentoHumano = true;
                    }
                }

                $personQuery = trim((string)($registro['numero_documento'] ?? ''));
                $nombre = trim((string)($registro['nombres'] ?? ''));
                $apellido = trim((string)($registro['apellidos'] ?? ''));

                if ($personQuery !== '' || $nombre !== '' || $apellido !== '') {
                    $query = Entrega::query();
                    if (!empty($registro['id'])) {
                        $query->where('id', '<>', $registro['id']);
                    }

                    if ($personQuery !== '') {
                        $query->where('numero_documento', $personQuery);
                    } else {
                        if ($nombre !== '') {
                            $query->whereRaw('LOWER(nombres) = ?', [mb_strtolower($nombre)]);
                        }
                        if ($apellido !== '') {
                            $query->whereRaw('LOWER(apellidos) = ?', [mb_strtolower($apellido)]);
                        }
                    }

                    if ($isHSEQ && !$isTalentoHumano) {
                        $query->whereRaw('LOWER(rol_entrega) LIKE ?', ['%hseq%']);
                    } elseif ($isTalentoHumano && !$isHSEQ) {
                        $query->where(function ($q) {
                            $q->whereRaw('LOWER(rol_entrega) LIKE ?', ['%talento%'])
                              ->orWhereRaw('LOWER(rol_entrega) LIKE ?', ['%humano%'])
                              ->orWhereRaw('LOWER(rol_entrega) LIKE ?', ['%th%']);
                        });
                    }

                    $previousEntregas = $query->with('elementos')->orderByDesc('created_at')->limit(12)->get();
                    if ($previousEntregas->isNotEmpty()) {
                        $allSkus = $previousEntregas->flatMap(function ($entrega) {
                            return $entrega->elementos->pluck('sku')->filter();
                        })->unique()->values()->all();

                        $skuNameMap = [];
                        if (!empty($allSkus)) {
                            try {
                                $products = \App\Models\Producto::whereIn('sku', $allSkus)->get(['sku', 'name_produc']);
                                foreach ($products as $product) {
                                    $skuNameMap[mb_strtolower(trim($product->sku))] = $product->name_produc;
                                }
                            } catch (\Throwable $e) {
                                // ignore mapping failure
                            }
                        }

                        $historialEntregas = $previousEntregas->map(function ($entrega) use ($skuNameMap) {
                            $firmaPath = !empty($entrega->firma_path) ? storage_path('app/' . ltrim($entrega->firma_path, '/')) : null;
                            return [
                                'id' => $entrega->id,
                                'fecha' => $entrega->created_at ? $entrega->created_at->toDateTimeString() : ($entrega->updated_at ? $entrega->updated_at->toDateTimeString() : now()->toDateTimeString()),
                                'tipo' => $entrega->tipo_entrega ?? 'ENTREGA',
                                'entrega_user' => $entrega->entrega_user ?? 'Sistema',
                                'firma' => ($firmaPath && file_exists($firmaPath)) ? 'file://' . $firmaPath : null,
                                'elementos' => $entrega->elementos->map(function ($el) use ($skuNameMap) {
                                    $sku = trim((string)$el->sku);
                                    $lowerSku = mb_strtolower($sku);
                                    return [
                                        'sku' => $sku,
                                        'name_produc' => $skuNameMap[$lowerSku] ?? null,
                                        'cantidad' => $el->cantidad ?? null,
                                    ];
                                })->toArray(),
                            ];
                        })->toArray();
                    }
                }
            }

            // Preparar datos para la vista
            $viewData = [
                'tipo' => $tipo,
                'registro' => (object) $registro,
                'elementos' => $elementos,
                'firma' => $firmaProcessed,
                'historialEntregas' => $historialEntregas
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
                    'firma_keys' => array_keys($firmaProcessed),
                    'historial_count' => count($historialEntregas)
                ]);

                $pdf = Pdf::loadView('pdf.comprobante', [
                    'tipo' => $tipo,
                    'registro' => (object) $registro,
                    'elementos' => $elementos,
                    'firma' => $firmaProcessed,
                    'historialEntregas' => $historialEntregas
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

    /**
     * Extraer posibles nombres de roles desde el usuario en sesión.
     */
    private function extractRoleNames($user): array
    {
        $roles = [];
        $push = function ($val) use (&$roles) {
            if (is_string($val) && !empty(trim($val))) {
                $roles[] = trim(strtolower($val));
            }
        };

        $candidates = ['role', 'rol', 'perfil', 'role_name', 'nombre_rol', 'tipo_rol'];

        if (is_array($user)) {
            foreach ($candidates as $key) {
                if (isset($user[$key])) {
                    $push($user[$key]);
                }
            }

            if (isset($user['roles']) && is_array($user['roles'])) {
                foreach ($user['roles'] as $item) {
                    if (is_string($item)) {
                        $push($item);
                        continue;
                    }
                    $roleKeys = ['name', 'nombre', 'role', 'rol', 'roles', 'slug', 'key', 'display_name'];
                    if (is_array($item)) {
                        foreach ($roleKeys as $kk) {
                            if (isset($item[$kk])) {
                                $push($item[$kk]);
                            }
                        }
                    } elseif (is_object($item)) {
                        foreach ($roleKeys as $kk) {
                            if (isset($item->$kk)) {
                                $push($item->$kk);
                            }
                        }
                    }
                }
            }
        } elseif (is_object($user)) {
            foreach ($candidates as $key) {
                if (isset($user->$key)) {
                    $push($user->$key);
                }
            }

            if (isset($user->roles) && is_array($user->roles)) {
                foreach ($user->roles as $item) {
                    if (is_string($item)) {
                        $push($item);
                        continue;
                    }
                    $roleKeys = ['name', 'nombre', 'role', 'rol', 'roles', 'slug', 'key', 'display_name'];
                    if (is_array($item)) {
                        foreach ($roleKeys as $kk) {
                            if (isset($item[$kk])) {
                                $push($item[$kk]);
                            }
                        }
                    } elseif (is_object($item)) {
                        foreach ($roleKeys as $kk) {
                            if (isset($item->$kk)) {
                                $push($item->$kk);
                            }
                        }
                    }
                }
            }
        }

        if (empty($roles) && $user) {
            try {
                $serialized = strtolower(json_encode($user));
                if (strpos($serialized, 'hseq') !== false) {
                    $roles[] = 'hseq';
                }
                if (strpos($serialized, 'talento') !== false) {
                    $roles[] = 'talento';
                }
                if (strpos($serialized, 'talento humano') !== false || strpos($serialized, 'talentohumano') !== false) {
                    $roles[] = 'talento humano';
                }
            } catch (\Throwable $e) {
                // ignore serialization failures
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }
}
