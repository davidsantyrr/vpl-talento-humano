@php($status = session('status'))
@extends('layouts.app')
@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Artículos (requisición) y Stock (VSP)</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary btn-sm" href="{{ route('articulos.index') }}">Artículos/Stock</a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ url('/menuentrega') }}">Menú Entregas</a>
    </div>
  </div>

  @if($status)
    <div class="alert alert-success">{{ $status }}</div>
  @endif
  <p class="text-muted">Gestiona el stock en la BD principal para artículos provenientes de la BD de requisición.</p>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th style="width:160px">Stock</th>
          <th style="width:140px">Actualizado</th>
        </tr>
      </thead>
      <tbody>
      @foreach($productos as $p)
        <tr>
          <td>{{ $p->sku }}</td>
          <td>{{ $p->name_produc }}</td>
          <td>{{ $p->categoria_produc }}</td>
          <td>
            <form action="{{ route('articulos.update', $p->sku) }}" method="POST" class="d-flex gap-2">
              @csrf
              <input name="cantidad" type="number" min="0" value="{{ optional($stocks->get($p->sku))->cantidad ?? 0 }}" class="form-control" />
              <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
          </td>
          <td>
            <span class="badge bg-secondary">{{ optional($stocks->get($p->sku))->updated_at?->diffForHumans() ?? 'Sin registro' }}</span>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <div>
    {{ $productos->links() }}
  </div>
</div>
@endsection