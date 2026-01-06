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
            // Intentar diferentes rutas posibles
            $posiblesPaths = [
                storage_path('app/' . $this->comprobantePath),
                storage_path('app/public/' . $this->comprobantePath),
                $this->comprobantePath, // Por si ya es ruta absoluta
            ];

            $fullPath = null;
            foreach ($posiblesPaths as $path) {
                if (file_exists($path)) {
                    $fullPath = $path;
                    break;
                }
            }
            
            if ($fullPath && file_exists($fullPath)) {
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
            } else {
                Log::error('❌ PDF NO encontrado para adjuntar al correo de recepción', [
                    'path' => $this->comprobantePath,
                    'intentos' => $posiblesPaths,
                    'exists' => false
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
