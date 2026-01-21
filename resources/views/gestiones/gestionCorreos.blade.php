@extends('layouts.app')

@section('title', 'Gestión de Correos')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionCorreos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
    <div class="container-fluid">
        <h1 class="mt-4">Gestión de Correos</h1>
        <div class="card mb-4">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                {{-- Formulario para agregar nuevo correo --}}
                <form method="POST" action="{{ route('gestionCorreos.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Rol</label>
                        @php
                            $selectedRol = old('rol') ?? (isset($selectedRol) ? $selectedRol : '');
                            $user = $authUser ?? session('auth.user') ?? null;
                            if (!$selectedRol && $user) {
                                $candidate = null;
                                $u = is_object($user) ? (array) $user : (array) $user;
                                // Try several common keys and nested structures
                                foreach (['rol','role','perfil','perfiles','roles','name'] as $k) {
                                    if (!isset($u[$k])) continue;
                                    $v = $u[$k];
                                    if (is_string($v) && trim($v) !== '') { $candidate = $v; break; }
                                    if (is_array($v) && count($v)) {
                                        $first = $v[0];
                                        if (is_string($first) && trim($first) !== '') { $candidate = $first; break; }
                                        if (is_object($first) || is_array($first)) {
                                            $fa = is_object($first) ? (array) $first : $first;
                                            foreach (['roles','rol','name','perfil'] as $kk) {
                                                if (isset($fa[$kk]) && is_string($fa[$kk]) && trim($fa[$kk]) !== '') { $candidate = $fa[$kk]; break 2; }
                                            }
                                        }
                                    }
                                }
                                if ($candidate) { $selectedRol = $candidate; }
                            }
                        @endphp

                        @if(!empty($selectedRol))
                            <input type="text" class="form-control" value="{{ $selectedRol }}" readonly>
                            <input type="hidden" name="rol" value="{{ $selectedRol }}">
                        @else
                            <select name="rol" class="form-select" required>
                                <option value="">-- Seleccione un rol --</option>
                                @foreach($rolesDisponibles as $rol)
                                    @php
                                        $isSelected = false;
                                        if ($selectedRol) {
                                            $s = mb_strtolower(trim($selectedRol));
                                            $r = mb_strtolower(trim($rol));
                                            if (strpos($s, $r) !== false || strpos($r, $s) !== false) {
                                                $isSelected = true;
                                            }
                                        }
                                    @endphp
                                    <option value="{{ $rol }}" {{ $isSelected ? 'selected' : '' }}>{{ $rol }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('rol') <div class="text-danger small">{{ $message }}</div> @enderror
                        <small class="text-muted">Los roles provienen de las periodicidades configuradas</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" value="{{ old('correo') }}" required maxlength="191">
                        @error('correo') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Agregar correo</button>
                    </div>
                </form>

                {{-- Tabla de correos existentes --}}
                <div class="table-responsive mt-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Correo</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($correos as $c)
                                <tr>
                                    <td>{{ $c->rol }}</td>
                                    <td>{{ $c->correo }}</td>
                                    <td>{{ optional($c->created_at)->toDateTimeString() }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('gestionCorreos.edit', $c->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form method="POST" action="{{ route('gestionCorreos.destroy', $c->id) }}" style="display:inline-block" onsubmit="return confirm('Eliminar este correo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No hay correos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="mt-3">
                    @if(method_exists($correos, 'links'))
                        {{ $correos->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection