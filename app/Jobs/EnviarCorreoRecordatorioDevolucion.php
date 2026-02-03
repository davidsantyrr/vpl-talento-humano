<?php

namespace App\Jobs;

use App\Mail\RecordatorioDevolucion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoRecordatorioDevolucion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array|string */
    public $emails;
    public $entrega; // stdClass/array (id, nombres, apellidos, numero_documento, recordatorio_devolucion_at, etc.)
    public $items;   // array [['sku','name','cantidad']]
    public $destinatarioNombre;

    /**
     * @param array|string $emails
     * @param object|array $entrega
     * @param array $items
     */
    public function __construct($emails, $entrega, array $items, ?string $destinatarioNombre = null)
    {
        $this->emails = is_array($emails) ? $emails : [$emails];
        $this->entrega = is_array($entrega) ? (object)$entrega : $entrega;
        $this->items = $items;
        $this->destinatarioNombre = $destinatarioNombre;
    }

    public function handle(): void
    {
        $m = new RecordatorioDevolucion($this->entrega, $this->items, $this->destinatarioNombre);
        Mail::to($this->emails)->send($m);
    }
}
