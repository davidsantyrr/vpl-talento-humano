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
use App\Models\Usuarios;

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

            // Si se indicó incluir correos desde la gestión, incluir los correos registrados en gestion_correos.
            // Antes se intentaba filtrar por rol; falla si el cargo del receptor no coincide exactamente
            // con la etiqueta usada en `gestion_correos`. Para garantizar que el correo agregado
            // se tenga en cuenta cuando el checkbox está activo, incluimos todos los correos.
            if ($this->incluirCorreosGestion) {
                try {
                    $extra = Correos::whereNotNull('correo')
                        ->pluck('correo')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();
                    if (!empty($extra)) {
                        Log::info('Incluyendo correos de gestion (checkbox activado)', ['rolUsuario' => $this->rolUsuario, 'correos' => $extra]);
                    } else {
                        Log::info('No hay correos registrados en gestion_correos para incluir');
                    }
                    $recipients = array_values(array_unique(array_filter(array_merge($recipients, $extra))));
                } catch (\Throwable $e) {
                    Log::warning('Error al recuperar correos de gestion', ['error' => $e->getMessage()]);
                }
            }

            // Determinar CC para prestamos: coordinador(es) de la operación
            $cc = [];
            $tipoEntrega = isset($this->entrega->tipo_entrega) ? (string)$this->entrega->tipo_entrega : null;
            $subAreaId = $this->entrega->sub_area_id ?? ($this->entrega->sub_area_id ?? null);
            if ($tipoEntrega === 'prestamo' && $subAreaId) {
                try {
                    $coordEmails = Usuarios::where('operacion_id', $subAreaId)
                        ->whereHas('cargo', function($q){ $q->whereRaw("LOWER(nombre) = ?", ['coordinador']); })
                        ->pluck('email')
                        ->filter()
                        ->unique()
                        ->toArray();
                    if (!empty($coordEmails)) {
                        $cc = $coordEmails;
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo obtener correos de coordinador', ['error' => $e->getMessage(), 'sub_area_id' => $subAreaId]);
                }
            }

            if (empty($recipients)) {
                Log::warning('No hay destinatarios para correo de entrega', ['entrega_id' => $this->entrega->id]);
            } else {
                $mailable = new EntregaRegistrada($this->entrega, $this->elementos, $this->comprobantePath);
                Log::info('Preparando envío correo de entrega', ['entrega_id' => $this->entrega->id ?? null, 'to' => $recipients, 'cc_candidate' => $cc]);
                $mail = Mail::to($recipients);
                if (!empty($cc)) {
                    // evitar duplicados entre to y cc
                    $cc = array_values(array_diff($cc, $recipients));
                    if (!empty($cc)) $mail = $mail->cc($cc);
                }
                $mail->send($mailable);

                Log::info('Correo de entrega enviado (post-send)', [
                    'entrega_id' => $this->entrega->id,
                    'recipients' => $recipients,
                    'cc' => $cc
                ]);

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

    /**
     * Normalizar cadena para comparación (minúsculas, guiones/underscores -> espacios, collapse spaces)
     */
    private function normalize(string $value): string
    {
        $v = trim(mb_strtolower($value));
        $v = str_replace(['_', '-'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }
}
