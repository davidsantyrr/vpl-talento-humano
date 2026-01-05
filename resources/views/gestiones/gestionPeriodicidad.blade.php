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
            <button class="btn btn-primary btn-back">Volver</button>
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
                                <option {{ $periodicidad->aviso_rojo == '3' ? 'selected' : '' }} value="3">3 días antes</option>
                                <option {{ $periodicidad->aviso_rojo == '7' ? 'selected' : '' }} value="7">7 días antes</option>
                                <option {{ $periodicidad->aviso_rojo == '14' ? 'selected' : '' }} value="14">14 días antes</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="amarillo[{{ $periodicidad->id }}]" disabled>
                                <option {{ $periodicidad->aviso_amarillo == '3' ? 'selected' : '' }} value="3">3 días antes</option>
                                <option {{ $periodicidad->aviso_amarillo == '7' ? 'selected' : '' }} value="7">7 días antes</option>
                                <option {{ $periodicidad->aviso_amarillo == '14' ? 'selected' : '' }} value="14">14 días antes</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="verde[{{ $periodicidad->id }}]" disabled>
                                <option {{ $periodicidad->aviso_verde == '3' ? 'selected' : '' }} value="3">3 días antes</option>
                                <option {{ $periodicidad->aviso_verde == '7' ? 'selected' : '' }} value="7">7 días antes</option>
                                <option {{ $periodicidad->aviso_verde == '14' ? 'selected' : '' }} value="14">14 días antes</option>
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
                        <td colspan="5" class="text-center text-muted">No se encontraron elementos.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </form>

            <!-- Hidden delete form (used to avoid nested forms) -->
            <form id="formDeleteElemento" method="POST" style="display:none">
                @csrf
                @method('DELETE')
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

    {{-- Modal para agregar nuevo elemento --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function(){
            @if(session('success'))
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: @json(session('success')),
                    showConfirmButton: false,
                    timer: 3000,
                });
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
                            <input type="text" name="nombre" class="form-control" required maxlength="191">
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
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btnSaveAll = document.getElementById('btnSaveAll');
            if(btnSaveAll){
                btnSaveAll.addEventListener('click', function(e){
                    e.preventDefault();
                    const form = document.getElementById('formSaveAll');
                    if(form) form.submit();
                });
            }
            // Edit button: populate edit modal
            document.querySelectorAll('.btn-edit').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const id = this.dataset.id;
                    const nombre = this.dataset.nombre || '';
                    const periodicidad = this.dataset.periodicidad || '';
                    const aviso_rojo = this.dataset.aviso_rojo || '';
                    const aviso_amarillo = this.dataset.aviso_amarillo || '';
                    const aviso_verde = this.dataset.aviso_verde || '';

                    const form = document.getElementById('formEditElemento');
                    if(!form) return;
                    form.action = '/gestionPeriodicidad/' + id;
                    // mark which id is being edited
                    form.dataset.editingId = id;
                    // Enable selects in the table row so user can edit inline if desired
                    const row = document.querySelector('tr[data-id="' + id + '"]');
                    if(row){
                        row.querySelectorAll('select').forEach(function(s){ s.removeAttribute('disabled'); });
                    }
                    form.querySelector('[name="nombre"]').value = nombre;
                    form.querySelector('[name="periodicidad"]').value = periodicidad;
                    form.querySelector('[name="aviso_rojo"]').value = aviso_rojo;
                    form.querySelector('[name="aviso_amarillo"]').value = aviso_amarillo;
                    form.querySelector('[name="aviso_verde"]').value = aviso_verde;
                });
            });
            // Delete button: use hidden form to avoid nested forms
            document.querySelectorAll('.btn-delete').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const id = this.dataset.id;
                    const delForm = document.getElementById('formDeleteElemento');
                    if(!delForm) return;
                    delForm.action = '/gestionPeriodicidad/' + id;
                    delForm.submit();
                });
            });
            // When the edit modal is closed, re-disable the selects of the edited row
            var editModalEl = document.getElementById('modalEditElemento');
            if(editModalEl){
                editModalEl.addEventListener('hidden.bs.modal', function(){
                    var form = document.getElementById('formEditElemento');
                    if(!form) return;
                    var id = form.dataset.editingId;
                    if(id){
                        var row = document.querySelector('tr[data-id="' + id + '"]');
                        if(row){
                            row.querySelectorAll('select').forEach(function(s){ s.setAttribute('disabled',''); });
                        }
                        form.dataset.editingId = '';
                    }
                });
            }
        });
    </script>
    @endpush

    <!-- Edit Modal -->
    @push('scripts')
    <div class="modal fade" id="modalEditElemento" tabindex="-1" aria-labelledby="modalEditElementoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditElementoLabel">Editar elemento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditElemento" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required maxlength="191">
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