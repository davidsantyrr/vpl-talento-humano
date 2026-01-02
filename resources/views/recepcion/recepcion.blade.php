@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/recepcion/recepcion.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="recepcion container">

  <div class="panel">
    
  <h1 class="title">Recepción de devoluciones</h1>
    <form method="POST" action="{{ route('recepcion.store') }}" id="recepcionForm">
      @csrf
      <div class="section">
        <div class="grid">
          <div class="field">
            <label>Tipo de documento</label>
            <select name="tipo_doc" id="tipoDocumento">
              <option value="CC">C.C</option>
              <option value="TI">T.I</option>
              <option value="EXTRANJERA">EXTRANJERA</option>
            </select>
          </div>
          <div class="field">
            <label>Número de documento</label>
            <input type="text" name="num_doc" id="numDocumento">
            <div id="usuarioLookupRecepcion" data-crear-url="{{ route('gestionUsuario.index') }}" class="small text-muted mt-1"></div>
          </div>
          <div class="field">
            <label>Nombres</label>
            <input type="text" name="nombres" id="nombresRecepcion">
          </div>
          <div class="field">
            <label>Apellidos</label>
            <input type="text" name="apellidos" id="apellidosRecepcion">
          </div>
          <div class="field span-2">
            <label>Operación</label>
            <select name="operation_id" id="operacionRecepcion">
              <option value="">Seleccione una operación</option>
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
                <th style="width:120px;">Acción</th>
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

      <input type="hidden" name="usuarios_id" id="usuariosIdHidden" value="">
      <input type="hidden" name="items" id="itemsField">
      <input type="hidden" name="firma" id="firmaField">

      <div class="actions">
        <button type="submit" class="btn primary">Recibir devolución</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modalElementosRecepcion">
  <div>
    <h1>Elementos a recibir</h1>
    <div class="modal-grid">
      <div class="modal-field">
        <label>Producto</label>
        <select id="elementoSelectRecepcion">
          <option value="">Seleccione un producto</option>
        </select>
      </div>
      <div class="modal-field">
        <label>Cantidad</label>
        <input type="number" id="cantidadInputRecepcion" name="cantidad" min="1" value="1" required>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn add" onclick="agregarElementoModalRecepcion()">Agregar a lista</button>
    </div>
    <table class="modal-table">
      <thead>
        <tr>
          <th>Elemento</th>
          <th style="width:120px;">Cantidad</th>
        </tr>
      </thead>
      <tbody id="elementosTbodyRecepcion"></tbody>
    </table>
    <div class="modal-actions" style="margin-top:16px;">
      <button type="button" class="btn primary" onclick="guardarModalRecepcion()">Añadir</button>
      <button type="button" class="btn secondary" onclick="cerrarModalRecepcion()">Cancelar</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Toast global
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
  });

  // Mostrar toast por mensajes de sesión
  (function(){
    const status = @json(session('status'));
    const error = @json(session('error'));
    if (status) {
      Toast.fire({ icon: 'success', title: status });
    } else if (error) {
      Toast.fire({ icon: 'error', title: error });
    }
  })();
</script>
<script>
  window.RecepcionPageConfig = {
    allProducts: @json($allProducts->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]))
  };
</script>
<script src="{{ asset('js/recepcion/recepcion.js') }}"></script>
<script src="{{ asset('js/recepcion/recepcionLookup.js') }}"></script>
<script src="{{ asset('js/recepcion/recepcionModal.js') }}"></script>
@endsection