<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\EnviarCorreoRecordatorioDevolucion;

class EnviarRecordatoriosDevolucion extends Command
{
    protected $signature = 'entregas:recordatorios-devolucion';
    protected $description = 'Envía correos de recordatorio para devoluciones de préstamos cuya fecha sea hoy';

    public function handle()
    {
        $hoy = now()->toDateString();
        $this->info('Buscando entregas con recordatorio para hoy: '.$hoy);

        $rows = DB::table('entregas')
            ->whereIn(DB::raw('LOWER(tipo_entrega)'), ['prestamo','préstamo'])
            ->whereDate('recordatorio_devolucion_at', '=', $hoy)
            ->where(function($q){ $q->whereNull('recordatorio_devolucion_enviado')->orWhere('recordatorio_devolucion_enviado', false); })
            ->get();

        $count = 0;
        foreach ($rows as $e) {
            try {
                // Obtener items de la entrega
                $itemsRaw = DB::table('elemento_x_entrega')
                    ->where('entrega_id', $e->id)
                    ->select('sku','cantidad')
                    ->get();

                $skus = $itemsRaw->pluck('sku')->filter()->unique()->values()->all();
                $names = [];
                if (!empty($skus)) {
                    try {
                        $prodModel = new \App\Models\Producto();
                        $conn = $prodModel->getConnectionName() ?: config('database.default');
                        $prodRows = DB::connection($conn)
                            ->table($prodModel->getTable())
                            ->whereIn('sku', $skus)
                            ->select('sku','name_produc')
                            ->get();
                        foreach ($prodRows as $pr) { $names[(string)$pr->sku] = (string)($pr->name_produc ?? ''); }
                    } catch (\Throwable $ex) {
                        $names = [];
                    }
                }
                $items = [];
                foreach ($itemsRaw as $ir) {
                    $sku = (string)$ir->sku;
                    $items[] = [
                        'sku' => $sku,
                        'name' => $names[$sku] ?? $sku,
                        'cantidad' => (string)$ir->cantidad,
                    ];
                }

                // Enviar al correo de quien registró la entrega (entrega_email)
                $emails = [];
                $destNombre = !empty($e->entrega_user) ? (string)$e->entrega_user : null;
                if (!empty($e->entrega_email)) { $emails[] = (string)$e->entrega_email; }
                if (empty($emails)) { $from = config('mail.from.address'); if ($from) $emails[] = $from; }

                if (!empty($emails)) {
                    EnviarCorreoRecordatorioDevolucion::dispatch($emails, $e, $items, $destNombre);
                    DB::table('entregas')->where('id', $e->id)->update(['recordatorio_devolucion_enviado' => true, 'updated_at' => now()]);
                    $count++;
                }
            } catch (\Throwable $ex) {
                Log::warning('Fallo al procesar recordatorio de devolucion', ['entrega_id' => $e->id ?? null, 'error' => $ex->getMessage()]);
            }
        }

        $this->info("Recordatorios enviados: {$count}");
        return 0;
    }
}
