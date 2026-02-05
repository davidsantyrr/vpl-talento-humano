<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InventarioBajo extends Mailable
{
    use Queueable, SerializesModels;

    public $elemento;
    public $sku;
    public $nombre;
    public $stockActual;
    public $umbral;

    public function __construct($elemento, $sku, $stockActual, $umbral)
    {
        $this->elemento = $elemento;
        $this->sku = $sku;
        $this->nombre = null;
        $this->stockActual = $stockActual;
        $this->umbral = $umbral;
    }

    public function build()
    {
        $displayName = $this->nombre ?: $this->elemento;
        $subject = sprintf('Notificación de inventario bajo — %s', $displayName);
        return $this->subject($subject)
                    ->view('emails.inventario_bajo')
                    ->with([
                        'elemento' => $this->elemento,
                        'sku' => $this->sku,
                        'nombre' => $this->nombre,
                        'stockActual' => $this->stockActual,
                        'umbral' => $this->umbral,
                        'appName' => config('app.name'),
                    ]);
    }
}
