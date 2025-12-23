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
            return back()->withInput()->with(
                ['errorMessage' => 'No se pudo conectar con el servidor de autenticación.']
            );
        }

        if ($response->successful()) {
            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $tokenType = $data['token_type'] ?? 'Bearer';
            $token = $accessToken ? ($tokenType . ' ' . $accessToken) : null;

            if (!$token || empty($data['user'])) {
                return back()->withInput()->with(['errorMessage' => 'Respuesta inválida del servidor de autenticación.']);
            }

            // Guardar cookie httpOnly (1 día)
            Cookie::queue(Cookie::make('auth_token', $token, 60 * 24, '/', null, false, true, false, 'Lax'));

            // Persistir en sesión para middleware y vistas
            session([
                'auth.user' => $data['user'],
                'auth.token' => $token,
            ]);

            // Determinar redirección por rol
            $roles = collect($data['user']['roles'] ?? [])
                ->pluck('roles')
                ->filter()
                ->map(fn($r) => strtolower(trim($r)));

            if ($roles->contains('talento_humano') || $roles->contains('talento humano')) {
                return redirect('/menu');
            }
            if ($roles->contains('hseq')) {
                return redirect('/menuentrega');
            }
            // Por defecto, enviar a /menu
            return redirect('/menu');
        }

        $message = $response->json('message') ?? 'Credenciales inválidas.';
        return back()->withInput()->with(['errorMessage' => $message]);
    }
}
