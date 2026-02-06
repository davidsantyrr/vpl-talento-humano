@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ secure_asset('css/menus/styleMenu.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
@endpush

@section('content')
    @php
        $user = Auth::user();

        // Build a roles array from several possible sources (User property, session, or serialized user)
        $rolesArray = [];
        if ($user) {
            if (is_array($user->roles ?? null)) {
                $rolesArray = $user->roles;
            } elseif (!empty($user->roles) && is_string($user->roles)) {
                // roles stored as comma separated string
                $rolesArray = array_map('trim', explode(',', $user->roles));
            }
        }

        // fallback to session-stored roles (some auth flows store roles in session)
        if (empty($rolesArray) && session()->has('roles')) {
            $sess = session('roles');
            if (is_array($sess)) $rolesArray = $sess;
            elseif (is_string($sess)) $rolesArray = array_map('trim', explode(',', $sess));
        }

        // fallback to auth payload saved in session by Api login (session('auth.user'))
        if (empty($rolesArray) && session()->has('auth.user')) {
            $authUser = session('auth.user');
            if (is_array($authUser)) {
                if (!empty($authUser['roles']) && is_array($authUser['roles'])) $rolesArray = $authUser['roles'];
                elseif (!empty($authUser['roles']) && is_string($authUser['roles'])) $rolesArray = array_map('trim', explode(',', $authUser['roles']));
            } elseif (is_object($authUser)) {
                if (!empty($authUser->roles) && is_array($authUser->roles)) $rolesArray = $authUser->roles;
                elseif (!empty($authUser->roles) && is_string($authUser->roles)) $rolesArray = array_map('trim', explode(',', $authUser->roles));
            }
            // if still empty, inspect serialized payload for known substrings
            if (empty($rolesArray)) {
                $s = strtolower(json_encode($authUser));
                if (strpos($s, 'hseq') !== false) $rolesArray[] = 'hseq';
                if (strpos($s, 'talento') !== false) $rolesArray[] = 'talento';
                if (strpos($s, 'talentohumano') !== false) $rolesArray[] = 'talentohumano';
            }
        }

        // final fallback: serialize the user object and search for known role substrings
        if (empty($rolesArray) && $user) {
            $rolesSerialized = strtolower(json_encode($user));
            $rolesArray = [];
            if (strpos($rolesSerialized, 'hseq') !== false) $rolesArray[] = 'hseq';
            if (strpos($rolesSerialized, 'talento') !== false) $rolesArray[] = 'talento';
            if (strpos($rolesSerialized, 'talentohumano') !== false) $rolesArray[] = 'talentohumano';
        }

        // Sanitize rolesArray: extract a list of string role names from mixed structures
        $sanitized = [];
        foreach ($rolesArray as $r) {
            if (is_string($r)) {
                $sanitized[] = trim($r);
                continue;
            }
            if (is_array($r)) {
                foreach (['name','nombre','role','rol','slug','key','display_name'] as $k) {
                    if (isset($r[$k]) && is_scalar($r[$k])) {
                        $sanitized[] = trim((string)$r[$k]);
                        break;
                    }
                }
                continue;
            }
            if (is_object($r)) {
                foreach (['name','nombre','role','rol','slug','key','display_name'] as $k) {
                    if (isset($r->$k) && is_scalar($r->$k)) {
                        $sanitized[] = trim((string)$r->$k);
                        break;
                    }
                }
            }
        }

        $rolesLower = array_map('strtolower', array_filter($sanitized, function($v){ return $v !== null && $v !== ''; }));
        $isHseq = in_array('hseq', $rolesLower, true);
        $isTalento = in_array('talento', $rolesLower, true) || in_array('talentohumano', $rolesLower, true);

        // Additional safety: inspect serialized user string for role substrings
        if ($user) {
            $ujson = strtolower(json_encode($user));
            if (strpos($ujson, 'hseq') !== false) $isHseq = true;
            if (strpos($ujson, 'talento') !== false || strpos($ujson, 'talentohumano') !== false) $isTalento = true;
        }

        // Aggressive combined detection across multiple session sources
        $combined = '';
        try {
            $combined .= strtolower(json_encode(Auth::user()));
        } catch (	Throwable $e) {}
        try {
            $combined .= strtolower(json_encode(session('auth.user')));
        } catch (\Throwable $e) {}
        try {
            $sessRoles = session('roles');
            if (is_array($sessRoles)) $combined .= implode(' ', $sessRoles);
            elseif (is_string($sessRoles)) $combined .= $sessRoles;
        } catch (\Throwable $e) {}

        $hideCards = (strpos($combined, 'hseq') !== false) || (strpos($combined, 'talento') !== false) || (strpos($combined, 'talentohumano') !== false);
    @endphp

    <x-NavEntregasComponente />

    <div class="container">
        <div class="menu">
                <!-- Gestión de Correos -->
                <div class="card card--blue" role="article" aria-label="Gestión de Correos">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-envelope"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de Correos</h3>
                        <p class="card__subtitle">Administra las direcciones de correo asociadas a los roles del sistema</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionCorreos.index') }}">Ingresar</a></div>
                </div>

        <!-- Gestión de usuarios -->
                @if (! $isHseq)
                <div class="card card--lightblue" role="article" aria-label="Gestión de usuarios">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de usuarios</h3>
                        <p class="card__subtitle">Gestionar los nuevos usuarios</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionUsuario.index') }}">Ingresar</a></div>
                </div>
                @endif

        <!-- Gestión de areas -->
                @if (! empty($hideCards) ? false : (! $isTalento && ! $isHseq))
                <div class="card card--peach" role="article" aria-label="Gestión de áreas">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-building"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de áreas</h3>
                        <p class="card__subtitle">Administra las áreas de la organización</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionArea.index') }}">Ingresar</a></div>
                </div>
                @endif

        <!-- Gestión de operaciones -->
                @if (! empty($hideCards) ? false : (! $isTalento && ! $isHseq))
                <div class="card card--mint" role="article" aria-label="Gestión de operaciones">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-gear"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de operaciones</h3>
                        <p class="card__subtitle">Administra las operaciones de la organización</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionOperacion.index') }}">Ingresar</a></div>
                </div>
                @endif

                <!-- Gestión de periodicidad -->
                <div class="card card--lime" role="article" aria-label="Gestión de periodicidad">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-calendar-days"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de periodicidad</h3>
                        <p class="card__subtitle">Administra la periodicidad de las actividades y procesos</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionPeriodicidad.index') }}">Ingresar</a></div>
                </div>

       <!-- Gestión de Centro de costos -->
                @if (! empty($hideCards) ? false : (! $isTalento && ! $isHseq))
                <div class="card card--coral" role="article" aria-label="Gestión de Centro de costos">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de Centro de costos</h3>
                        <p class="card__subtitle">Administra los centros de costos de la organización</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionCentroCosto.index') }}">Ingresar</a></div>
                </div>
                @endif
        
                <!-- Gestión de notificaciones inventario -->
                <div class="card card--yellow" role="article" aria-label="Gestión de notificaciones inventario">
                    <div class="card__media" aria-hidden="true">
                        <div class="card__icon"><i class="fa-solid fa-bell"></i></div>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title">Gestión de notificaciones inventario</h3>
                        <p class="card__subtitle">Administra las notificaciones relacionadas con el inventario</p>
                    </div>
                    <div class="card__actions"><a class="btn" href="{{ route('gestionNotificacionesInventario.index') }}">Ingresar</a></div>
                </div>
        </div>
    </div>

        {{-- Quick client-side hide fallback for critical roles (immediate fix) --}}
        <script>
            (function(){
                var hide = {{ ($isHseq || $isTalento) ? 'true' : 'false' }};
                if(!hide) return;
                document.addEventListener('DOMContentLoaded', function(){
                    var selectors = ['[aria-label="Gestión de áreas"]','[aria-label="Gestión de operaciones"]','[aria-label="Gestión de Centro de costos"]'];
                    selectors.forEach(function(s){
                        document.querySelectorAll(s).forEach(function(el){ el.remove(); });
                    });
                });
            })();
        </script>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function(){
            const form = document.getElementById('logoutFormMenu');
            if(form){
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    Swal.fire({
                        title: '¿Cerrar sesión?',
                        text: 'Se cerrará tu sesión actual.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, cerrar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({ title: 'Cerrando sesión...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                            form.submit();
                        }
                    });
                });
            }
        })();
    </script>
    @endpush

@endsection