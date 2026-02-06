@extends('layouts.app')
@section('title', 'Gestion de Areas')
@push('styles')
<link rel="stylesheet" href="{{ secure_asset('css/gestiones/gestionArea.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
<div class="gestion-card">
    <h1>Gestión de Áreas</h1>
    <p>Aquí puedes gestionar las áreas disponibles en el sistema.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Formulario crear/editar --}}
    @if(isset($editArea))
        <form action="{{ route('gestionArea.update', $editArea->id) }}" method="POST" class="mb-3">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <label class="form-label">Nombre del Área</label>
                <input name="areaName" value="{{ old('areaName', $editArea->nombre_area) }}" class="form-control" required>
            </div>
            <button class="btn btn-primary">Actualizar Área</button>
            <a href="{{ route('gestionArea.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    @else
        <form action="{{ route('gestionArea.store') }}" method="POST" class="mb-3">
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

    {{-- Tabla de áreas --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Área</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($areas ?? [] as $area)
                <tr>
                    <td data-label="ID">{{ $area->id }}</td>
                    <td data-label="Nombre">{{ $area->nombre_area }}</td>
                    <td data-label="Acciones" class="action-buttons">
                        <a href="{{ route('gestionArea.edit', $area->id) }}" class="btn btn-sm btn-warning">Editar</a>
                        <form action="{{ route('gestionArea.destroy', $area->id) }}" method="POST" style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No hay áreas disponibles.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ secure_asset('js/alertas.js') }}"></script>
@endpush

@endsection

