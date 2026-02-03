<?php

namespace App\Jobs;

use App\Mail\InventarioBajo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoInventarioBajo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array|string */
    public $emails;
    public string $elemento;
    public string $sku;
    public ?string $nombre;
    public int $stockActual;
    public int $umbral;

    /**
     * Create a new job instance.
     * @param array|string $emails
     */
    public function __construct($emails, string $elemento, string $sku, int $stockActual, int $umbral, ?string $nombre = null)
    {
        $this->emails = is_array($emails) ? $emails : [$emails];
        $this->elemento = $elemento;
        $this->sku = $sku;
        $this->stockActual = $stockActual;
        $this->umbral = $umbral;
        $this->nombre = $nombre;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $m = new InventarioBajo($this->elemento, $this->sku, $this->stockActual, $this->umbral);
        $m->nombre = $this->nombre;
        Mail::to($this->emails)->send($m);
    }
}
