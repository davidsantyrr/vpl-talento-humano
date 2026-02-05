<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecordatorioDevolucion;

Artisan::command('mail:test', function(){
    // Configurar Mailpit runtime
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1025,
        'mail.mailers.smtp.encryption' => null,
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.from.address' => 'dev@local.test',
        'mail.from.name' => 'VPL Talento Humano',
    ]);
    Mail::raw('Prueba de Mailpit: recordatorio de devolución', function($m){
        $m->to('test@localhost')->subject('Prueba Mailpit');
    });
    $this->info('Correo de prueba enviado. Verifica Mailpit.');
})->purpose('Enviar un correo de prueba a Mailpit');

Artisan::command('mail:test-recordatorio', function(){
    // Configurar Mailpit en runtime para el entorno local
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1025,
        'mail.mailers.smtp.encryption' => null,
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.from.address' => 'dev@local.test',
        'mail.from.name' => 'VPL Talento Humano',
    ]);

    // Entrega de ejemplo
    $entrega = (object) [
        'nombres' => 'Juan',
        'apellidos' => 'Pérez',
        'numero_documento' => '123',
        'recordatorio_devolucion_at' => now()->toDateString(),
    ];
    $items = [
        ['sku' => 'SKU-TEST-1', 'name' => 'Casco de seguridad', 'cantidad' => '2'],
        ['sku' => 'SKU-TEST-2', 'name' => 'Guantes EPP', 'cantidad' => '1'],
    ];
    $destinatario = 'Admin Test';

    Mail::to('test@localhost')->send(new RecordatorioDevolucion($entrega, $items, $destinatario));
    $this->info('Correo de recordatorio enviado. Revisa Mailpit.');
})->purpose('Enviar mailable de recordatorio de devolución con redacción completa');

Artisan::command('entregas:recordatorios-devolucion', function () {
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
                } catch (\Throwable $ex) { $names = []; }
            }
            $items = [];
            foreach ($itemsRaw as $ir) {
                $sku = (string)$ir->sku;
                $items[] = [ 'sku' => $sku, 'name' => $names[$sku] ?? $sku, 'cantidad' => (string)$ir->cantidad ];
            }

            $emails = [];
            $destNombre = !empty($e->entrega_user) ? (string)$e->entrega_user : null;
            if (!empty($e->entrega_email)) { $emails[] = (string)$e->entrega_email; }
            if (empty($emails)) { $from = config('mail.from.address'); if ($from) $emails[] = $from; }

            if (!empty($emails)) {
                \App\Jobs\EnviarCorreoRecordatorioDevolucion::dispatchSync($emails, $e, $items, $destNombre);
                DB::table('entregas')->where('id', $e->id)->update(['recordatorio_devolucion_enviado' => true, 'updated_at' => now()]);
                $count++;
            }
        } catch (\Throwable $ex) {
            $this->error('Fallo al procesar entrega ID '.$e->id.': '.$ex->getMessage());
        }
    }

    $this->info("Recordatorios enviados: {$count}");
})->purpose('Enviar correos de recordatorio de devolución para préstamos con fecha hoy');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
