@extends('layouts.app')
@section('title','Historial de Entregas y Recepciones')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/historico/historialUnificado.css') }}?v={{ time() }}">
@endpush

@section('content')
<x-NavEntregasComponente />

<div class="historial-container">
	<header class="historial-header">
		<div class="header-top">
			<h1>Historial de Entregas y Recepciones</h1>
			<button class="btn primary" onclick="abrirModalDescargaMasiva()">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 6px;">
					<path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
					<path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
				</svg>
				Descarga Masiva
			</button>
		</div>
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
							<td>
								@if($registro->registro_tipo === 'entrega')
									@if(in_array($registro->tipo, ['periodica', 'primera vez']))
										<span class="badge badge-success">
											Completado
										</span>
									@else
										<span class="badge {{ $registro->recibido ? 'badge-success' : 'badge-warning' }}">
											{{ $registro->recibido ? 'Recibido' : 'Pendiente' }}
										</span>
									@endif
								@else
									<span class="badge {{ $registro->recibido ? 'badge-success' : 'badge-warning' }}">
										{{ $registro->recibido ? 'Entregado' : 'Pendiente' }}
									</span>
								@endif
							</td>
							<td>
								<div class="actions-row">
									<button class="btn small" onclick='verDetalle(@json($registro))'>Ver</button>
									<button class="btn small primary" onclick="descargarPDF('{{ $registro->registro_tipo }}', {{ $registro->id }})">PDF</button>
								</div>
							</td>
						</tr>
					@empty
						<tr><td colspan="9" style="text-align:center;color:#6c757d;padding:40px;">No se encontraron registros.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="pagination-row">
			{{ $paginatedRegistros->links() }}
		</div>
	</main>
</div>

<!-- Modal de Descarga Masiva -->
<div class="modal" id="modalDescargaMasiva">
	<div class="modal-content modal-content-descarga">
		<div class="modal-header">
			<h2>Descarga Masiva de Registros</h2>
			<button class="modal-close" onclick="cerrarModalDescargaMasiva()">&times;</button>
		</div>
		<div class="modal-body">
			<form id="formDescargaMasiva" onsubmit="procesarDescargaMasiva(event)">
				<div class="form-grid">
					<div class="form-field">
						<label for="descargaTipo">Tipo de Registro</label>
						<select id="descargaTipo" name="tipo_registro" required>
							<option value="">Seleccione un tipo</option>
							<option value="entrega">Entregas</option>
							<option value="recepcion">Recepciones</option>
							<option value="todos">Todos</option>
						</select>
					</div>
					<div class="form-field">
						<label for="descargaOperacion">Operación</label>
						<select id="descargaOperacion" name="operacion_id">
							<option value="">Todas las operaciones</option>
							@foreach($operations ?? [] as $op)
								<option value="{{ $op->id }}">{{ $op->operationName }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-field">
						<label for="descargaFechaInicio">Fecha Inicio</label>
						<input type="date" id="descargaFechaInicio" name="fecha_inicio" max="{{ date('Y-m-d') }}" required>
					</div>
					<div class="form-field">
						<label for="descargaFechaFin">Fecha Fin</label>
						<input type="date" id="descargaFechaFin" name="fecha_fin" max="{{ date('Y-m-d') }}" required>
					</div>
				</div>
				<div class="info-box">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
						<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
						<path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
					</svg>
					<span>Se generará un archivo PDF con todos los registros que cumplan los criterios seleccionados.</span>
				</div>
			</form>
		</div>
		<div class="modal-footer">
			<button class="btn secondary" type="button" onclick="cerrarModalDescargaMasiva()">Cancelar</button>
			<button class="btn primary" type="submit" form="formDescargaMasiva">
				<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 6px;">
					<path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
					<path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
				</svg>
				Descargar
			</button>
		</div>
	</div>
</div>

<!-- Modal de Detalle -->
<div class="modal" id="modalDetalle">
	<div class="modal-content">
		<div class="modal-header">
			<h2 id="modalTitulo">Detalle del Registro</h2>
			<button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
		</div>
		<div class="modal-body">
			<div class="detalle-grid">
				<div class="detalle-item">
					<strong>Tipo:</strong>
					<span id="detalleTipo"></span>
				</div>
				<div class="detalle-item">
					<strong>Subtipo:</strong>
					<span id="detalleSubtipo"></span>
				</div>
				<div class="detalle-item">
					<strong>Fecha:</strong>
					<span id="detalleFecha"></span>
				</div>
				<div class="detalle-item">
					<strong>Estado:</strong>
					<span id="detalleEstado"></span>
				</div>
				<div class="detalle-item">
					<strong>Tipo Documento:</strong>
					<span id="detalleTipoDoc"></span>
				</div>
				<div class="detalle-item">
					<strong>Número Documento:</strong>
					<span id="detalleNumDoc"></span>
				</div>
				<div class="detalle-item span-2">
					<strong>Nombre Completo:</strong>
					<span id="detalleNombre"></span>
				</div>
				<div class="detalle-item span-2">
					<strong>Operación:</strong>
					<span id="detalleOperacion"></span>
				</div>
			</div>
			
			<div class="elementos-section">
				<h3>Elementos</h3>
				<div class="table-wrap">
					<table class="elementos-table">
						<thead>
							<tr>
								<th>SKU</th>
								<th>Cantidad</th>
							</tr>
						</thead>
						<tbody id="detalleElementosTbody">
							<tr>
								<td colspan="2" style="text-align:center;padding:20px;">Cargando...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button class="btn secondary" onclick="cerrarModalDetalle()">Cerrar</button>
			<button class="btn primary" onclick="descargarPDFDesdeModal()">Descargar PDF</button>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/historial/historial.js') }}?v={{ time() }}"></script>
<script>
	// Mostrar toast de sesión si existe
	@if(session('status'))
		window.HistorialToast.fire({
			icon: 'success',
			title: @json(session('status'))
		});
	@endif
</script>
@endsection
