<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RecepcionRegistrada extends Mailable
{
    use Queueable, SerializesModels;

    public $recepcion;
    public $elementos;
    public $comprobantePath;

    /**
     * Create a new message instance.
     */
    public function __construct($recepcion, $elementos, $comprobantePath = null)
    {
        $this->recepcion = $recepcion;
        $this->elementos = $elementos;
        $this->comprobantePath = $comprobantePath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        Log::info('🔵 RecepcionRegistrada->build() iniciado', [
            'recepcion_id' => $this->recepcion->id ?? 'N/A',
            'comprobante_path' => $this->comprobantePath
        ]);

        $email = $this->subject('Comprobante de Recepción - Sistema de Gestión')
            ->view('emails.recepcion-registrada')
            ->with([
                'recepcion' => $this->recepcion,
                'elementos' => $this->elementos
            ]);

        // Adjuntar PDF si existe
        if (!empty($this->comprobantePath)) {
            // Buscar ruta real del archivo: soportar ruta absoluta, Storage::exists en discos y storage_path
            $fullPath = null;

            // 1) Si es ruta absoluta y existe
            if (file_exists($this->comprobantePath)) {
                $fullPath = $this->comprobantePath;
            }

            // 2) Intentar Storage (path relativo dentro de disks)
            if (!$fullPath) {
                try {
                    if (Storage::exists($this->comprobantePath)) {
                        $fullPath = Storage::path($this->comprobantePath);
                    } elseif (Storage::disk('public')->exists($this->comprobantePath)) {
                        $fullPath = Storage::disk('public')->path($this->comprobantePath);
                    } elseif (Storage::disk('local')->exists($this->comprobantePath)) {
                        $fullPath = Storage::disk('local')->path($this->comprobantePath);
                    }
                } catch (\Throwable $e) {
                    // Ignorar errores de disks y continuar con otros intentos
                    Log::debug('Storage check fallo', ['error' => $e->getMessage(), 'path' => $this->comprobantePath]);
                }
            }

            // 3) Fallback a rutas dentro de storage/app y storage/app/public
            if (!$fullPath) {
                $candidates = [
                    storage_path('app/' . ltrim($this->comprobantePath, '/')),
                    storage_path('app/public/' . ltrim($this->comprobantePath, '/')),
                ];
                foreach ($candidates as $c) {
                    if (file_exists($c)) {
                        $fullPath = $c;
                        break;
                    }
                }
            }

            if ($fullPath && file_exists($fullPath)) {
                try {
                    $email->attach($fullPath, [
                        'as' => 'Comprobante_Recepcion_' . ($this->recepcion->id ?? 'N/A') . '.pdf',
                        'mime' => 'application/pdf'
                    ]);

                    Log::info('✅ PDF adjuntado al correo de recepción', [
                        'path' => $this->comprobantePath,
                        'full_path' => $fullPath,
                        'size' => filesize($fullPath),
                        'exists' => true
                    ]);
                } catch (\Throwable $e) {
                    Log::error('❌ Error adjuntando PDF al correo de recepción', [
                        'path' => $this->comprobantePath,
                        'full_path' => $fullPath,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::error('❌ PDF NO encontrado para adjuntar al correo de recepción', [
                    'path' => $this->comprobantePath,
                    'checked' => [
                        'absolute' => file_exists($this->comprobantePath),
                        'storage_exists' => method_exists(Storage::class, 'exists') ? Storage::exists($this->comprobantePath) : null,
                        'candidates' => [
                            storage_path('app/' . ltrim($this->comprobantePath, '/')),
                            storage_path('app/public/' . ltrim($this->comprobantePath, '/')),
                        ]
                    ],
                ]);
            }
        } else {
            Log::warning('⚠ No se proporcionó path del comprobante para recepción', [
                'recepcion_id' => $this->recepcion->id ?? 'N/A'
            ]);
        }

        return $email;
    }
}
