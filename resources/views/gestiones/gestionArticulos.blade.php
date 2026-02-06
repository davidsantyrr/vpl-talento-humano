@extends('layouts.app')
@section('title', 'Gestión de Artículos')
@push('styles')
<link rel="stylesheet" href="{{ secure_asset('css/gestiones/gestionArticulos.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>

<div class="gestion-card">
    <h1>Gestión de Artículos</h1>
    <p>Aquí puedes gestionar los artículos disponibles en el sistema.</p>

    <div class="gestion-toolbar mb-3">
        <div class="filters"></div>
        <button type="button" class="btn btn-primary" id="btnToggleAdd">Agregar artículo</button>
    </div>

    {{-- Formulario de edición --}}
    @isset($editItem)
    <div id="editFormWrapper" class="mb-4">
        <h5>Editar artículo #{{ $editItem->id }}</h5>
        <form method="POST" action="{{ route('gestionArticulos.update', $editItem->id) }}">
            @csrf
            @method('PUT')
            <div class="row" style="gap:10px;">
                <div style="flex:1; min-width:240px;">
                    <label class="form-label">Nombre del artículo</label>
                    <input type="text" name="nombre_articulo" class="form-control" value="{{ old('nombre_articulo', $editItem->nombre_articulo) }}" required>
                    @error('nombre_articulo')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku', $editItem->sku) }}" required>
                    @error('sku')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Sin categoría</option>
                        @foreach(($categorias ?? []) as $cat)
                            <option value="{{ $cat }}" {{ (old('categoria', $editItem->categoria) === $cat) ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('categoria')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="align-self:end;">
                    <button type="submit" class="btn btn-success">Actualizar</button>
                    <a href="{{ route('gestionArticulos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
    @endisset

    <div id="addFormWrapper" style="display:none;">
        <form method="POST" action="{{ route('gestionArticulos.store') }}" class="mb-3">
            @csrf
            <div class="row" style="gap:10px;">
                <div style="flex:1; min-width:240px;">
                    <label class="form-label">Nombre del artículo</label>
                    <input type="text" name="nombre_articulo" class="form-control" placeholder="Ej: Casco de seguridad" required>
                    @error('nombre_articulo')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control" placeholder="Ej: CSM439" required>
                    @error('sku')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="flex:1; min-width:200px;">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Sin categoría</option>
                        @foreach(($categorias ?? []) as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                    @error('categoria')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div style="align-self:end;">
                    <button type="submit" class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnCancelAdd">Cancelar</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Mensaje de éxito --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tabla --}}
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Artículo</th>
                <th>SKU</th>
                <th>Categoría</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($articulos as $articulo)
            <tr>
                <td data-label="ID">{{ $articulo->id }}</td>
                <td data-label="Nombre del Artículo">{{ $articulo->nombre_articulo }}</td>
                <td data-label="SKU">{{ $articulo->sku }}</td>
                <td data-label="Categoría">{{ $articulo->categoria ?? '—' }}</td>
                <td data-label="Acciones">
                    <a href="{{ route('gestionArticulos.edit', $articulo->id) }}" class="btn btn-sm btn-primary">Editar</a>
                    <form action="{{ route('gestionArticulos.destroy', $articulo->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este artículo?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-muted">No hay artículos registrados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Paginación --}}
    {{ $articulos->links() }}
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ secure_asset('js/alertas.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        var btnToggle = document.getElementById('btnToggleAdd');
        var btnCancel = document.getElementById('btnCancelAdd');
        var wrap = document.getElementById('addFormWrapper');
        if (btnToggle && wrap) {
            btnToggle.addEventListener('click', function(){
                wrap.style.display = (wrap.style.display === 'none' || !wrap.style.display) ? 'block' : 'none';
            });
        }
        if (btnCancel && wrap) {
            btnCancel.addEventListener('click', function(){ wrap.style.display = 'none'; });
        }
    });
</script>
@endpush
@endsection

