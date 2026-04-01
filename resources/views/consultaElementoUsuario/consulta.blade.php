@extends('layouts.app')
@section('title', 'Consulta de Elementos por Usuario')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/consultaElementoUsuario/consultaElementoUsuario.css') }}">
<style>
/* botones compactos con misma altura */
.compact-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	height: 36px;
	padding: .25rem .6rem;
}
</style>
@endpush

@section('content')
<x-NavEntregasComponente/>
    <div class="container-fluid">
        <h1 class="mt-4">Consulta de Elementos por Usuario</h1>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('consultaElementoUsuario.consulta') }}">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input type="text" name="usuario" id="usuario" class="form-control" value="{{ request('usuario') }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm compact-btn me-2">Buscar</button>
                            <a href="{{ route('consultaElementoUsuario.consulta') }}" class="btn btn-primary btn-sm compact-btn me-2">Limpiar</a>
                            <a href="{{ route('elementoPeriodicidad.index') }}" class="btn btn-primary btn-sm compact-btn me-2">elementos a entregar</a>
                        </div>
                    </div>
                </form>
                @if(isset($usuario_info) && $usuario_info)
                    <div class="mb-3">
                        <strong>Usuario:</strong> {{ trim($usuario_info->nombres . ' ' . $usuario_info->apellidos) }}
                        
                    </div>
                @elseif(request('usuario'))
                    <div class="mb-3 text-muted">Usuario no encontrado en el registro, mostrando resultados por número de documento.</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Elemento</th>
                                <th style="width:120px;">Cantidad</th>
                                <th style="width:160px;">Última entrega</th>
                                <th style="width:160px;">Próxima entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resultados as $resultado)
                                @php
                                    $sku = $resultado->elemento;
                                    $period = \DB::table('periodicidad')->where('sku', $sku)->first();
                                    $next = null;
                                    if ($period) {
                                        $base = $resultado->ultima_entrega ?? $resultado->ultima_recepcion ?? null;
                                        if ($base) {
                                            try {
                                                $dt = \Carbon\Carbon::parse($base);
                                                switch ($period->periodicidad) {
                                                    case '1_mes': $dt->addMonth(); break;
                                                    case '3_meses': $dt->addMonths(3); break;
                                                    case '6_meses': $dt->addMonths(6); break;
                                                    case '12_meses': $dt->addYear(); break;
                                                    default: $dt = null; break;
                                                }
                                                if ($dt) $next = $dt->format('Y-m-d');
                                            } catch (Exception $e) { $next = null; }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $resultado->elemento }}</div>
                                        @if(!empty($resultado->elemento_nombre))
                                            <div class="text-muted small">{{ $resultado->elemento_nombre }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $resultado->cantidad }}</td>
                                    <td class="text-center">{{ $resultado->ultima_entrega ?? '-' }}</td>
                                    <td class="text-center">
                                        @if($next)
                                            <span class="badge bg-primary">{{ $next }}</span>
                                        @else
                                            @if($period)
                                                <span class="text-muted">Sin historial</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection