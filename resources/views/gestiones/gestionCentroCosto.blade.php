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

    {{-- Mensaje de éxito --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Formulario crear / editar --}}
    @if(isset($editCentro))
<form action="{{ route('gestionCentroCosto.update', $editCentro->id) }}" method="POST">
    @csrf
    @method('PUT')

    <input
        type="text"
        name="centroCostoName"
        class="form-control"
        value="{{ old('centroCostoName', $editCentro->centroCostoName) }}"
        required
    >

    <button class="btn btn-primary">Actualizar</button>
</form>
@else
<form action="{{ route('gestionCentroCosto.store') }}" method="POST">
    @csrf

    <input
        type="text"
        name="centroCostoName"
        class="form-control"
        placeholder="Nombre del centro de costo"
        required
    >

    <button class="btn btn-primary">Agregar centro de costo</button>
</form>
@endif

    {{-- Tabla --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Centro de Costo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($centros as $centro)
                <tr>
                    <td>{{ $centro->id }}</td>
                    <td>{{ $centro->centroCostoName }}</td>
                    <td class="action-buttons">
                        <a href="{{ route('gestionCentroCosto.edit', $centro->id) }}" class="btn btn-sm btn-warning">
                            Editar
                        </a>

                        <form action="{{ route('gestionCentroCosto.destroy', $centro->id) }}" method="POST" style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger"
                                onclick="return confirm('¿Estás seguro de eliminar este centro de costo?')">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No hay centros de costo registrados.</td>
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
