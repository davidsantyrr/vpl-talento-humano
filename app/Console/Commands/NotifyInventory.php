<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GestionNotificacionInventario;
use App\Models\Producto;
use App\Models\CorreosNotificacion;
use Illuminate\Support\Facades\Mail;
use App\Mail\InventarioBajo;
use Illuminate\Support\Facades\Log;

class NotifyInventory extends Command
{
    protected $signature = 'notify:inventory';
    protected $description = 'Check inventory against notifications and send emails when below threshold';

    public function handle()
    {
        $notifs = GestionNotificacionInventario::all();
        foreach ($notifs as $n) {
            try {
                $this->checkAndNotify($n);
            } catch (\Throwable $e) {
                Log::error('Error checking notification', ['id' => $n->id ?? null, 'error' => $e->getMessage()]);
            }
        }
        return 0;
    }

    protected function checkAndNotify(GestionNotificacionInventario $n)
    {
        $sku = (string) $n->elemento;
        $umbral = (int) $n->stock;

        $producto = Producto::where('sku', $sku)->first();
        if (!$producto) {
            $producto = Producto::whereRaw('LOWER(name_produc) = ?', [mb_strtolower($sku)])->first();
        }
        if (!$producto) return;

        $stockActual = (int) ($producto->stock_produc ?? 0);
        $nombre = (string) ($producto->name_produc ?? $producto->name ?? $n->elemento);
        // Enviar sólo cuando se cruce el umbral desde encima -> por debajo (evita reenvíos continuos)
        if ($stockActual <= $umbral) {
            if (!$n->notified) {
                $emails = CorreosNotificacion::whereRaw("LOWER(rol) LIKE ?", ['%hseq%'])->pluck('correo')->filter()->values()->toArray();
                if (empty($emails)) {
                    $from = config('mail.from.address');
                    if ($from) $emails = [$from];
                }
                if (!empty($emails)) {
                    $m = new InventarioBajo($n->elemento, $producto->sku, $stockActual, $umbral);
                    $m->nombre = $nombre;
                    Mail::to($emails)->send($m);
                    $n->notified = true;
                    $n->last_notified_at = now();
                    $n->save();
                    Log::info('Inventario bajo - correo enviado', ['sku' => $producto->sku, 'stock' => $stockActual, 'umbral' => $umbral, 'emails' => $emails]);
                }
            }
        } else {
            // si el stock volvió a subir por encima del umbral, resetear el flag para futuras notificaciones
            if ($n->notified) {
                $n->notified = false;
                $n->save();
            }
        }
    }
}
