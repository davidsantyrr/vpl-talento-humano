<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\PeriodicidadAviso;
use App\Models\CorreosNotificacion;
use Illuminate\Support\Facades\Log;

class NotifyPeriodicidad extends Command
{
    protected $signature = 'notify:periodicidad {--dry-run : Do not send emails, just report how many would be sent} {--role= : Optional role filter (hseq|talento)} {--force-days= : Temporarily force days remaining for all items (testing)}';
    protected $description = 'Enviar notificaciones por periodicidades próximas (semaforización)';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $roleOpt = $this->option('role');
        $rolesToRun = [];
        if (!empty($roleOpt)) {
            $rolesToRun[] = strtolower($roleOpt);
        } else {
            $rolesToRun = ['hseq', 'talento'];
        }

        try {
            $controller = app(\App\Http\Controllers\elementoPeriodicidad\ElementoPeriodicidadController::class);
            if (!method_exists($controller, 'upcomingNotifications')) {
                Log::warning('NotifyPeriodicidad: controlador no expone upcomingNotifications');
                $this->error('upcomingNotifications no disponible');
                return 1;
            }

            $sentCount = 0;

            foreach ($rolesToRun as $roleRun) {
                try {
                    $items = $controller->upcomingNotifications($roleRun);
                } catch (\Throwable $e) {
                    Log::error('NotifyPeriodicidad: error obteniendo upcomingNotifications', ['role' => $roleRun, 'error' => $e->getMessage()]);
                    continue;
                }

                // Testing helper: optionally force days_remaining to a specific value
                $forceDaysOpt = $this->option('force-days');
                $forceDays = is_numeric($forceDaysOpt) ? (int)$forceDaysOpt : 0;
                if ($forceDays > 0) {
                    foreach ($items as &$it) {
                        $it['days_remaining'] = $forceDays;
                        // recompute urgency based on thresholds
                        $thr = null;
                        try {
                            // Always try to fetch thresholds by SKU when forcing days (safer for testing)
                            $pr = \DB::table('periodicidad')->where('sku', $it['sku'])->first();
                            if ($pr) {
                                $thr = [
                                    'rojo' => isset($pr->aviso_rojo) ? (int)$pr->aviso_rojo : null,
                                    'amarillo' => isset($pr->aviso_amarillo) ? (int)$pr->aviso_amarillo : null,
                                    'verde' => isset($pr->aviso_verde) ? (int)$pr->aviso_verde : null,
                                ];
                            }
                        } catch (\Throwable $e) { $thr = null; }
                        Log::debug('NotifyPeriodicidad thresholds', ['sku'=>$it['sku'] ?? null, 'thr'=>$thr]);

                        $urg = 'ok';
                        if ($thr) {
                            if ($thr['rojo'] !== null && $it['days_remaining'] <= $thr['rojo']) $urg = 'urgent';
                            elseif ($thr['amarillo'] !== null && $it['days_remaining'] <= $thr['amarillo']) $urg = 'soon';
                            elseif ($thr['verde'] !== null && $it['days_remaining'] <= $thr['verde']) $urg = 'warning';
                        } else {
                            $base = 14; // fallback
                            if ($it['days_remaining'] <= $base) $urg = 'soon';
                            elseif ($it['days_remaining'] <= $base*2) $urg = 'warning';
                        }
                        $it['urgency'] = $urg;

                        // set threshold_days/threshold_color for display
                        $thresholdDays = null; $thresholdName = null;
                        if ($thr) {
                            if ($urg === 'urgent') { $thresholdDays = $thr['rojo']; $thresholdName = 'Rojo'; }
                            elseif ($urg === 'soon') { $thresholdDays = $thr['amarillo']; $thresholdName = 'Amarillo'; }
                            elseif ($urg === 'warning') { $thresholdDays = $thr['verde']; $thresholdName = 'Verde'; }
                        }
                        $it['threshold_days'] = $thresholdDays;
                        $it['threshold_color'] = $thresholdName;
                    }
                    unset($it);
                }

                foreach ($items as $p) {
                    $emails = [];
                    if (!empty($p['users']) && is_array($p['users'])) {
                        foreach ($p['users'] as $u) {
                            if (!empty($u['email'])) $emails[] = $u['email'];
                        }
                    }

                    if (empty($emails)) {
                        if (!empty($p['rol_periodicidad'])) {
                            try {
                                $pattern = '%' . strtolower(trim($p['rol_periodicidad'])) . '%';
                                $byRole = CorreosNotificacion::whereRaw('LOWER(rol) LIKE ?', [$pattern])->pluck('correo')->filter()->values()->toArray();
                                $emails = array_merge($emails, $byRole);
                            } catch (\Throwable $e) { Log::warning('Error buscando correos por rol_periodicidad', ['error'=>$e->getMessage()]); }
                        }
                        try {
                            $hseq = CorreosNotificacion::whereRaw("LOWER(rol) LIKE ?", ['%hseq%'])->pluck('correo')->filter()->values()->toArray();
                            $emails = array_merge($emails, $hseq);
                        } catch (\Throwable $e) { Log::warning('Error buscando correos HSEQ', ['error'=>$e->getMessage()]); }

                        $emails = array_values(array_unique(array_filter($emails)));
                        if (empty($emails)) {
                            $from = config('mail.from.address');
                            if ($from) $emails = [$from];
                        }
                    }

                    $payload = [
                        'sku' => $p['sku'] ?? null,
                        'name' => $p['name'] ?? ($p['sku'] ?? null),
                        'next_date' => $p['next_date'] ?? null,
                        'days_remaining' => $p['days_remaining'] ?? 0,
                        'days_int' => isset($p['days_remaining']) ? (int) round($p['days_remaining']) : 0,
                        'urgency' => $p['urgency'] ?? 'ok',
                        'quantity' => $p['quantity'] ?? 1,
                        'users' => $p['users'] ?? [],
                    ];

                    if ($dry) {
                        Log::info('Periodicidad - correo enviado (or simulated)', ['sku'=>$p['sku'] ?? null,'urgency'=>$p['urgency'] ?? null,'to'=>$emails,'dry'=>true,'quantity'=>$payload['quantity'] ?? null,'days_int'=>$payload['days_int'] ?? null]);
                        $sentCount++;
                    } else {
                        try {
                            Mail::to($emails)->send(new PeriodicidadAviso($payload));
                            $sentCount++;
                            Log::info('Periodicidad - correo enviado', ['sku'=>$p['sku'] ?? null,'urgency'=>$p['urgency'] ?? null,'to'=>$emails,'dry'=>false,'quantity'=>$payload['quantity'] ?? null,'days_int'=>$payload['days_int'] ?? null]);
                        } catch (\Throwable $e) {
                            Log::error('Periodicidad - fallo enviando correo', ['sku'=>$p['sku'] ?? null,'error'=>$e->getMessage()]);
                        }
                    }
                }
            }

            if ($dry) {
                $this->info('Dry-run: ' . $sentCount . ' emails would be sent.');
                Log::info('notify:periodicidad dry-run', ['would_send' => $sentCount]);
            } else {
                $this->info('Sent: ' . $sentCount . ' emails.');
            }

            return 0;
        } catch (\Throwable $e) {
            Log::error('notify:periodicidad error', ['error'=>$e->getMessage()]);
            return 1;
        }
    }
}
