<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class VplAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('auth.user');
        $token = session('auth.token');

        if (!$user || !$token) {
            return redirect('/login');
        }

        View::share('authUser', $user);
        View::share('authToken', $token);

        $request->attributes->set('authUser', $user);
        $request->attributes->set('authToken', $token);

        return $next($request);
    }
}
