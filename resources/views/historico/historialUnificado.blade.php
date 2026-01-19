@extends('layouts.app')
@section('title','Historial de Entregas y Recepciones')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/historico/historialUnificado.css') }}">
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
									<a class="btn small primary" href="{{ route('historial.pdf', ['tipo'=>$registro->registro_tipo, 'id'=>$registro->id]) }}" target="_blank">PDF</a>
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
			@if(isset($paginatedRegistros) && method_exists($paginatedRegistros, 'lastPage') && $paginatedRegistros->lastPage() > 1)
				@php
					$start = max(1, $paginatedRegistros->currentPage() - 2);
					$end = min($paginatedRegistros->lastPage(), $paginatedRegistros->currentPage() + 2);
					$btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 12px;border-radius:999px;text-decoration:none;font-weight:600;font-size:14px;line-height:1;color:#111827;background:#f8fafc;border:1px solid rgba(0,0,0,0.08);';
					$active = 'background:var(--primary);color:#fff;border-color:var(--primary);';
					$disabled = 'opacity:.45;pointer-events:none;background:transparent;';
				@endphp
				<div class="pagination-inner" style="display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:1200px;margin:0 auto;width:100%;">
					<div class="pager-left">
						<form method="GET" action="{{ url()->current() }}" style="display:inline-flex;align-items:center;gap:8px;">
							<input type="hidden" name="q" value="{{ request('q') }}">
							<input type="hidden" name="operacion" value="{{ request('operacion') }}">
							<label style="font-weight:600;color:var(--gray-700);">Mostrar</label>
							<select name="per_page" onchange="this.form.submit()" style="padding:8px 10px;border-radius:8px;border:1px solid var(--gray-300);background:#fff;">
								@foreach([5,10,15,20,50] as $sz)
									<option value="{{ $sz }}" {{ (int)request('per_page',15) === $sz ? 'selected' : '' }}>{{ $sz }}</option>
								@endforeach
							</select>
							<span style="color:var(--gray-700);">por página</span>
						</form>
					</div>
					<div class="pager-right">
						<div style="display:flex;gap:8px;align-items:center;">
							@if($paginatedRegistros->onFirstPage())
								<span style="{{ $disabled }}{{ $btn }}">&laquo;&nbsp;Prev</span>
							@else
								<a style="{{ $btn }}" href="{{ $paginatedRegistros->previousPageUrl() }}">&laquo;&nbsp;Prev</a>
							@endif

							@if($start > 1)
								<a style="{{ $btn }}" href="{{ $paginatedRegistros->url(1) }}">1</a>
								@if($start > 2)
									<span style="padding:0 8px;color:var(--gray-500);">&hellip;</span>
								@endif
							@endif

							@for($i = $start; $i <= $end; $i++)
								@if($paginatedRegistros->currentPage() == $i)
									<span style="{{ $active }}{{ $btn }}">{{ $i }}</span>
								@else
									<a style="{{ $btn }}" href="{{ $paginatedRegistros->url($i) }}">{{ $i }}</a>
								@endif
							@endfor

							@if($end < $paginatedRegistros->lastPage())
								@if($end < $paginatedRegistros->lastPage() - 1)
									<span style="padding:0 8px;color:var(--gray-500);">&hellip;</span>
								@endif
								<a style="{{ $btn }}" href="{{ $paginatedRegistros->url($paginatedRegistros->lastPage()) }}">{{ $paginatedRegistros->lastPage() }}</a>
							@endif

							@if($paginatedRegistros->hasMorePages())
								<a style="{{ $btn }}" href="{{ $paginatedRegistros->nextPageUrl() }}">Next&nbsp;&raquo;</a>
							@else
								<span style="{{ $disabled }}{{ $btn }}">Next&nbsp;&raquo;</span>
							@endif
						</div>
					</div>
				</div>
			@endif
		</div>

		<style>
		.custom-paginacion {display:flex !important; justify-content:center !important; padding:12px 0 !important}
		.custom-paginacion .custom-pagination-list {list-style:none !important; display:flex !important; gap:8px !important; margin:0 !important; padding:0 !important; align-items:center !important}
		.custom-paginacion .custom-page {display:inline-flex !important}
		.custom-paginacion .custom-link, .custom-paginacion .custom-page span.custom-link {display:inline-flex !important; align-items:center !important; justify-content:center !important; min-width:36px !important; height:36px !important; padding:0 12px !important; border-radius:999px !important; text-decoration:none !important; font-weight:600 !important; font-size:14px !important; line-height:1 !important; color:var(--gray-700) !important; background:#f8fafc !important; border:1px solid rgba(0,0,0,0.08) !important}
		.custom-paginacion .custom-page.active span.custom-link {background:var(--primary) !important; color:#fff !important; border-color:transparent !important}
		.custom-paginacion .custom-page.disabled .custom-link, .custom-paginacion .custom-page.disabled span.custom-link {opacity:.45 !important; pointer-events:none !important}
		/* hide any pseudo or large icons */
		.custom-paginacion::before, .custom-paginacion::after, .custom-paginacion .custom-pagination-list::before, .custom-paginacion .custom-pagination-list::after {content:none !important; display:none !important}
		</style>

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
	@if(session('error'))
		window.HistorialToast.fire({
			icon: 'error',
			title: @json(session('error'))
		});
	@endif
</script>
@endsection
