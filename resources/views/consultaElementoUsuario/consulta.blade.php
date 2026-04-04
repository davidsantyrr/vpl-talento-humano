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

/* Modal entregas anteriores */
.modal-entregas-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.modal-entregas-overlay.active {
    display: flex;
}
.modal-entregas-content {
    background: white;
    border-radius: 12px;
    width: 95%;
    max-width: 1400px;
    height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-entregas-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}
.modal-entregas-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #1f2937;
}
.modal-entregas-body {
    display: flex;
    flex: 1;
    overflow: hidden;
}
.entregas-lista {
    width: 350px;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    background: #f9fafb;
}
.entregas-search {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
}
.entregas-search input {
    width: 100%;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
}
.entregas-items {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}
.entrega-item {
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    background: white;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
}
.entrega-item:hover {
    background: #eff6ff;
    border-color: #3b82f6;
}
.entrega-item.active {
    background: #dbeafe;
    border-color: #3b82f6;
}
.entrega-item .fecha {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}
.entrega-item .tipo {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}
.entrega-item .elementos {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pdf-preview {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #1f2937;
}
.pdf-preview-placeholder {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 1.1rem;
}
.pdf-preview iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.btn-close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}
.btn-close-modal:hover {
    background: #e5e7eb;
    color: #1f2937;
}
.no-entregas {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}
.loading-entregas {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}
.btn-ver-entregas {
    background: #8b5cf6;
    color: white;
    border: none;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-ver-entregas:hover {
    background: #7c3aed;
}
.pdf-no-disponible {
    color: #ef4444;
    font-size: 0.8rem;
    margin-top: 4px;
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
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <div>
                            <strong>Usuario:</strong> {{ trim($usuario_info->nombres . ' ' . $usuario_info->apellidos) }}
                        </div>
                        <button type="button" class="btn-ver-entregas" onclick="abrirModalEntregas('{{ $usuario_info->numero_documento }}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                            </svg>
                            Ver entregas anteriores
                        </button>
                    </div>
                @elseif(request('usuario'))
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <span class="text-muted">Usuario no encontrado en el registro, mostrando resultados por número de documento.</span>
                        <button type="button" class="btn-ver-entregas" onclick="abrirModalEntregas('{{ request('usuario') }}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                            </svg>
                            Ver entregas anteriores
                        </button>
                    </div>
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

    <!-- Modal de Entregas Anteriores -->
    <div class="modal-entregas-overlay" id="modalEntregasAnteriores">
        <div class="modal-entregas-content">
            <div class="modal-entregas-header">
                <h3>📄 Entregas Anteriores</h3>
                <button type="button" class="btn-close-modal" onclick="cerrarModalEntregas()">&times;</button>
            </div>
            <div class="modal-entregas-body">
                <div class="entregas-lista">
                    <div class="entregas-search">
                        <input type="date" id="filtroFechaEntrega" placeholder="Filtrar por fecha" onchange="filtrarEntregasPorFecha()">
                    </div>
                    <div class="entregas-items" id="listaEntregas">
                        <div class="loading-entregas">Cargando entregas...</div>
                    </div>
                </div>
                <div class="pdf-preview" id="pdfPreviewContainer">
                    <div class="pdf-preview-placeholder">
                        Seleccione una entrega para ver el PDF
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
let documentoActual = '';
let entregasData = [];

function abrirModalEntregas(documento) {
    documentoActual = documento;
    document.getElementById('modalEntregasAnteriores').classList.add('active');
    document.getElementById('filtroFechaEntrega').value = '';
    cargarEntregas(documento);
}

function cerrarModalEntregas() {
    document.getElementById('modalEntregasAnteriores').classList.remove('active');
    document.getElementById('pdfPreviewContainer').innerHTML = '<div class="pdf-preview-placeholder">Seleccione una entrega para ver el PDF</div>';
}

async function cargarEntregas(documento, fecha = '') {
    const lista = document.getElementById('listaEntregas');
    lista.innerHTML = '<div class="loading-entregas">Cargando entregas...</div>';
    
    try {
        let url = `{{ url('/consulta-elementos/entregas-anteriores') }}?documento=${encodeURIComponent(documento)}`;
        if (fecha) {
            url += `&fecha=${encodeURIComponent(fecha)}`;
        }
        
        const resp = await fetch(url);
        const data = await resp.json();
        
        entregasData = data.entregas || [];
        
        if (entregasData.length === 0) {
            lista.innerHTML = '<div class="no-entregas">No se encontraron entregas para este usuario</div>';
            return;
        }
        
        renderizarListaEntregas();
        
    } catch (error) {
        console.error('Error cargando entregas:', error);
        lista.innerHTML = '<div class="no-entregas">Error al cargar las entregas</div>';
    }
}

function renderizarListaEntregas() {
    const lista = document.getElementById('listaEntregas');
    
    if (entregasData.length === 0) {
        lista.innerHTML = '<div class="no-entregas">No se encontraron entregas</div>';
        return;
    }
    
    lista.innerHTML = entregasData.map((e, idx) => {
        const elementosTexto = e.elementos.map(el => el.nombre || el.sku).join(', ');
        const tienePdf = e.pdf_url ? true : false;
        
        return `
            <div class="entrega-item" data-idx="${idx}" onclick="seleccionarEntrega(${idx})">
                <div class="fecha">${e.fecha_corta}</div>
                <div class="tipo">Tipo: ${e.tipo_entrega} | Por: ${e.entrega_user}</div>
                <div class="elementos" title="${elementosTexto}">${elementosTexto || 'Sin elementos'}</div>
                ${!tienePdf ? '<div class="pdf-no-disponible">⚠️ PDF no disponible</div>' : ''}
            </div>
        `;
    }).join('');
}

function filtrarEntregasPorFecha() {
    const fecha = document.getElementById('filtroFechaEntrega').value;
    cargarEntregas(documentoActual, fecha);
}

function seleccionarEntrega(idx) {
    // Marcar como activo
    document.querySelectorAll('.entrega-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.entrega-item[data-idx="${idx}"]`)?.classList.add('active');
    
    const entrega = entregasData[idx];
    const container = document.getElementById('pdfPreviewContainer');
    
    if (entrega.pdf_url) {
        container.innerHTML = `<iframe src="${entrega.pdf_url}" title="Vista previa PDF"></iframe>`;
    } else {
        container.innerHTML = `
            <div class="pdf-preview-placeholder">
                <div style="text-align: center;">
                    <p style="margin-bottom: 1rem;">⚠️ El PDF de esta entrega no está disponible</p>
                    <p style="font-size: 0.9rem; color: #6b7280;">
                        Fecha: ${entrega.fecha}<br>
                        Tipo: ${entrega.tipo_entrega}<br>
                        Elementos: ${entrega.elementos.map(e => e.nombre || e.sku).join(', ')}
                    </p>
                </div>
            </div>
        `;
    }
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalEntregas();
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalEntregasAnteriores')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalEntregas();
    }
});
</script>
@endpush