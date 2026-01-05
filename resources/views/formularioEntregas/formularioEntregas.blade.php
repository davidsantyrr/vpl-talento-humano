@extends('layouts.app')
@section('title', 'Formulario de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/formularioEntregas.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>

<div class="container">
    <div class="panel">
    <h1 class="title">Formulario de Entregas</h1>
        <form action="{{ route('entregas.store') }}" method="POST" id="entregasForm">
            @csrf
            
            <div class="section">
                <div class="grid">
                    <div class="field">
                        <label>Tipo de documento</label>
                        <select name="tipo_documento">
                            <option value="CC">C.C</option>
                            <option value="TI">T.I</option>
                            <option value="EXTRANJERA">EXTRANJERA</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Número de documento</label>
                        <input type="text" id="numberDocumento" name="numberDocumento">
                        <div id="usuarioLookup" data-crear-url="{{ route('gestionUsuario.index') }}" class="small text-muted mt-1"></div>
                    </div>
                    <div class="field">
                        <label>Nombres</label>
                        <input type="text" id="nombre" name="nombre">
                    </div>
                    <div class="field">
                        <label>Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos">
                    </div>
                    <div class="field">
                        <label>Tipo</label>
                        <select id="tipoSelect" name="tipo">
                            <option value="prestamo">Préstamo</option>
                            <option value="primera vez">Primera vez</option>
                            <option value="periodica">Periódica</option>
                            <option value="cambio">Entrega de cambio</option>
                        </select>
                    </div>
                    <div class="field" id="field-operacion">
                        <label>Operación</label>
                        <select id="operacionSelect" name="operacion_id">
                            <option value="">Seleccione una operación</option>
                            @foreach($operations as $op)
                            <option value="{{ $op->id }}">{{ $op->operationName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field span-2" id="field-cargo" style="display:none;">
                        <label>Cargo</label>
                        @php($listCargos = isset($cargos) ? $cargos : \App\Models\Cargo::orderBy('nombre')->get())
                        <select id="cargoSelect" name="cargo_id" disabled title="Informativo">
                            <option value="">Seleccione un cargo</option>
                            @foreach($listCargos as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" id="cargoIdHidden" name="cargo_id" value="">
                        <small class="text-muted">Solo informativo, se autocompleta con el cargo del usuario.</small>
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn add" id="btnAnadirElemento" onclick="abrirModal()">Añadir elemento</button>
                    <button type="button" class="btn primary" id="btnSeleccionarRecepcion" onclick="abrirModalRecepcion()" style="display:none;">Seleccionar recepción</button>
                </div>
            </div>

            <div class="section">
                <div class="table-wrapper">
                    <table id="elementosFormTable">
                        <thead>
                            <tr>
                                <th>Elemento</th>
                                <th style="width:120px;">Cantidad</th>
                                <th style="width:120px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="elementosFormTbody"></tbody>
                    </table>
                </div>
            </div>

            <input type="hidden" name="elementos" id="elementosJson" value="[]">
            <input type="hidden" name="recepcion_id" id="recepcionIdHidden" value="">

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

            <input type="hidden" name="firma" id="firmaField">

            <div class="actions">
                <button type="submit" class="btn primary">Realizar entrega</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalElementos">
    <div>
        <h1>Elementos a entregar</h1>
        <div class="modal-grid">
            <div class="modal-field">
                <label>Producto</label>
                <select id="elementoSelect">
                    <option value="">Seleccione un producto</option>
                </select>
            </div>
            <div class="modal-field">
                <label>Cantidad</label>
                <input type="number" id="cantidadInput" name="cantidad" min="1" value="1" required>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn add" onclick="agregarElementoModal()">Agregar a lista</button>
        </div>
        <table class="modal-table">
            <thead>
                <tr>
                    <th>Elemento</th>
                    <th style="width:120px;">Cantidad</th>
                </tr>
            </thead>
            <tbody id="elementosTbody"></tbody>
        </table>
        <div class="modal-actions" style="margin-top:16px;">
            <button type="button" class="btn primary" onclick="guardarModal()">Añadir</button>
            <button type="button" class="btn secondary" onclick="cerrarModal()">Cancelar</button>
        </div>
    </div>
</div>

<div class="modal" id="modalRecepciones">
    <div>
        <h1>Seleccionar Recepción</h1>
        <div class="modal-grid" style="grid-template-columns: 1fr;">
            <div class="modal-field">
                <label>Buscar por número de documento</label>
                <input type="text" id="buscarRecepcionInput" placeholder="Ingrese número de documento">
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn primary" onclick="buscarRecepciones()">Buscar</button>
        </div>
        <div class="table-wrapper" style="max-height: 400px; overflow-y: auto;">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Elementos Recibidos</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="recepcionesTbody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            Ingrese un número de documento para buscar recepciones
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="modal-actions" style="margin-top:16px;">
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
    window.FormularioPageConfig = {
        allProducts: @json(($allProducts ?? collect())->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]))
    };
</script>
<script src="{{ asset('js/formularioEntregas.js') }}"></script>
<script>
  // Flag global para evitar listeners de submit externos
  window.__TH_AJAX_SUBMIT__ = true;
</script>
<script>
// Interceptar submit para generar comprobante con la plantilla y firma
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('entregasForm');
  if (!form) return;

  let isProcessing = false;

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    e.stopImmediatePropagation();
    e.stopPropagation();
    
    if (isProcessing) {
      console.log('Ya se está procesando la entrega');
      return false;
    }
    
    isProcessing = true;

    // Recolectar datos necesarios
    const registro = {
      id: null,
      tipo_documento: form.tipo_documento?.value || null,
      numero_documento: form.numberDocumento?.value || null,
      nombres: form.nombre?.value || null,
      apellidos: form.apellidos?.value || null,
      operacion: form.operacion_id?.options[form.operacion_id?.selectedIndex]?.text || null,
      tipo: form.tipo?.value || null,
      recibido: false,
      created_at: new Date().toISOString()
    };

    const elementos = JSON.parse(document.getElementById('elementosJson').value || '[]');

    const firmaField = document.getElementById('firmaField');
    const firmaData = {};
    if (firmaField && firmaField.value) {
      firmaData['entrega'] = firmaField.value;
    }

    try {
      const payloadPDF = { tipo: 'entrega', registro, elementos, firma: firmaData };
      const respPDF = await fetch('{{ route('comprobantes.generar') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payloadPDF)
      });
      const jsonPDF = await respPDF.json();
      if (!jsonPDF.success) {
        isProcessing = false;
        Swal.fire({icon:'error', title:'Error', text: jsonPDF.message || 'Error generando comprobante'});
        return false;
      }

      const formData = new FormData(form);
      formData.append('comprobante_path', jsonPDF.path);

      const respForm = await fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: formData
      });
      const jsonForm = await respForm.json();
      if (jsonForm.success) {
        Swal.fire({
          icon: 'success',
          title: 'Éxito',
          text: jsonForm.message || 'Entrega registrada correctamente'
        }).then(() => {
          // Redirigir recargando la misma página para evitar rutas inexistentes
          window.location.reload();
        });
      } else {
        isProcessing = false;
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: jsonForm.message || 'Error al registrar la entrega'
        });
      }
    } catch (err) {
      isProcessing = false;
      console.error('Error:', err);
      Swal.fire({icon:'error', title:'Error', text: 'No se pudo procesar la entrega'});
      return false;
    }
  }, true); // usar captura para ejecutar antes que otros listeners
});
</script>
@endsection