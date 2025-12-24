@php($status = session('status'))
@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
<div class="articulos-page container">
  <header class="page-header">
    <h1>Artículos (requisición) y Stock (VSP)</h1>
  </header>

  @if(!empty($status))
    <div class="alert success">{{ $status }}</div>
  @endif

  <p class="page-subtitle">Gestiona el stock en la BD principal para artículos provenientes de la BD de requisición.</p>

  <div class="table-wrapper">
    <table class="tabla-articulos">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Stock</th>
          <th>Actualizado</th>
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
            <option value="{{ $size }}" {{ (int)$perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
        <span>artículos</span>
      </form>
    </div>

    {!! $paginationHtml !!}
  </div>
</div>
@endsection