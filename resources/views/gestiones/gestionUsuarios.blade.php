@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionUsuario.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>

<div class="gestion-card">

    {{-- HEADER --}}
    <div class="gestion-header">
        <div>
            <h1>Gestión de Usuarios</h1>
            <p>Administra los usuarios registrados en el sistema</p>
        </div>

        <button class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalUsuario">
            + Agregar nuevo usuario
        </button>
    </div>

    {{-- ALERTA --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA --}}
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Tipo documento</th>
                    <th>N° documento</th>
                    <th>Email</th>
                    <th>Fecha ingreso</th>
                    <th>Operación</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->id }}</td>
                    <td>{{ $usuario->nombres }}</td>
                    <td>{{ $usuario->apellidos }}</td>
                    <td>{{ $usuario->tipo_documento }}</td>
                    <td>{{ $usuario->numero_documento }}</td>
                    <td>{{ $usuario->email }}</td>
                    <td>{{ $usuario->fecha_ingreso }}</td>
                    <td>{{ optional($usuario->operacion)->operationName ?? 'N/A' }}</td>
                    <td>{{ optional($usuario->area)->areaName ?? 'N/A' }}</td>
                    <td class="acciones">
                        <a href="{{ route('gestionUsuario.edit', $usuario->id) }}"
                        class="btn btn-warning btn-sm">
                            Editar
                        </a>

                        <form action="{{ route('gestionUsuario.destroy', $usuario->id) }}"
                            method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">No hay usuarios registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODAL CREAR USUARIO --}}
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Agregar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            
            <form action="{{ route('gestionUsuario.store') }}" method="POST">
                @csrf

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label name="nombres" class="form-label">Nombre</label>
                            <input type="text" name="nombres" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo Documento</label>
                            <select name="tipo_documento" class="form-select">
                                <option value="">Seleccione</option>
                                <option value="Cédula de Ciudadanía">Cédula de Ciudadanía</option>
                                <option value="Cédula de Extranjería">Cédula de Extranjería</option>
                                <option value="Pasaporte">Pasaporte</option>    
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Número Documento</label>
                            <input type="text" name="numero_documento" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha Ingreso</label>
                            <input type="date" name="fecha_ingreso" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Operación</label>
                            <select name="operacion_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($operations as $op)
                                    <option value="{{ $op->id }}">{{ $op->operationName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Área</label>
                            <select name="area_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->nombre_area }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        Guardar Usuario
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alertas.js') }}"></script>
@endpush

@endsection

