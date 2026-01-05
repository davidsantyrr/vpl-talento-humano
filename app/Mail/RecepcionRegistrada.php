<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

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
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $tipoTexto = ucfirst($this->recepcion->tipo_recepcion ?? 'recepción');
        return new Envelope(
            subject: "Comprobante de Recepción - {$tipoTexto} #{$this->recepcion->id}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.recepcion-registrada',
            with: [
                'recepcion' => $this->recepcion,
                'elementos' => $this->elementos,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];
        
        if ($this->comprobantePath) {
            $fullPath = storage_path('app/' . $this->comprobantePath);
            if (file_exists($fullPath)) {
                $attachments[] = Attachment::fromPath($fullPath)
                    ->as('Comprobante_Recepcion_' . $this->recepcion->id . '.pdf')
                    ->withMime('application/pdf');
            }
        }
        
        return $attachments;
    }
}
