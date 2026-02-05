<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PeriodicidadAviso extends Mailable
{
    use Queueable, SerializesModels;

    public $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build()
    {
        $urgency = strtolower($this->payload['urgency'] ?? 'ok');

        // Prefer explicit threshold color when provided by the generator (controller/command).
        $color = null;
        if (!empty($this->payload['threshold_color'])) {
            $color = ucfirst(strtolower($this->payload['threshold_color']));
        }

        if ($color === null) {
            $color = 'Verde';
            if (strpos($urgency, 'soon') !== false || strpos($urgency, 'amarillo') !== false || strpos($urgency, 'warn') !== false) {
                $color = 'Amarillo';
            } elseif (strpos($urgency, 'urgent') !== false || strpos($urgency, 'rojo') !== false || strpos($urgency, 'alert') !== false) {
                $color = 'Rojo';
            }
        }

        $subject = sprintf('Aviso de entrega periódica: %s — %s', $this->payload['sku'] ?? 'Elemento', ucfirst($this->payload['urgency'] ?? 'aviso'));

        $payload = $this->payload;
        $payload['color'] = $color;
        $payload['days_int'] = isset($payload['days_remaining']) ? (int) round($payload['days_remaining']) : 0;

        return $this->subject($subject)
                    ->view('emails.periodicidad_aviso')
                    ->with(['p' => $payload]);
    }
}
