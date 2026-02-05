<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioDevolucion extends Mailable
{
    use Queueable, SerializesModels;

    public $entrega;
    public $items;
    public $fechaLimite;
    public $destinatarioNombre;

    public function __construct($entrega, array $items, ?string $destinatarioNombre = null)
    {
        $this->entrega = $entrega;
        $this->items = $items;
        $this->fechaLimite = isset($entrega->recordatorio_devolucion_at) ? (string)$entrega->recordatorio_devolucion_at : null;
        $this->destinatarioNombre = $destinatarioNombre;
    }

    public function build()
    {
        $fullName = trim(($this->entrega->nombres ?? '').' '.($this->entrega->apellidos ?? ''));
        $subject = 'Recordatorio de devolución de préstamo'.($fullName ? ' - '.$fullName : '');
        if ($this->fechaLimite) $subject .= ' (vence: '.$this->fechaLimite.')';

        return $this->subject($subject)
            ->view('emails.recordatorio_devolucion');
    }
}
