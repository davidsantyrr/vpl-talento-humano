<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EntregaRegistrada extends Mailable
{
    use Queueable, SerializesModels;

    public $entrega;
    public $elementos;
    public $comprobantePath;

    /**
     * Create a new message instance.
     */
    public function __construct($entrega, $elementos, $comprobantePath = null)
    {
        $this->entrega = $entrega;
        $this->elementos = $elementos;
        $this->comprobantePath = $comprobantePath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $tipoTexto = ucfirst($this->entrega->tipo_entrega ?? 'entrega');
        return new Envelope(
            subject: "Comprobante de Entrega - {$tipoTexto} #{$this->entrega->id}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.entrega-registrada',
            with: [
                'entrega' => $this->entrega,
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
                    ->as('Comprobante_Entrega_' . $this->entrega->id . '.pdf')
                    ->withMime('application/pdf');
            }
        }
        
        return $attachments;
    }
}
