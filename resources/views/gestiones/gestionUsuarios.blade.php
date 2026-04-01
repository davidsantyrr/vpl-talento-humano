@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionUsuario.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

        <div class="d-flex gap-2 align-items-center">
            <form class="d-flex me-2" method="GET" action="{{ route('gestionUsuario.index') }}">
                <input name="q" value="{{ isset($q) ? $q : request('q') }}" class="form-control form-control-sm me-2" placeholder="Buscar por nombre o documento" />
                <button class="btn btn-sm btn-outline-primary" type="submit">Buscar</button>
                <a href="{{ route('gestionUsuario.index') }}" class="btn btn-sm btn-outline-secondary ms-2">Limpiar</a>
            </form>

            <button class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalUsuario">
                + Agregar nuevo usuario
            </button>

            <a href="{{ route('gestionUsuario.template') }}" class="btn btn-outline-secondary">
                <i class="bi bi-download"></i>
                Descargar plantilla
            </a>

            <button class="btn btn-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalImportarUsuarios">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                Importar Excel
            </button>
        </div>
    </div>

    @if(session('import_errors'))
        <div class="alert alert-warning mt-3">
            <strong>Import: Errores</strong>
            <ul class="mb-0">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ALERTA --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(isset($q) && $q !== '' && $usuarios->isEmpty())
        <div class="alert alert-info mt-3">
            <strong>Usuario no existente:</strong> No se encontró ningún usuario para "{{ $q }}".
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
                    <td>{{ optional($usuario->area)->nombre_area ?? 'N/A' }}</td>
                    <td>
                        <div class="acciones d-flex align-items-center gap-2">
                            
                            <a href="{{ route('gestionUsuario.edit', $usuario->id) }}"
                                class="btn btn-outline-warning btn-sm" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form action="{{ route('gestionUsuario.destroy', $usuario->id) }}"
                                method="POST">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
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
                <h5 class="modal-title">{{ isset($editUsuario) ? 'Editar Usuario' : 'Agregar Usuario' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            
            <form action="{{ isset($editUsuario) ? route('gestionUsuario.update', $editUsuario->id) : route('gestionUsuario.store') }}" method="POST">
                @csrf
                @if(isset($editUsuario))
                    @method('PUT')
                @endif

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label name="nombres" class="form-label">Nombre</label>
                            <input type="text" name="nombres" class="form-control" required value="{{ old('nombres', $editUsuario->nombres ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control" value="{{ old('apellidos', $editUsuario->apellidos ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo Documento</label>
                            <select name="tipo_documento" class="form-select">
                                <option value="">Seleccione</option>
                                @php $tipoOld = old('tipo_documento', $editUsuario->tipo_documento ?? '') @endphp
                                <option value="Cédula de Ciudadanía" {{ $tipoOld == 'Cédula de Ciudadanía' ? 'selected' : '' }}>Cédula de Ciudadanía</option>
                                <option value="Cédula de Extranjería" {{ $tipoOld == 'Cédula de Extranjería' ? 'selected' : '' }}>Cédula de Extranjería</option>
                                <option value="Pasaporte" {{ $tipoOld == 'Pasaporte' ? 'selected' : '' }}>Pasaporte</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Número Documento</label>
                            <input type="text" name="numero_documento" class="form-control" required value="{{ old('numero_documento', $editUsuario->numero_documento ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email', $editUsuario->email ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha Ingreso</label>
                            <input type="date" name="fecha_ingreso" class="form-control" required value="{{ old('fecha_ingreso', $editUsuario->fecha_ingreso ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Operación</label>
                            <select name="operacion_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($operations as $op)
                                    <option value="{{ $op->id }}" {{ (string) old('operacion_id', $editUsuario->operacion_id ?? '') === (string) $op->id ? 'selected' : '' }}>{{ $op->operationName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Área</label>
                            <select name="area_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}" {{ (string) old('area_id', $editUsuario->area_id ?? '') === (string) $area->id ? 'selected' : '' }}>{{ $area->nombre_area }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        {{ isset($editUsuario) ? 'Actualizar Usuario' : 'Guardar Usuario' }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

{{-- MODAL SELECCIÓN DE PRODUCTO --}}
{{-- MODAL IMPORTAR USUARIOS --}}
<div class="modal fade" id="modalImportarUsuarios" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importar usuarios desde Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="{{ route('gestionUsuario.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Archivo (xlsx / csv)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>

                    <p class="small text-muted">Orden de columnas esperado: Nombres, Apellidos, Tipo documento, N° documento, Email, Fecha ingreso (YYYY-MM-DD), Operación, Área, Cargo (opcional)</p>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Importar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>

        </div>
    </div>
</div>



@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alertas.js') }}"></script>
 
@if(isset($editUsuario))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalUsuario');
    if (modalEl) {
        var m = new bootstrap.Modal(modalEl);
        m.show();
    }
});
</script>
@endif
@endpush

@endsection

