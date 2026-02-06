@extends('layouts.app')
@section('title','Historial de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ secure_asset('css/historico/historialUnificado.css') }}">
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
						<th>Realizó</th>
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
							<td class="firma-cell">
								@if(!empty($e->comprobante_path))
									@php
										// esperar formato: {dir}/{file}
										$parts = explode('/', $e->comprobante_path);
										$dir = $parts[0] ?? '';
										$file = $parts[1] ?? ($parts[count($parts)-1] ?? '');
									@endphp
									@if($dir && $file)
										<a href="{{ route('comprobantes.download', [$dir, $file]) }}" class="btn small" target="_blank">Descargar</a>
									@else
										- 
									@endif
								@else
									-
								@endif
							</td>
							<td>{{ $e->entrega_user ?? '-' }}</td>
							<td>
								<div class="actions-row">
									<a href="{{ route('entregas.show', $e->id ?? 0) }}" class="btn small">Ver</a>
									@if(!empty($e->comprobante_path))
										@php
											$parts = explode('/', $e->comprobante_path);
											$dir = $parts[0] ?? '';
											$file = $parts[1] ?? ($parts[count($parts)-1] ?? '');
										@endphp
										@if($dir && $file)
											<a href="{{ route('comprobantes.download', [$dir, $file]) }}" class="btn small" target="_blank">PDF</a>
										@else
											<a href="#" class="btn small disabled">PDF</a>
										@endif
									@else
										<a href="#" class="btn small disabled">PDF</a>
									@endif
								</div>
							</td>
						</tr>
					@empty
						<tr><td colspan="8" style="text-align:center;color:var(--muted)">No se encontraron entregas.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="pagination-row">
			@if(isset($entregas) && method_exists($entregas, 'lastPage') && $entregas->lastPage() > 1)
				@php
					$start = max(1, $entregas->currentPage() - 2);
					$end = min($entregas->lastPage(), $entregas->currentPage() + 2);
					$btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 12px;border-radius:999px;text-decoration:none;font-weight:600;font-size:14px;line-height:1;color:#111827;background:#f8fafc;border:1px solid rgba(0,0,0,0.08);';
					$active = 'background:var(--primary);color:#fff;border-color:var(--primary);';
					$disabled = 'opacity:.45;pointer-events:none;background:transparent;';
				@endphp
				<div class="pagination-inner" style="display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:1200px;margin:0 auto;width:100%;">
					<div class="pager-left">
						<form method="GET" action="{{ route('entregas.index') }}" style="display:inline-flex;align-items:center;gap:8px;">
							<input type="hidden" name="q" value="{{ request('q') }}">
							<input type="hidden" name="operacion" value="{{ request('operacion') }}">
							<label style="font-weight:600;color:var(--gray-700);">Mostrar</label>
							<select name="per_page" onchange="this.form.submit()" style="padding:8px 10px;border-radius:8px;border:1px solid var(--gray-300);background:#fff;">
								@foreach([5,10,15,20,50] as $sz)
									<option value="{{ $sz }}" {{ (int)request('per_page',10) === $sz ? 'selected' : '' }}>{{ $sz }}</option>
								@endforeach
							</select>
							<span style="color:var(--gray-700);">por página</span>
						</form>
					</div>
					<div class="pager-right">
						<div style="display:flex;gap:8px;align-items:center;">
							@if($entregas->onFirstPage())
								<span style="{{ $disabled }}{{ $btn }}">&laquo;&nbsp;Prev</span>
							@else
								<a style="{{ $btn }}" href="{{ $entregas->previousPageUrl() }}">&laquo;&nbsp;Prev</a>
							@endif

							@if($start > 1)
								<a style="{{ $btn }}" href="{{ $entregas->url(1) }}">1</a>
								@if($start > 2)
									<span style="padding:0 8px;color:var(--gray-500);">&hellip;</span>
								@endif
							@endif

							@for($i = $start; $i <= $end; $i++)
								@if($entregas->currentPage() == $i)
									<span style="{{ $active }}{{ $btn }}">{{ $i }}</span>
								@else
									<a style="{{ $btn }}" href="{{ $entregas->url($i) }}">{{ $i }}</a>
								@endif
							@endfor

							@if($end < $entregas->lastPage())
								@if($end < $entregas->lastPage() - 1)
									<span style="padding:0 8px;color:var(--gray-500);">&hellip;</span>
								@endif
								<a style="{{ $btn }}" href="{{ $entregas->url($entregas->lastPage()) }}">{{ $entregas->lastPage() }}</a>
							@endif

							@if($entregas->hasMorePages())
								<a style="{{ $btn }}" href="{{ $entregas->nextPageUrl() }}">Next&nbsp;&raquo;</a>
							@else
								<span style="{{ $disabled }}{{ $btn }}">Next&nbsp;&raquo;</span>
							@endif
						</div>
					</div>
				</div>
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
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('a[href*="/comprobantes/"]').forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            var href = a.getAttribute('href');
            // enviar beacon para registrar intento (no bloqueante)
            try {
                navigator.sendBeacon('/_log_comprobante_hit', JSON.stringify({href: href, ts: Date.now()}));
            } catch(err) {
                // fallback: petición fetch
                fetch('/_log_comprobante_hit', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({href:href,ts:Date.now()})});
            }
            // navegar a la URL real
            window.location = href;
        });
    });
});
</script>
@endpush
