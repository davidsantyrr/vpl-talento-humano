@php($status = session('status'))
@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="articulos-page container">
  <header class="page-header">
    <h1>Artículos (requisición) y Stock (VSP)</h1>
  </header>

  <div class="tabs-panel">
  {{-- Tabs de estatus --}}
  <div class="tabs">
    <button class="tab-btn active" data-status="disponible">Disponibles</button>
    <button class="tab-btn" data-status="prestado">Prestados</button>
    <button class="tab-btn" data-status="perdido">Perdidos</button>
    <button class="tab-btn" data-status="destruido">Destruidos</button>
  </div>

    <p class="page-subtitle">Gestiona inventario en la BD 3 (bodega, ubicación, estatus y stock) para artículos provenientes de requisición.</p>

    <div class="table-wrapper">
      <table class="tabla-articulos">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Bodega</th>
            <th>Ubicación</th>
            <th>Estatus</th>
            <th>Stock</th>
            <th style="text-align:center;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          {!! $rowsHtml !!}
        </tbody>
      </table>
    </div>

    <div class="paginacion paginacion-compact">
      <div class="page-size">
        <form method="GET" action="{{ route('articulos.index') }}" class="page-size-form">
          <label for="per_page">Ver</label>
          <select id="per_page" name="per_page" onchange="this.form.submit()">
            @foreach([5,10,20,50] as $size)
            <option value="{{ $size }}" {{ (int)$perPage===$size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
          </select>
          <span>artículos</span>
        </form>
      </div>

      {!! $paginationHtml !!}
    </div>
  </div>
</div>

@php($ubicacionesJson = \Illuminate\Support\Facades\DB::connection('mysql_third')
  ->table('ubicaciones')->select('id','bodega','ubicacion')->orderBy('bodega')->orderBy('ubicacion')->get())
<script>
  window.ArticulosPageConfig = {
    statusMsg: @json($status),
    errorMsg: @json(session('error')),
    perPage: {{ (int)($perPage ?? 20) }},
    csrfToken: '{{ csrf_token() }}',
    articulosBaseUrl: '{{ url('/articulos') }}',
    ubicacionesAll: @json($ubicacionesJson),
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/articulo/articulo.js') }}"></script>
@endsection