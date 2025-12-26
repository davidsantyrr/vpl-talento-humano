@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
@endpush
@section('content')
<div class="container">
  <h1>Asignar productos a cargo</h1>
  @if(session('status'))
    <div class="alert success">{{ session('status') }}</div>
  @endif

  <form method="GET" action="{{ route('elementoxcargo.productos') }}" class="page-size-form" style="margin-bottom:10px">
    <label for="cargo_id">Cargo</label>
    <select name="cargo_id" id="cargo_id" onchange="this.form.submit()">
      <option value="">Seleccione un cargo</option>
      @foreach($cargos as $c)
        <option value="{{ $c->id }}" {{ (int)$cargoId === $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
      @endforeach
    </select>
    <label for="per_page">Ver</label>
    <select id="per_page" name="per_page" onchange="this.form.submit()">
      @foreach([5,10,20,50] as $size)
        <option value="{{ $size }}" {{ (int)$perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
      @endforeach
    </select>
  </form>

  @if($cargoId)
    <form method="POST" action="{{ route('elementoxcargo.productos.store') }}">
      @csrf
      <input type="hidden" name="cargo_id" value="{{ $cargoId }}">

      <div class="table-wrapper">
        <table class="tabla-articulos">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>Asignar</th>
            </tr>
          </thead>
          <tbody>
            {!! $rowsHtml !!}
          </tbody>
        </table>
      </div>

      <div class="paginacion paginacion-compact">
        {!! $paginationHtml !!}
      </div>

      <button type="submit" class="btn-guardar">Guardar selección</button>
    </form>
  @else
    <p class="page-subtitle">Seleccione un cargo para ver los productos disponibles y asignarlos.</p>
  @endif
</div>
@endsection