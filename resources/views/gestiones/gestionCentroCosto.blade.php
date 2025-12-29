@extends('layouts.app')
@section('title', 'Gestión de Centros de Costo')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionCentroCostos.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>
<div class="gestion-card">
    <h1>Gestión de Centros de Costo</h1>
    <p>Aquí puedes gestionar los centros de costo disponibles en el sistema.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Formulario crear/editar --}}
    @if(isset($editCentroCosto))
        <form action="{{ route('gestionCentroCosto.update', $editCentroCosto->id) }}" method="POST" class="mb-3">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <label class="form-label">Nombre del Centro de Costo</label>
                <input name="centroCostoName" value="{{ old('centroCostoName', $editCentroCosto->centroCostoName) }}" class="form-control" required>
            </div>
            <button class="btn btn-primary">Actualizar Centro de Costo</button>
            <a href="{{ route('gestionCentroCosto.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    @else
        <form action="{{ route('gestionCentroCosto.store') }}" method="POST" class="mb-3">
            @csrf
            <div class="row g-2 align-items-center">
                <div class="col-auto" style="flex:1">
                    <input name="centroCostoName" placeholder="Nombre del centro de costo" class="form-control" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Agregar Nuevo Centro de Costo</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Tabla de centros de costo --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Centro de Costo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($centrosCosto ?? [] as $centro)
                <tr>
                    <td data-label="ID">{{ $centro->id }}</td>
                    <td data-label="Nombre">{{ $centro->centroCostoName }}</td>
                    <td data-label="Acciones" class="action-buttons">
                        <a href="{{ route('gestionCentroCosto.edit', $centro->id) }}" class="btn btn-sm btn-warning">Editar</a>
                        <form action="{{ route('gestionCentroCosto.destroy', $centro->id) }}" method="POST" style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este centro de costo?')">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No hay centros de costo disponibles.</td>
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