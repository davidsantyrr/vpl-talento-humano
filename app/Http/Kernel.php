<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\VplAuth; // importar el middleware

class Kernel extends HttpKernel
{
    // ...existing code...

    protected $routeMiddleware = [
        // ...existing code...
        'vpl.auth' => VplAuth::class,
    ];

    public array $middlewareAliases = [
        // ...existing code...
        'vpl.auth' => VplAuth::class,
    ];
}
