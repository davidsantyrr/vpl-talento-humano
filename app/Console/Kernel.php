<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
{
    $schedule->command('notify:periodicidad')
        ->everyFifteenMinutes()
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/periodicidad.log'));

    $schedule->command('entregas:recordatorios-devolucion')
        ->everyFifteenMinutes()
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/entregas.log'));
}


    protected function commands(): void
    {
        // cargar comandos desde rutas/artisan if needed
        $this->load(__DIR__.'/Commands');
    }
}
