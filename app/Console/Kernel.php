<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Ejecutar el chequeo de periodicidades cada hora
        $schedule->command('notify:periodicidad')->hourly();
    }

    protected function commands(): void
    {
        // cargar comandos desde rutas/artisan if needed
        $this->load(__DIR__.'/Commands');
    }
}
