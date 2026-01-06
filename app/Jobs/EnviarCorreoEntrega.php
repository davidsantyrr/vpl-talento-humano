<?php

namespace App\Jobs;

use App\Mail\EntregaRegistrada;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EnviarCorreoEntrega implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $entrega;
    public $elementos;
    public $emailDestino;
    public $comprobantePath;

    /**
     * Create a new job instance.
     */
    public function __construct($entrega, $elementos, $emailDestino, $comprobantePath = null)
    {
        $this->entrega = $entrega;
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
                new EntregaRegistrada($this->entrega, $this->elementos, $this->comprobantePath)
            );

            Log::info('Correo de entrega enviado', [
                'entrega_id' => $this->entrega->id,
                'email' => $this->emailDestino
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de entrega', [
                'entrega_id' => $this->entrega->id,
                'email' => $this->emailDestino,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
