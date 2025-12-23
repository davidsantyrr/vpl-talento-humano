<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $baseUrl = rtrim(config('app.vpl_core') ?: env('VPL_CORE'), '/');
        $endpoint = $baseUrl . '/api/auth/login';

        try {
            $response = Http::asJson()->acceptJson()->post($endpoint, [
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
        } catch (\Throwable $e) {
            Log::error('VPL login error', ['error' => $e->getMessage()]);
            return back()->withInput()->with([
                'errorMessage' => 'No se pudo conectar con el servidor de autenticación.'
            ]);
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? 'Credenciales inválidas.';
            return back()->withInput()->with(['errorMessage' => $message]);
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $tokenType = $data['token_type'] ?? 'Bearer';
        $token = $accessToken ? ($tokenType . ' ' . $accessToken) : null;

        if (!$token || empty($data['user'])) {
            return back()->withInput()->with(['errorMessage' => 'Respuesta inválida del servidor de autenticación.']);
        }

        // Cookie httpOnly (1 día)
        Cookie::queue(Cookie::make('auth_token', $token, 60 * 24, '/', null, false, true, false, 'Lax'));

        // Persistir en sesión para middleware y vistas
        session([
            'auth.user' => $data['user'],
            'auth.token' => $token,
        ]);

        // Determinar redirección por rol usando mapa/switch
        $rolesRaw = collect($data['user']['roles'] ?? [])
            ->pluck('roles')
            ->filter();

        // Normalizar roles (minúsculas, sin espacios extra)
        $roles = $rolesRaw->map(fn($r) => strtolower(trim($r)))->unique();

        // Mapa de rutas por rol principal
        $roleRouteMap = [
            'talento_humano' => '/menu',
            'talento humano' => '/menu',
            'hseq' => '/menuentrega',
        ];

        $redirect = '/menu'; // ruta por defecto
        foreach ($roles as $role) {
            if (array_key_exists($role, $roleRouteMap)) {
                $redirect = $roleRouteMap[$role];
                break; // primera coincidencia
            }
        }

        return redirect($redirect);
    }
}
