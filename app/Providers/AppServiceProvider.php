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
        // Forzar esquema HTTPS cuando la app no esté en entorno local
        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }
    }
}
