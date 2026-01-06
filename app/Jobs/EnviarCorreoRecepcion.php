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

    public $recepcion;
    public $elementos;
    public $emailDestino;
    public $comprobantePath;

    /**
     * Create a new job instance.
     */
    public function __construct($recepcion, $elementos, $emailDestino, $comprobantePath = null)
    {
        $this->recepcion = $recepcion;
        $this->elementos = $elementos;
        $this->emailDestino = $emailDestino;
        $this->comprobantePath = $comprobantePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->emailDestino)->send(
                new RecepcionRegistrada($this->recepcion, $this->elementos, $this->comprobantePath)
            );

            Log::info('Correo de recepción enviado', [
                'recepcion_id' => $this->recepcion->id,
                'email' => $this->emailDestino
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de recepción', [
                'recepcion_id' => $this->recepcion->id,
                'email' => $this->emailDestino,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
