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

        if ($this->isFailed($response)) {
            $message = $this->getJson($response, 'message') ?? 'Credenciales inválidas.';
            return back()->withInput()->with(['errorMessage' => $message]);
        }

        $data = $this->getJson($response) ?: [];
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

        // Siempre redirigir a menú principal independientemente del rol
        return redirect('/menus/menu');
    }

    public function logout(Request $request)
    {
        $baseUrl = rtrim(config('app.vpl_core') ?: env('VPL_CORE'), '/');
        $endpoint = $baseUrl . '/api/auth/logout';

        $token = session('auth.token');
        if ($token) {
            try {
                Http::withHeaders(['Authorization' => $token])
                    ->acceptJson()
                    ->post($endpoint);
            } catch (\Throwable $e) {
                Log::warning('VPL logout error', ['error' => $e->getMessage()]);
            }
        }

        // Clear session and cookie
        Cookie::queue(Cookie::forget('auth_token'));
        $request->session()->forget(['auth.user', 'auth.token']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Sesión cerrada correctamente.');
    }

    // Helpers para compatibilidad con diferentes versiones del cliente HTTP
    private function isFailed($response): bool
    {
        if (is_object($response)) {
            if (method_exists($response, 'failed')) {
                return (bool) $response->failed();
            }
            if (method_exists($response, 'status')) {
                return (int) $response->status() >= 400;
            }
        }
        // Si no es un objeto esperado, considerar fallo
        return true;
    }

    private function getJson($response, ?string $key = null)
    {
        if (is_object($response) && method_exists($response, 'json')) {
            $data = $response->json();
        } elseif (is_object($response) && method_exists($response, 'body')) {
            $data = json_decode($response->body(), true) ?: [];
        } else {
            $data = [];
        }
        return $key ? ($data[$key] ?? null) : $data;
    }
}
