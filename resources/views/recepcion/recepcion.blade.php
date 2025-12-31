@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/recepcion/recepcion.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="recepcion container">

  @if(session('status'))
    <div class="alert success">{{ session('status') }}</div>
  @endif

  <div class="panel">
    
  {{-- <h1 class="title">Recepción de devoluciones</h1> --}}
    <form method="POST" action="{{ route('recepcion.store') }}" id="recepcionForm">
      @csrf
      <div class="section">
        <div class="grid">
          <div class="field">
            <label>Tipo de documento</label>
            <input type="text" name="tipo_doc" placeholder="Value" required>
          </div>
          <div class="field">
            <label>Número de documento</label>
            <input type="text" name="num_doc" placeholder="Value" required>
          </div>
          <div class="field">
            <label>Nombres</label>
            <input type="text" name="nombres" placeholder="Value" required>
          </div>
          <div class="field">
            <label>Apellidos</label>
            <input type="text" name="apellidos" placeholder="Value" required>
          </div>
          <div class="field span-2">
            <label>Operación</label>
            <select name="operation_id" required>
              <option value="">Value</option>
              @foreach($operations as $op)
                <option value="{{ $op->id }}">{{ $op->operationName }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="actions">
          <button type="button" class="btn add" id="addItemBtn">Añadir elemento</button>
        </div>
      </div>

      <div class="section">
        <div class="table-wrapper">
          <table class="tabla-items" id="itemsTable">
            <thead>
              <tr>
                <th>Elemento</th>
                <th style="width:120px;">Cantidad</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="section">
        <div class="firma">
          <label>Firma</label>
          <div class="firma-pad" id="firmaPad">
            <canvas id="firmaCanvas"></canvas>
          </div>
          <div class="firma-tools">
            <button type="button" class="btn" id="clearFirma">Limpiar firma</button>
          </div>
        </div>
      </div>

      <input type="hidden" name="items" id="itemsField">
      <input type="hidden" name="firma" id="firmaField">

      <div class="actions">
        <button type="submit" class="btn primary">Recibir devolución</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  window.RecepcionPageConfig = {
    allProducts: @json($allProducts->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]))
  };
</script>
<script src="{{ asset('js/recepcion/recepcion.js') }}"></script>
@endsection