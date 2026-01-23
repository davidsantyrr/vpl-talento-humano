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
use App\Models\Correos;

class EnviarCorreoEntrega implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $entrega;
    public $elementos;
    public $emailDestino;
    public $comprobantePath;
    public $incluirCorreosGestion = false;
    public $rolUsuario = null;

    /**
     * Create a new job instance.
     */
    public function __construct($entrega, $elementos, $emailDestino, $comprobantePath = null, $incluirCorreosGestion = false, $rolUsuario = null)
    {
        $this->entrega = $entrega;
        $this->elementos = $elementos;
        $this->emailDestino = $emailDestino;
        $this->comprobantePath = $comprobantePath;
        $this->incluirCorreosGestion = (bool) $incluirCorreosGestion;
        $this->rolUsuario = $rolUsuario;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Normalizar destino principal a array
            $primary = is_array($this->emailDestino) ? $this->emailDestino : [$this->emailDestino];

            $recipients = $primary;

            // Si se indicó incluir correos desde la gestión, filtrar por rol del usuario
            if ($this->incluirCorreosGestion) {
                $all = Correos::all();
                if ($this->rolUsuario) {
                    $rolNorm = mb_strtolower(trim((string)$this->rolUsuario));
                    $extra = $all->filter(function($c) use ($rolNorm) {
                        return $c->correo && strpos(mb_strtolower($c->rol ?? ''), $rolNorm) !== false;
                    })->pluck('correo')->toArray();
                } else {
                    // si no hay rol, no añadimos extras
                    $extra = [];
                }
                $recipients = array_values(array_unique(array_filter(array_merge($recipients, $extra))));
            }

            if (empty($recipients)) {
                Log::warning('No hay destinatarios para correo de entrega', ['entrega_id' => $this->entrega->id]);
            } else {
                Mail::to($recipients)->send(
                    new EntregaRegistrada($this->entrega, $this->elementos, $this->comprobantePath)
                );

                Log::info('Correo de entrega enviado', [
                    'entrega_id' => $this->entrega->id,
                    'recipients' => $recipients
                ]);
            }
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
