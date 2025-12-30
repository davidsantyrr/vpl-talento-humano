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
<script>
(function(){
  const items = [];
  const tableBody = document.querySelector('#itemsTable tbody');
  const itemsField = document.getElementById('itemsField');
  function render(){
    tableBody.innerHTML = items.map(it => `<tr><td>${escapeHtml(it.nombre)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`).join('');
    itemsField.value = JSON.stringify(items);
  }
  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  document.getElementById('addItemBtn').addEventListener('click', function(){
    const nombre = prompt('Nombre del elemento');
    if (!nombre) return;
    const cantStr = prompt('Cantidad');
    const cant = parseInt(cantStr || '0', 10);
    if (!cant || cant < 1) return;
    items.push({ nombre, cantidad: cant });
    render();
  });

  // Canvas firma: ajustar tamaño al contenedor y escalar por DPR
  const canvas = document.getElementById('firmaCanvas');
  const pad = document.getElementById('firmaPad');
  const ctx = canvas.getContext('2d');
  function resizeCanvas(){
    const dpr = window.devicePixelRatio || 1;
    const cssWidth = Math.min(pad.clientWidth - 32, 600); // padding visual y límite
    const cssHeight = Math.max(160, Math.floor(cssWidth * 0.4));
    canvas.style.width = cssWidth + 'px';
    canvas.style.height = cssHeight + 'px';
    canvas.width = Math.floor(cssWidth * dpr);
    canvas.height = Math.floor(cssHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
  }
  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);

  let drawing = false;
  function getPos(e){
    const rect = canvas.getBoundingClientRect();
    const clientX = (e.touches ? e.touches[0].clientX : e.clientX);
    const clientY = (e.touches ? e.touches[0].clientY : e.clientY);
    return { x: clientX - rect.left, y: clientY - rect.top };
  }
  function start(e){ e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function move(e){ if(!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
  function end(){ drawing = false; }
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseleave', end);
  canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false}); canvas.addEventListener('touchend', end);
  document.getElementById('clearFirma').addEventListener('click', function(){ ctx.clearRect(0,0,canvas.width,canvas.height); });

  document.getElementById('recepcionForm').addEventListener('submit', function(){
    document.getElementById('firmaField').value = canvas.toDataURL('image/png');
    itemsField.value = JSON.stringify(items);
  });
})();
</script>
@endsection