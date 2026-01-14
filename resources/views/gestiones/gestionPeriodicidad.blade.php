@extends('layouts.app')
@section('title', 'Gestión de Periodicidades')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionPeriodicidad.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
<div class="gestion-card">

    {{-- HEADER --}}
    <div class="gestion-header">
        <div>
            <h1>Configuración de notificaciones de entrega</h1>
            <p>Administra las periodicidades y avisos por elemento</p>
        </div>

        <div class="header-actions">
            <button class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#modalAddElemento">Agregar elemento</button>
            <a href="{{ url('/gestionCorreos') }}" class="btn btn-primary">
                gestion correos
            </a>

        </div>
    </div>

    {{-- ALERTA (usamos toast en su lugar) --}}

    {{-- TABLA --}}
    <div class="periodicidad-card">
        <div class="periodicidad-table-wrapper">
            {{-- Formulario para guardar cambios masivos --}}
            <form id="formSaveAll" method="POST" action="{{ route('gestionPeriodicidad.saveAll') }}">
                @csrf
                <table class="periodicidad-table">
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Periodicidad</th>
                        <th>Rojo</th>
                        <th>Amarillo</th>
                        <th>Verde</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periodicidades as $periodicidad)
                    <tr data-id="{{ $periodicidad->id }}">
                        <td class="elemento-cell">
                            <div class="elemento-panel">{{ $periodicidad->nombre ?? 'Sin nombre' }}</div>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="periodicidad[{{ $periodicidad->id }}]" disabled>
                                <option value="1_mes" {{ $periodicidad->periodicidad == '1_mes' ? 'selected' : '' }}>1 mes</option>
                                <option value="3_meses" {{ $periodicidad->periodicidad == '3_meses' ? 'selected' : '' }}>3 meses</option>
                                <option value="6_meses" {{ $periodicidad->periodicidad == '6_meses' ? 'selected' : '' }}>6 meses</option>
                                <option value="12_meses" {{ $periodicidad->periodicidad == '12_meses' ? 'selected' : '' }}>12 meses</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="rojo[{{ $periodicidad->id }}]" disabled>
                                <option value="3"  {{ (string)$periodicidad->aviso_rojo === '3' ? 'selected' : '' }}>3 días antes</option>
                                <option value="7"  {{ (string)$periodicidad->aviso_rojo === '7' ? 'selected' : '' }}>7 días antes</option>
                                <option value="14" {{ (string)$periodicidad->aviso_rojo === '14' ? 'selected' : '' }}>14 días antes</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="amarillo[{{ $periodicidad->id }}]" disabled>
                                <option value="3"  {{ (string)$periodicidad->aviso_amarillo === '3' ? 'selected' : '' }}>3 días antes</option>
                                <option value="7"  {{ (string)$periodicidad->aviso_amarillo === '7' ? 'selected' : '' }}>7 días antes</option>
                                <option value="14" {{ (string)$periodicidad->aviso_amarillo === '14' ? 'selected' : '' }}>14 días antes</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="verde[{{ $periodicidad->id }}]" disabled>
                                <option value="3"  {{ (string)$periodicidad->aviso_verde === '3' ? 'selected' : '' }}>3 días antes</option>
                                <option value="7"  {{ (string)$periodicidad->aviso_verde === '7' ? 'selected' : '' }}>7 días antes</option>
                                <option value="14" {{ (string)$periodicidad->aviso_verde === '14' ? 'selected' : '' }}>14 días antes</option>
                            </select>
                        </td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit" 
                                data-id="{{ $periodicidad->id }}"
                                data-nombre="{{ $periodicidad->nombre }}"
                                data-periodicidad="{{ $periodicidad->periodicidad }}"
                                data-aviso_rojo="{{ $periodicidad->aviso_rojo }}"
                                data-aviso_amarillo="{{ $periodicidad->aviso_amarillo }}"
                                data-aviso_verde="{{ $periodicidad->aviso_verde }}"
                                data-bs-toggle="modal" data-bs-target="#modalEditElemento">
                                Editar
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="{{ $periodicidad->id }}">Eliminar</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No se encontraron elementos.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </form>

            <!-- Hidden delete form (used to avoid nested forms) -->
            <form id="formDeleteElemento" method="POST" style="display:none">
                @csrf
            </form>
        </div>

        <div class="periodicidad-footer">
            <div class="pagination-wrapper">
                @if(method_exists($periodicidades, 'links'))
                    {{ $periodicidades->links() }}
                @else
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <li class="page-item disabled"><a class="page-link">&laquo;</a></li>
                            <li class="page-item active"><a class="page-link">1</a></li>
                            <li class="page-item"><a class="page-link">2</a></li>
                            <li class="page-item"><a class="page-link">3</a></li>
                            <li class="page-item"><a class="page-link">&raquo;</a></li>
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection

{{-- Toasts --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    (function(){
        @if(session('success'))
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:@json(session('success')), showConfirmButton:false, timer:3000 });
        @endif
        @if(session('error'))
        Swal.fire({ toast:true, position:'top-end', icon:'error', title:@json(session('error')), showConfirmButton:false, timer:5000 });
        @endif
    })();
</script>
<div class="modal fade" id="modalAddElemento" tabindex="-1" aria-labelledby="modalAddElementoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAddElementoLabel">Agregar elemento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('gestionPeriodicidad.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" id="searchProductoAdd" class="form-control mb-2" placeholder="Buscar producto por SKU o nombre...">
                        <select name="nombre" id="selectProductoAdd" class="form-select" required size="6">
                            <option value="">-- Seleccione un producto --</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->name_produc }}" data-sku="{{ $p->sku }}">{{ $p->sku }} - {{ $p->name_produc }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="sku" id="skuHiddenAdd" value="">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Periodicidad</label>
                        <select name="periodicidad" class="form-select">
                            <option value="1_mes">1 mes</option>
                            <option value="3_meses">3 meses</option>
                            <option value="6_meses">6 meses</option>
                            <option value="12_meses">12 meses</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Rojo</label>
                            <select name="aviso_rojo" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Amarillo</label>
                            <select name="aviso_amarillo" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Verde</label>
                            <select name="aviso_verde" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarAdd">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
@push('scripts')
<div class="modal fade" id="modalEditElemento" tabindex="-1" aria-labelledby="modalEditElementoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditElementoLabel">Editar elemento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditElemento" method="POST" action="{{ route('gestionPeriodicidad.index') }}" data-update-template="{{ route('gestionPeriodicidad.update', ['gestionPeriodicidad' => '__ID__']) }}" data-destroy-template="{{ route('gestionPeriodicidad.destroy', ['gestionPeriodicidad' => '__ID__']) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" id="searchProductoEdit" class="form-control mb-2" placeholder="Buscar producto por SKU o nombre...">
                        <select name="nombre" id="selectProductoEdit" class="form-select" required size="6">
                            <option value="">-- Seleccione un producto --</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->name_produc }}" data-sku="{{ $p->sku }}">{{ $p->sku }} - {{ $p->name_produc }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="sku" id="skuHiddenEdit" value="">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Periodicidad</label>
                        <select name="periodicidad" class="form-select">
                            <option value="1_mes">1 mes</option>
                            <option value="3_meses">3 meses</option>
                            <option value="6_meses">6 meses</option>
                            <option value="12_meses">12 meses</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Rojo</label>
                            <select name="aviso_rojo" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Amarillo</label>
                            <select name="aviso_amarillo" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Verde</label>
                            <select name="aviso_verde" class="form-select">
                                <option value="3">3 días antes</option>
                                <option value="7">7 días antes</option>
                                <option value="14">14 días antes</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@push('scripts')
<script src="{{ asset('js/periodicidad/periodicidad.js') }}"></script>
@endpush
