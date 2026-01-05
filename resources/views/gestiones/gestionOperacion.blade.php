@extends('layouts.app')
@section('title', 'Gestión de Operación')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionOperacion.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>

<div class="gestion-card">
    <h1>Gestión de Operación</h1>
    <p>Aquí puedes gestionar las operaciones disponibles en el sistema.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Formulario crear/editar --}}
    @if(isset($editOperation))
        <form action="{{ route('gestionOperacion.update', $editOperation->id) }}" method="POST" class="mb-3">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <label class="form-label">Nombre de la Operación</label>
                <input name="operationName" value="{{ old('operationName', $editOperation->operationName) }}" class="form-control" required>
            </div>
            <button class="btn btn-primary">Actualizar Operación</button>
            <a href="{{ route('gestionOperacion.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    @else
        <form action="{{ route('gestionOperacion.store') }}" method="POST" class="mb-3">
            @csrf
            <div class="row g-2 align-items-center">
                <div class="col-auto" style="flex:1">
                    <input name="operationName" placeholder="Nombre de la operación" class="form-control" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Agregar Nueva Operación</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Tabla de operaciones --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre de la Operación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($operations ?? [] as $op)
                <tr>
                    <td data-label="ID">{{ $op->id }}</td>
                    <td data-label="Nombre">{{ $op->operationName }}</td>
                    <td data-label="Acciones" class="action-buttons">
                        <a href="{{ route('gestionOperacion.edit', $op->id) }}" class="btn btn-sm btn-warning">Editar</a>
                        <form action="{{ route('gestionOperacion.destroy', $op->id) }}" method="POST" style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No hay operaciones registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alertas.js') }}"></script>
@endpush

@endsection