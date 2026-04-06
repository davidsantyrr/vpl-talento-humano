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
.modal-entregas-anteriores .modal-dialog {
    max-width: 900px;
}
.modal-entregas-anteriores .modal-content {
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.modal-entregas-anteriores .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.25rem 1.5rem;
}
.modal-entregas-anteriores .modal-title {
    font-weight: 600;
    font-size: 1.1rem;
}
.modal-entregas-anteriores .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}
.modal-entregas-anteriores .btn-close:hover {
    opacity: 1;
}
.modal-entregas-anteriores .modal-body {
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
}

/* Timeline de entregas */
.entregas-timeline {
    position: relative;
    padding-left: 30px;
}
.entregas-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
}
.entrega-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding: 1rem 1.25rem;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 3px solid #667eea;
    transition: all 0.3s ease;
}
.entrega-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.entrega-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 1.25rem;
    width: 12px;
    height: 12px;
    background: #667eea;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
}
.entrega-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.entrega-fecha {
    font-weight: 600;
    color: #495057;
    font-size: 0.95rem;
}
.entrega-tipo {
    font-size: 0.75rem;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    background: #e3f2fd;
    color: #1976d2;
    font-weight: 500;
}
.entrega-responsable {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
.entrega-elementos {
    margin-top: 0.75rem;
}
.elemento-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: white;
    border: 1px solid #dee2e6;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.8rem;
    margin: 0.2rem;
}
.elemento-badge .sku {
    font-weight: 600;
    color: #495057;
}
.elemento-badge .cantidad {
    background: #667eea;
    color: white;
    padding: 0.1rem 0.4rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}
.entrega-actions {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #dee2e6;
}
.btn-ver-pdf {
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
}
.btn-ver-pdf i {
    margin-right: 0.35rem;
}
.no-entregas {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}
.no-entregas i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
.loading-entregas {
    text-align: center;
    padding: 3rem;
}
.loading-entregas .spinner-border {
    width: 3rem;
    height: 3rem;
    color: #667eea;
}

/* Filtro de fecha */
.filtro-fecha-container {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}
.filtro-fecha-container label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
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
                    <div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>Usuario:</strong> {{ trim($usuario_info->nombres . ' ' . $usuario_info->apellidos) }}
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEntregasAnteriores" data-documento="{{ request('usuario') }}">
                            <i class="fas fa-history"></i> Ver Entregas Anteriores
                        </button>
                    </div>
                @elseif(request('usuario'))
                    <div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Usuario no encontrado en el registro, mostrando resultados por número de documento.</span>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEntregasAnteriores" data-documento="{{ request('usuario') }}">
                            <i class="fas fa-history"></i> Ver Entregas Anteriores
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

<!-- Modal Entregas Anteriores -->
<div class="modal fade modal-entregas-anteriores" id="modalEntregasAnteriores" tabindex="-1" aria-labelledby="modalEntregasAnterioresLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEntregasAnterioresLabel">
                    <i class="fas fa-history me-2"></i>Historial de Entregas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Filtro de fecha -->
                <div class="filtro-fecha-container">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label for="filtroFechaEntrega" class="form-label">Filtrar por fecha</label>
                            <input type="date" class="form-control" id="filtroFechaEntrega">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-sm btn-primary" id="btnFiltrarFecha">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltro">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contenedor de entregas -->
                <div id="entregasAnterioresContainer">
                    <div class="loading-entregas">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2 text-muted">Cargando historial de entregas...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalEntregasAnteriores');
    const container = document.getElementById('entregasAnterioresContainer');
    const filtroFecha = document.getElementById('filtroFechaEntrega');
    const btnFiltrar = document.getElementById('btnFiltrarFecha');
    const btnLimpiar = document.getElementById('btnLimpiarFiltro');
    let currentDocumento = '';
    
    // Cargar entregas cuando se abre el modal
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        currentDocumento = button.getAttribute('data-documento');
        filtroFecha.value = '';
        cargarEntregas(currentDocumento);
    });
    
    // Filtrar por fecha
    btnFiltrar.addEventListener('click', function() {
        cargarEntregas(currentDocumento, filtroFecha.value);
    });
    
    // Limpiar filtro
    btnLimpiar.addEventListener('click', function() {
        filtroFecha.value = '';
        cargarEntregas(currentDocumento);
    });
    
    function cargarEntregas(documento, fecha = '') {
        container.innerHTML = `
            <div class="loading-entregas">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 text-muted">Cargando historial de entregas...</p>
            </div>
        `;
        
        let url = `{{ route('consultaElementoUsuario.entregasAnteriores') }}?documento=${encodeURIComponent(documento)}`;
        if (fecha) {
            url += `&fecha=${encodeURIComponent(fecha)}`;
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (!data.entregas || data.entregas.length === 0) {
                    container.innerHTML = `
                        <div class="no-entregas">
                            <i class="fas fa-inbox"></i>
                            <p>No se encontraron entregas anteriores${fecha ? ' para la fecha seleccionada' : ''}.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="entregas-timeline">';
                
                data.entregas.forEach(entrega => {
                    let elementosHtml = '';
                    if (entrega.elementos && entrega.elementos.length > 0) {
                        entrega.elementos.forEach(elem => {
                            elementosHtml += `
                                <span class="elemento-badge">
                                    <span class="sku">${elem.sku}</span>
                                    ${elem.nombre ? `<small class="text-muted">${elem.nombre}</small>` : ''}
                                    <span class="cantidad">x${elem.cantidad}</span>
                                </span>
                            `;
                        });
                    }
                    
                    let pdfButton = '';
                    if (entrega.pdf_url) {
                        pdfButton = `
                            <a href="${entrega.pdf_url}" target="_blank" class="btn btn-success btn-ver-pdf">
                                <i class="fas fa-file-pdf"></i> Ver PDF con Firma
                            </a>
                        `;
                    } else {
                        pdfButton = `
                            <span class="text-muted small">
                                <i class="fas fa-info-circle"></i> PDF no disponible (entrega sin comprobante guardado)
                            </span>
                        `;
                    }
                    
                    html += `
                        <div class="entrega-item">
                            <div class="entrega-header">
                                <span class="entrega-fecha">
                                    <i class="fas fa-calendar-alt me-1"></i>${entrega.fecha_corta}
                                </span>
                                <span class="entrega-tipo">${entrega.tipo_entrega}</span>
                            </div>
                            <div class="entrega-responsable">
                                <i class="fas fa-user me-1"></i>Entregado por: ${entrega.entrega_user}
                            </div>
                            <div class="entrega-elementos">
                                ${elementosHtml}
                            </div>
                            <div class="entrega-actions">
                                ${pdfButton}
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error cargando entregas:', error);
                container.innerHTML = `
                    <div class="no-entregas">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <p>Error al cargar el historial de entregas.</p>
                        <small class="text-muted">${error.message}</small>
                    </div>
                `;
            });
    }
});
</script>
@endpush