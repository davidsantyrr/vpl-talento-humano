<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar esquema HTTPS globalmente cuando se requiera.
        // Controlado por la variable de entorno FORCE_HTTPS (por defecto true para este entorno).
        // Si necesitas desactivarlo en local, pon FORCE_HTTPS=false en tu .env.
        if (env('FORCE_HTTPS', true) || env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }
    }
}
