<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class VplAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = session('auth.user');
        $token = session('auth.token');

        if (!$user || !$token) {
            // Redirigir a inicio con mensaje para el toast
            return redirect('/')
                ->with('errorMessage', 'Debes iniciar sesión para poder ingresar.');
        }

        // Validar rol permitido: hseq, talento humano, administrador
        if (!$this->userHasAllowedRole($user)) {
            $detected = $this->collectRoleStrings($user);
            $email = $this->extractEmail($user);
            $emailAllowed = $this->isEmailAllowed($email);
            Log::warning('VplAuth: acceso denegado por rol', [
                'user_sample' => is_array($user) ? array_intersect_key($user, array_flip(['id','email','name','role','rol','perfil'])) : $user,
                'detected_role_strings' => $detected,
                'email' => $email,
                'email_allowed' => $emailAllowed,
                'allowed_emails_config' => config('vpl.allowed_emails'),
                'allowed_domains_config' => config('vpl.allowed_domains'),
            ]);
            // Limpiar sesión/cookie y bloquear acceso
            Cookie::queue(Cookie::forget('auth_token'));
            $request->session()->forget(['auth.user', 'auth.token']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/')
                ->with('errorMessage', 'Tu perfil no tiene acceso a esta aplicación.');
        }

        View::share('authUser', $user);
        View::share('authToken', $token);

        $request->attributes->set('authUser', $user);
        $request->attributes->set('authToken', $token);

        return $next($request);
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) return null;
        $v = trim(mb_strtolower($value));
        $v = str_replace(['_', '-'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }

    private function userHasAllowedRole($user): bool
    {
        $values = $this->collectRoleStrings($user);
        foreach ($values as $v) {
            if ($this->matchesAllowed($v)) return true;
        }
        // Fallback: permitir por lista blanca de emails o dominios configurados
        $email = $this->extractEmail($user);
        if ($this->isEmailAllowed($email)) return true;

        return false;
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

    private function collectRoleStrings($user): array
    {
        $out = [];
        $push = function($val) use (&$out) {
            if (is_string($val)) $out[] = $this->normalize($val);
        };
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

    private function matchesAllowed(?string $value): bool
    {
        if ($value === null) return false;
        $v = str_replace(' ', '', $value); // comparar sin espacios
        return str_contains($v, 'hseq') || str_contains($v, 'talentohumano') || str_contains($v, 'talento') || str_contains($v, 'administrador') || str_contains($v, 'admin');
    }
}
