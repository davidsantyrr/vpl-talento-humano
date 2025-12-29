@extends('layouts.app')
@section('title', 'Gestion de Usuarios')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionArea.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
<div class="gestion-card">
    <h1>Gestión de Usuarios</h1>
    <p>Aquí puedes gestionar los usuarios del sistema.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Formulario crear/editar --}}
    @if(isset($editUsuario))
        <form action="{{ route('gestionUsuario.update', $editUsuario->id) }}" method="POST" class="mb-3">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <label class="form-label">Nombre</label>
                <input name="nombre" value="{{ old('nombre', $editUsuario->nombre) }}" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Apellidos</label>
                <input name="apellidos" value="{{ old('apellidos', $editUsuario->apellidos) }}" class="form-control" required>
            </div>

            <div class="mb-2">
                <label class="form-label">tipo documento</label>
                <select name="tipo_documento" class="form-control" required>
                    <option value="CC" {{ old('tipo_documento', $editUsuario->tipo_documento) == 'CC' ? 'selected' : '' }}>C.C</option>
                    <option value="TI" {{ old('tipo_documento', $editUsuario->tipo_documento) == 'TI' ? 'selected' : '' }}>T.I</option>
                    <option value="EXTRANJERA" {{ old('tipo_documento', $editUsuario->tipo_documento) == 'EXTRANJERA' ? 'selected' : '' }}>EXTRANJERA</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Numero de documento</label>
                <input name="numero_documento" value="{{ old('numero_documento', $editUsuario->numero_documento) }}" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">email</label>
                <input name="email" value="{{ old('email', $editUsuario->email) }}" class="form-control" required>
            </div>
            
            <div class="mb-2">
                <label for="destinatario" class="form-label">operacion</label>
                
                <select name="operacion_id" class="form-control" required>
                    <option value="">Seleccione una operación</option>

                    @foreach($operations as $op)
                    <option value="{{ $op->id }}">
                    {{ $op->operationName }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label for="destinatario" class="form-label">area</label>
                
                <select name="area_id" class="form-control" required>
                    <option value="">Seleccione una área</option>

                    @foreach($areas as $area)
                    <option value="{{ $area->id }}">
                    {{ $area->areaName }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary">Actualizar Usuario</button>
            <a href="{{ route('gestionUsuario.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    @else
        <form action="{{ route('gestionUsuario.store') }}" method="POST" class="mb-3">
            @csrf
            <div class="row g-2 align-items-center">
                <div class="col-auto" style="flex:1">
                    <input name="areaName" placeholder="Nombre del área" class="form-control" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Agregar Nueva Área</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Tabla de Usuarios --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombres</th>
                <th>apellidos</th>
                <th>tipo de documento</th>
                <th>Numero de documento</th>
                <th>email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->id }}</td>
                    <td>{{ $usuario->nombre }}</td>
                    <td>{{ $usuario->apellidos }}</td>
                    <td>{{ $usuario->tipo_documento }}</td>
                    <td>{{ $usuario->numero_documento }}</td>
                    <td>{{ $usuario->email }}</td>
    <td>
        <a href="{{ route('gestionUsuario.edit', $usuario->id) }}" class="btn btn-warning btn-sm">Editar</a>
        <form action="{{ route('gestionUsuario.destroy', $usuario->id) }}" method="POST" style="display:inline">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm">Eliminar</button>
        </form>
    </td>
</tr>
@empty
<tr>
    <td colspan="7">No hay usuarios registrados.</td>
</tr>
@endforelse
        </tbody>
    </table>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alertasOperacines.js') }}"></script>
@endpush

@endsection