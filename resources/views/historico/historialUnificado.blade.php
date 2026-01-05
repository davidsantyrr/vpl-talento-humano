@extends('layouts.app')
@section('title','Historial de Entregas y Recepciones')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/historico/historialUnificado.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente />

<div class="historial-container">
	<header class="historial-header">
		<h1>Historial de Entregas y Recepciones</h1>
		<div class="controls">
			<form method="GET" class="filters">
				<input type="search" name="q" placeholder="Buscar por nombre o documento" value="{{ request('q') }}">
				<select name="operacion">
					<option value="">Todas las operaciones</option>
					@foreach($operations ?? [] as $op)
						<option value="{{ $op->id }}" {{ request('operacion') == $op->id ? 'selected' : '' }}>{{ $op->operationName }}</option>
					@endforeach
				</select>
				<select name="tipo_registro">
					<option value="">Todos los tipos</option>
					<option value="entrega" {{ request('tipo_registro') == 'entrega' ? 'selected' : '' }}>Solo Entregas</option>
					<option value="recepcion" {{ request('tipo_registro') == 'recepcion' ? 'selected' : '' }}>Solo Recepciones</option>
				</select>
				<button class="btn primary" type="submit">Filtrar</button>
				<a class="btn secondary" href="{{ route('historial.unificado') }}">Limpiar</a>
			</form>
		</div>
	</header>

	<main class="historial-main">
		<div class="table-wrap">
			<table class="historial-table">
				<thead>
					<tr>
						<th>Tipo</th>
						<th>Fecha</th>
						<th>Tipo Doc.</th>
						<th>Documento</th>
						<th>Nombre</th>
						<th>Operación</th>
						<th>Subtipo</th>
						<th>Elementos</th>
						<th>Estado</th>
						<th>Acciones</th>
					</tr>
				</thead>
				<tbody>
					@forelse($paginatedRegistros ?? [] as $registro)
						<tr>
							<td>
								<span class="badge {{ $registro->registro_tipo === 'entrega' ? 'badge-entrega' : 'badge-recepcion' }}">
									{{ $registro->registro_tipo === 'entrega' ? 'Entrega' : 'Recepción' }}
								</span>
							</td>
							<td>{{ \Carbon\Carbon::parse($registro->created_at)->format('Y-m-d H:i') }}</td>
							<td>{{ $registro->tipo_documento ?? '-' }}</td>
							<td>{{ $registro->numero_documento ?? '-' }}</td>
							<td>{{ trim(($registro->nombres ?? '') . ' ' . ($registro->apellidos ?? '')) ?: '-' }}</td>
							<td>{{ $registro->operacion ?? '-' }}</td>
							<td>
								<span class="badge badge-tipo">
									{{ ucfirst($registro->tipo ?? '-') }}
								</span>
							</td>
							<td class="text-center">
								<span class="elementos-count" title="{{ $registro->elementos->map(fn($e) => $e->sku . ' (' . $e->cantidad . ')')->join(', ') }}">
									{{ $registro->elementos->count() ?? 0 }}
								</span>
							</td>
							<td>
								@if($registro->registro_tipo === 'entrega')
									<span class="badge {{ $registro->recibido ? 'badge-success' : 'badge-warning' }}">
										{{ $registro->recibido ? 'Recibido' : 'Pendiente' }}
									</span>
								@else
									<span class="badge {{ $registro->recibido ? 'badge-success' : 'badge-warning' }}">
										{{ $registro->recibido ? 'Entregado' : 'Pendiente' }}
									</span>
								@endif
							</td>
							<td>
								<div class="actions-row">
									<button class="btn small" onclick="verDetalle('{{ $registro->registro_tipo }}', {{ $registro->id }})">Ver</button>
									<button class="btn small primary" onclick="descargarPDF('{{ $registro->registro_tipo }}', {{ $registro->id }})">PDF</button>
								</div>
							</td>
						</tr>
					@empty
						<tr><td colspan="10" style="text-align:center;color:#6c757d;padding:40px;">No se encontraron registros.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="pagination-row">
			{{ $paginatedRegistros->links() }}
		</div>
	</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	const Toast = Swal.mixin({
		toast: true,
		position: 'top-end',
		showConfirmButton: false,
		timer: 3000,
		timerProgressBar: true,
	});

	@if(session('status'))
		Toast.fire({
			icon: 'success',
			title: @json(session('status'))
		});
	@endif

	function verDetalle(tipo, id) {
		Toast.fire({
			icon: 'info',
			title: `Ver detalle de ${tipo} #${id}`
		});
		// TODO: Implementar modal o redirección a página de detalle
	}

	function descargarPDF(tipo, id) {
		Toast.fire({
			icon: 'info',
			title: 'Generando PDF...'
		});
		// TODO: Implementar descarga de PDF
		// window.location.href = `/pdf/${tipo}/${id}`;
	}
</script>
@endsection
