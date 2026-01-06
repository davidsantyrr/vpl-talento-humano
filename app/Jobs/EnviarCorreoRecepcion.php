<?php

namespace App\Jobs;

use App\Mail\RecepcionRegistrada;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EnviarCorreoRecepcion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recepcion;
    protected $elementos;
    protected $emailUsuario;
    protected $comprobantePath;

    /**
     * Create a new job instance.
     */
    public function __construct($recepcion, $elementos, $emailUsuario, $comprobantePath = null)
    {
        $this->recepcion = $recepcion;
        $this->elementos = $elementos;
        $this->emailUsuario = $emailUsuario;
        $this->comprobantePath = $comprobantePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Enviando correo de recepción', [
                'recepcion_id' => $this->recepcion->id ?? 'N/A',
                'email' => $this->emailUsuario,
                'comprobante_path' => $this->comprobantePath
            ]);

            Mail::to($this->emailUsuario)->send(
                new RecepcionRegistrada($this->recepcion, $this->elementos, $this->comprobantePath)
            );

            Log::info('Correo de recepción enviado exitosamente', [
                'recepcion_id' => $this->recepcion->id ?? 'N/A',
                'email' => $this->emailUsuario
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de recepción', [
                'recepcion_id' => $this->recepcion->id ?? 'N/A',
                'email' => $this->emailUsuario,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
