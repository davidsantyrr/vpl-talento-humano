
@extends('layouts.app')
@section('title','Historial de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ asset('entregas/historialEntregas.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente />

<div class="historial-container">
	<header class="historial-header">
		<h1>Historial de Entregas</h1>
		<div class="controls">
			<form method="GET" class="filters" style="display:flex;gap:8px;align-items:center;">
				<input type="search" name="q" placeholder="Buscar por nombre, documento o elemento" value="{{ request('q') }}">
				<select name="operacion">
					<option value="">Todas las operaciones</option>
					@foreach($operations ?? [] as $op)
						<option value="{{ $op->id }}" {{ request('operacion') == $op->id ? 'selected' : '' }}>{{ $op->operationName }}</option>
					@endforeach
				</select>
				<button class="btn" type="submit">Filtrar</button>
				<a class="btn ghost" href="{{ route('entregas.index') }}">Limpiar</a>
			</form>
		</div>
	</header>

	<main class="historial-main">
		<div class="table-wrap">
			<table class="historial-table">
				<thead>
					<tr>
						<th>Fecha</th>
						<th>Documento</th>
						<th>Nombre</th>
						<th>Operación</th>
						<th>Elementos</th>
						<th>Firma</th>
						<th>Acciones</th>
					</tr>
				</thead>
				<tbody>
					@forelse($entregas ?? [] as $e)
						<tr>
							<td>{{ optional($e->created_at)->format('Y-m-d') ?? '-' }}</td>
							<td>{{ $e->usuario->numero_documento ?? '-' }}</td>
							<td>{{ trim(($e->usuario->nombres ?? '') . ' ' . ($e->usuario->apellidos ?? '')) ?: '-' }}</td>
							<td>{{ $e->operacion->operationName ?? ($e->operacion_id ?? '-') }}</td>
							<td class="text-center">{{ $e->elementos->count() ?? 0 }}</td>
							<td class="firma-cell">-</td>
							<td>
								<div class="actions-row">
									<a href="{{ route('entregas.show', $e->id ?? 0) }}" class="btn small">Ver</a>
									<a href="{{ route('entregas.download', $e->id ?? 0) }}" class="btn small">PDF</a>
								</div>
							</td>
						</tr>
					@empty
						<tr><td colspan="7" style="text-align:center;color:var(--muted)">No se encontraron entregas.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="pagination-row">
			@if(method_exists($entregas ?? collect(), 'links'))
				{{ $entregas->links() }}
			@endif
		</div>
	</main>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	(function(){
		@if(session('status'))
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'success',
				title: @json(session('status')),
				showConfirmButton: false,
				timer: 3000,
			});
		@endif
	})();
</script>
@endpush
