@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
<style>
  .cargo-page .actions { display:flex; gap:8px; align-items:center }
  .cargo-form, .cargo-edit-form { display:flex; gap:8px; align-items:center }
  .cargo-form input, .cargo-edit-form input { padding:8px 10px; border:1px solid rgba(0,0,0,.14); border-radius:10px }
  .btn { padding:8px 12px; border:none; border-radius:10px; cursor:pointer }
  .btn.primary { background:#2563eb; color:#fff }
  .btn.danger { background:#ef4444; color:#fff }
  .table-wrapper { margin-top:12px }
</style>
@endpush
@section('content')
<div class="container cargo-page">
  <h1>Gestión de Cargos</h1>

  @if(session('status'))
    <div class="alert success">{{ session('status') }}</div>
  @endif

  <form class="cargo-form" method="POST" action="{{ route('cargos.store') }}">
    @csrf
    <input type="text" name="nombre" placeholder="Nuevo cargo" required>
    <button class="btn primary" type="submit">Crear</button>
  </form>

  <div class="actions" style="margin-top:10px">
    <form method="GET" action="{{ route('cargos.index') }}" style="display:flex; gap:8px; align-items:center">
      <input type="text" name="q" value="{{ $q }}" placeholder="Buscar cargo...">
      <select name="per_page" onchange="this.form.submit()">
        @foreach([5,10,20,50] as $n)
          <option value="{{ $n }}" {{ (int)$perPage===$n?'selected':'' }}>{{ $n }}</option>
        @endforeach
      </select>
      <button class="btn" type="submit">Filtrar</button>
    </form>
  </div>

  <div class="table-wrapper">
    <table class="tabla-articulos">
      <thead>
        <tr>
          <th>#</th>
          <th>Nombre</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        @foreach($cargos as $cargo)
          <tr>
            <td>{{ $cargo->id }}</td>
            <td>
              <form class="cargo-edit-form" method="POST" action="{{ route('cargos.update', $cargo) }}">
                @csrf
                @method('PUT')
                <input type="text" name="nombre" value="{{ $cargo->nombre }}" required>
                <button class="btn primary" type="submit">Guardar</button>
              </form>
            </td>
            <td>
              <form method="POST" action="{{ route('cargos.destroy', $cargo) }}" onsubmit="return confirm('¿Eliminar cargo?')">
                @csrf
                @method('DELETE')
                <button class="btn danger" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="paginacion paginacion-compact">
    {{ $cargos->links() }}
  </div>
</div>
@endsection