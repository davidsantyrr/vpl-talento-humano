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
          <div class="field">
            <label>Tipo</label>
            <select id="tipoRecepcionSelect" name="tipo">
              <option value="cambio">Recepción para cambio</option>
              <option value="prestamo">Recepción de préstamo</option>
            </select>
          </div>
          <div class="field" id="field-operacion-recepcion">
            <label>Operación</label>
            <select id="operacionRecepcion" disabled>
              <option value="">Seleccione una operación</option>
              @foreach($operations as $op)
                <option value="{{ $op->id }}">{{ $op->operationName }}</option>
              @endforeach
            </select>
            <input type="hidden" id="operacionIdHidden" name="operation_id" value="">
          </div>
        </div>
        <div class="actions">
          <button type="button" class="btn add" id="addItemBtn">Añadir elemento</button>
          <button type="button" class="btn primary" id="btnSeleccionarEntrega" style="display:none;">Seleccionar entrega</button>
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
      <input type="hidden" name="firma" id="firmaField" value="">
      <input type="hidden" name="entrega_id" id="entregaIdHidden" value="">

      <div class="actions">
        <button type="submit" class="btn primary" id="btnSubmitRecepcion" disabled>Recibir devolución</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modalEntregas">
  <div>
    <h1>Seleccionar Entrega (Préstamo)</h1>
    <div class="modal-grid" style="grid-template-columns: 1fr;">
      <div class="modal-field">
        <label>Buscar por número de documento</label>
        <input type="text" id="buscarEntregaInput" placeholder="Ingrese número de documento">
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn primary" onclick="buscarEntregasPrestamo()">Buscar</button>
    </div>
    <div class="table-wrapper" style="max-height: 400px; overflow-y: auto;">
      <table class="modal-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Elementos Entregados</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody id="entregasTbody">
          <tr>
            <td colspan="5" style="text-align: center; padding: 20px;">
              Ingrese un número de documento para buscar entregas
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="modal-actions" style="margin-top:16px;">
      <button type="button" class="btn secondary" onclick="cerrarModalEntregas()">Cancelar</button>
    </div>
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
  // Flag global: evitar listeners externos de submit
  window.__TH_AJAX_SUBMIT__ = true;
</script>
<script src="{{ asset('js/recepcion/recepcion.js') }}"></script>
<script src="{{ asset('js/recepcion/recepcionLookup.js') }}"></script>
<script src="{{ asset('js/recepcion/recepcionModal.js') }}"></script>
<script src="{{ asset('js/recepcion/recepcionEntregasModal.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const canvas = document.getElementById('firmaCanvas');
  const firmaField = document.getElementById('firmaField');
  const btnSubmit = document.getElementById('btnSubmitRecepcion');
  if (!canvas || !firmaField || !btnSubmit) return;

  let drawing = false;
  function markSigned(){
    try { firmaField.value = canvas.toDataURL('image/png'); } catch(_) {}
    if (firmaField.value && firmaField.value.length > 50) {
      btnSubmit.disabled = false;
    }
  }
  function clearSigned(){ firmaField.value = ''; btnSubmit.disabled = true; }

  canvas.addEventListener('mousedown', function(e){ drawing = true; markSigned(); });
  canvas.addEventListener('mousemove', function(e){ if(drawing) markSigned(); });
  canvas.addEventListener('mouseup', function(){ drawing = false; });
  canvas.addEventListener('mouseleave', function(){ drawing = false; });
  canvas.addEventListener('touchstart', function(){ drawing = true; markSigned(); }, {passive:false});
  canvas.addEventListener('touchmove', function(){ if(drawing) markSigned(); }, {passive:false});
  canvas.addEventListener('touchend', function(){ drawing = false; });

  const clearBtn = document.getElementById('clearFirma');
  clearBtn && clearBtn.addEventListener('click', clearSigned);

  clearSigned();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('recepcionForm');
  if (!form) return;

  let isProcessing = false;

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    e.stopImmediatePropagation();
    e.stopPropagation();
    
    if (isProcessing) {
      console.log('Ya se está procesando la recepción');
      return false;
    }
    
    isProcessing = true;

    // Recolectar datos
    const registro = {
      id: null,
      tipo_documento: form.tipo_doc?.value || null,
      numero_documento: form.num_doc?.value || null,
      nombres: form.nombres?.value || null,
      apellidos: form.apellidos?.value || null,
      operacion: (function(){ const el = document.getElementById('operacionRecepcion'); return el ? (el.options[el.selectedIndex]?.text || null) : null; })(),
      tipo: form.tipo?.value || null,
      recibido: false,
      created_at: new Date().toISOString()
    };

    // Elementos desde campo hidden items
    const elementos = JSON.parse(document.getElementById('itemsField')?.value || '[]');

    // Firma
    const firmaField = document.getElementById('firmaField');
    const firmaData = {};
    if (firmaField && !firmaField.value) {
      const canvas = document.getElementById('firmaCanvas');
      if (canvas) {
        try { firmaField.value = canvas.toDataURL('image/png'); } catch(_) {}
      }
    }
    if (!firmaField || !firmaField.value) {
      isProcessing = false;
      Swal.fire({ icon:'error', title:'Firma requerida', text:'Por favor, dibuje su firma antes de continuar.' });
      return false;
    }
    if (firmaField && firmaField.value) {
      firmaData['recepcion'] = firmaField.value;
    }

    try {
      // Mostrar SweetAlert de carga
      Swal.fire({
        title: 'Procesando recepción...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      // 1. Generar comprobante PDF
      const payloadPDF = {
        tipo: 'recepcion',
        registro: registro,
        elementos: elementos,
        firma: firmaData
      };

      const respPDF = await fetch('{{ route('comprobantes.generar') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payloadPDF)
      });

      const jsonPDF = await respPDF.json();
      if (!jsonPDF.success) {
        isProcessing = false;
        Swal.close();
        Swal.fire({icon:'error', title:'Error', text: jsonPDF.message || 'Error generando comprobante'});
        return false;
      }

      // 2. Enviar formulario via AJAX (sin disparar evento submit)
      const formData = new FormData(form);
      formData.append('comprobante_path', jsonPDF.path);

      const respForm = await fetch(form.action, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
      });

      const jsonForm = await respForm.json();
      
      if (jsonForm.success) {
        Swal.close();
        Toast.fire({ icon: 'success', title: jsonForm.message || 'Recepción registrada correctamente' });
        setTimeout(() => window.location.reload(), 900);
      } else {
        isProcessing = false;
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: jsonForm.message || 'Error al registrar la recepción'
        });
      }

    } catch (err) {
      isProcessing = false;
      console.error('Error:', err);
      Swal.close();
      Swal.fire({icon:'error', title:'Error', text: 'No se pudo procesar la recepción'});
      return false;
    }
  }, true); // fase captura para preceder a otros listeners
});
</script>
@endsection