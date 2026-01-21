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

        // Registrar y validar roles detectados para diagnóstico
        $detectedRoles = $this->collectRoleStrings($data['user']);
        Log::info('VPL login: roles detectados', ['roles' => $detectedRoles, 'user_sample' => is_array($data['user']) ? array_intersect_key($data['user'], array_flip(['id','email','name'])) : $data['user']]);

        // Validar rol permitido antes de persistir sesión
        if (!$this->userHasAllowedRole($data['user'])) {
            $email = $this->extractEmail($data['user']);
            $emailAllowed = $this->isEmailAllowed($email);
            $msg = 'Tu perfil no tiene acceso a esta aplicación.';
            if (!empty($detectedRoles)) {
                $msg .= ' Rol(es) detectado(s): ' . implode(', ', $detectedRoles) . '.';
            }

            // Log detallado para diagnóstico
            Log::warning('VPL login: acceso denegado tras validar roles', [
                'detected_roles' => $detectedRoles,
                'email' => $email,
                'email_allowed' => $emailAllowed,
                'allowed_emails_config' => config('vpl.allowed_emails'),
                'allowed_domains_config' => config('vpl.allowed_domains'),
                'user_payload' => $data['user'],
            ]);

            // Intentar fallback por email/dominio si está configurado
            if ($email && $emailAllowed) {
                Log::info('VPL login: acceso permitido por whitelist de email/domain', ['email' => $email]);
                // permitir y continuar
            } else {
                return back()->withInput()->with(['errorMessage' => $msg]);
            }
        }

        // Cookie httpOnly (1 día)
        Cookie::queue(Cookie::make('auth_token', $token, 60 * 24, '/', null, false, true, false, 'Lax'));

        // Persistir en sesión para middleware y vistas
        session([
            'auth.user' => $data['user'],
            'auth.token' => $token,
        ]);
        // Regenerar ID de sesión para evitar pérdida de estado (fix login redirect)
        $request->session()->regenerate();

        // Redirigir a la ruta deseada o al menú
        return redirect()->intended('/menus/menu');
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

    private function normalize(?string $value): ?string
    {
        if ($value === null) return null;
        $v = trim(mb_strtolower($value));
        $v = str_replace(['_', '-'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }

    private function collectRoleStrings($user): array
    {
        $out = [];
        $push = function($val) use (&$out) { if (is_string($val)) $out[] = $this->normalize($val); };
        $candidates = ['role','rol','perfil','perfil_name','perfilNombre','role_name','nombre_rol','slug','key','codigo','tipo','tipo_rol','name','display_name','full_name','nombre','nombres','usuario'];
        if (is_array($user)) {
            foreach ($candidates as $k) if (isset($user[$k])) $push($user[$k]);
            if (isset($user['roles'])) {
                $r = $user['roles'];
                if (is_array($r)) foreach ($r as $item) {
                    if (is_string($item)) $push($item);
                    elseif (is_array($item)) foreach (['name','nombre','role','rol','roles','slug','key'] as $kk) if (isset($item[$kk])) $push($item[$kk]);
                    elseif (is_object($item)) foreach (['name','nombre','role','rol','roles','slug','key'] as $kk) if (isset($item->$kk)) $push($item->$kk);
                }
            }
        } elseif (is_object($user)) {
            foreach ($candidates as $k) if (isset($user->$k)) $push($user->$k);
            if (isset($user->roles) && is_array($user->roles)) foreach ($user->roles as $item) {
                if (is_string($item)) $push($item);
                elseif (is_object($item)) foreach (['name','nombre','role','rol','slug','key'] as $kk) if (isset($item->$kk)) $push($item->$kk);
            }
        }
        return array_values(array_filter(array_unique($out)));
    }

    private function extractEmail($user): ?string
    {
        if (is_array($user) && isset($user['email'])) return $user['email'];
        if (is_object($user) && isset($user->email)) return $user->email;
        return null;
    }

    private function isEmailAllowed(?string $email): bool
    {
        if (empty($email) || !is_string($email)) return false;
        $email = trim(mb_strtolower($email));
        $allowed = array_filter(array_map('trim', explode(',', config('vpl.allowed_emails', ''))));
        foreach ($allowed as $a) {
            if (mb_strtolower($a) === $email) return true;
        }
        $domain = strstr($email, '@');
        if ($domain !== false) {
            $domain = ltrim($domain, '@');
            $allowedDomains = array_filter(array_map('trim', explode(',', config('vpl.allowed_domains', ''))));
            foreach ($allowedDomains as $d) {
                if (mb_strtolower($d) === mb_strtolower($domain)) return true;
            }
        }
        return false;
    }

    private function matchesAllowed(?string $value): bool
    {
        if ($value === null) return false;
        $v = str_replace(' ', '', $value);
        return str_contains($v, 'hseq') || str_contains($v, 'talentohumano') || str_contains($v, 'talento') || str_contains($v, 'administrador') || str_contains($v, 'admin');
    }

    private function userHasAllowedRole($user): bool
    {
        $values = $this->collectRoleStrings($user);
        foreach ($values as $v) if ($this->matchesAllowed($v)) return true;
        return false;
    }
}
