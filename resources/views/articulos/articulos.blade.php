@php($status = session('status'))
@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="articulos-page container">
  <header class="page-header">
    <h1>Listado de articulos</h1>
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

    <div class="filters" style="display:flex; gap:16px; align-items:center; margin: 12px 0;">
      <form method="GET" action="{{ route('articulos.index') }}" class="category-filter-form" style="display:flex; gap:8px; align-items:center;">
        <label for="category">Categoría</label>
        <select id="category" name="category" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach(($categories ?? []) as $cat)
            <option value="{{ $cat }}" {{ ($selectedCategory ?? '')===$cat ? 'selected' : '' }}>{{ $cat }}</option>
          @endforeach
        </select>
        <input type="hidden" name="per_page" value="{{ (int)($perPage ?? 20) }}">
      </form>
    </div>

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
          <input type="hidden" name="category" value="{{ $selectedCategory ?? '' }}">
        </form>
      </div>

      {!! $paginationHtml !!}
    </div>
  </div>
</div>

<!-- Modal de Ver Constancias -->
<div class="modal" id="modalVerConstancias">
  <div class="modal-content modal-content-constancias">
    <div class="modal-header">
      <h2>Constancias de Destrucción</h2>
      <button class="modal-close" onclick="cerrarModalConstancias()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="info-box info-box-info">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
        </svg>
        <div>
          <strong>SKU:</strong> <span id="constanciasSku"></span><br>
          <strong>Producto:</strong> <span id="constanciasProducto"></span>
        </div>
      </div>

      <div id="constanciasLista" class="constancias-lista">
        <p style="text-align: center; color: #999; padding: 2rem;">Cargando constancias...</p>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn secondary" onclick="cerrarModalConstancias()">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal de Destrucción -->
<div class="modal" id="modalDestruccion">
  <div class="modal-content modal-content-destruccion">
    <div class="modal-header">
      <h2>Destruir Artículo</h2>
      <button class="modal-close" onclick="cerrarModalDestruccion()">&times;</button>
    </div>
    <form id="formDestruccion" action="{{ route('articulos.destruir') }}" method="POST" enctype="multipart/form-data" onsubmit="procesarDestruccion(event)">
      @csrf
      <div class="modal-body">
        <div class="info-box info-box-warning">
          <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
          </svg>
          <span>Esta acción moverá el artículo al estado de "Destruido". Debe cargar la constancia de destrucción en formato PDF.</span>
        </div>
        
        <div class="detalle-grid" style="margin-top: 1.5rem;">
          <div class="detalle-item">
            <strong>Producto:</strong>
            <span id="destruccionNombreProducto"></span>
          </div>
          <div class="detalle-item">
            <strong>SKU:</strong>
            <span id="destruccionSku"></span>
          </div>
          <div class="detalle-item">
            <strong>Bodega:</strong>
            <span id="destruccionBodega"></span>
          </div>
          <div class="detalle-item">
            <strong>Ubicación:</strong>
            <span id="destruccionUbicacion"></span>
          </div>
          <div class="detalle-item">
            <strong>Estatus Actual:</strong>
            <span id="destruccionEstatusActual"></span>
          </div>
          <div class="detalle-item">
            <strong>Stock Actual:</strong>
            <span id="destruccionStockActual"></span>
          </div>
        </div>

        <div class="form-grid" style="margin-top: 1.5rem;">
          <div class="form-field">
            <label for="destruccionCantidad">Cantidad a Destruir *</label>
            <input type="number" id="destruccionCantidad" name="cantidad" min="1" required>
          </div>
          <div class="form-field span-2">
            <label for="destruccionArchivo">Constancia de Destrucción (PDF) *</label>
            <input type="file" id="destruccionArchivo" name="constancia" accept="application/pdf" required>
            <small class="text-muted">Debe cargar un archivo PDF que certifique la destrucción del artículo</small>
          </div>
        </div>

        <input type="hidden" name="sku" id="destruccionSkuHidden">
        <input type="hidden" name="bodega" id="destruccionBodegaHidden">
        <input type="hidden" name="ubicacion" id="destruccionUbicacionHidden">
        <input type="hidden" name="estatus" id="destruccionEstatusHidden">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <input type="hidden" name="category" value="{{ $selectedCategory ?? '' }}">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn secondary" onclick="cerrarModalDestruccion()">Cancelar</button>
        <button type="submit" class="btn danger">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right: 6px;">
            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
            <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
          </svg>
          Destruir Artículo
        </button>
      </div>
    </form>
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
<script src="{{ asset('js/articulo/destruccion.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/articulo/constancias.js') }}?v={{ time() }}"></script>
@endsection