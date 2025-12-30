@php($status = session('status'))
@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/articulos/articulos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="articulos-page container">
  <header class="page-header">
    <h1>Artículos (requisición) y Stock (VSP)</h1>
  </header>

  {{-- Tabs de estatus --}}
  <div class="tabs">
    <button class="tab-btn active" data-status="disponible">Disponibles</button>
    <button class="tab-btn" data-status="prestado">Prestados</button>
    <button class="tab-btn" data-status="perdido">Perdidos</button>
    <button class="tab-btn" data-status="destruido">Destruidos</button>
  </div>

  <p class="page-subtitle">Gestiona inventario en la BD 3 (bodega, ubicación, estatus y stock) para artículos provenientes de requisición.</p>

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
      </form>
    </div>

    {!! $paginationHtml !!}
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function(){
    const statusMsg = @json($status);
    const errorMsg = @json(session('error'));
    if (statusMsg) {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: statusMsg, showConfirmButton: false, timer: 2500, timerProgressBar: true });
    }
    if (errorMsg) {
      Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: errorMsg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }

    function openLocation(row){
      const sku = row.dataset.sku;
      let bodega = row.dataset.bodega || '';
      let ubicacion = row.dataset.ubicacion || '';

      const UBI_ALL = @json(\Illuminate\Support\Facades\DB::connection('mysql_third')
        ->table('ubicaciones')->select('id','bodega','ubicacion')->orderBy('bodega')->orderBy('ubicacion')->get());
      const bodegas = [...new Set(UBI_ALL.map(u => u.bodega))];
      function buildBodegaOptions(selected){
        return bodegas.map(b => `<option value="${b}" ${selected===b?'selected':''}>${b}</option>`).join('');
      }
      function buildUbicacionOptions(bod, selected){
        const list = UBI_ALL.filter(u => !bod || u.bodega === bod);
        let html = `<option value="">Seleccione ubicación</option>`;
        html += list.map(u => `<option value="${u.ubicacion}" ${selected===u.ubicacion?'selected':''} data-bodega="${u.bodega}">${u.ubicacion}</option>`).join('');
        return html;
      }

      Swal.fire({
        title: `Ubicación (${sku})`,
        html: `
          <div class="modal-grid">
            <div class="field">
              <label>Bodega</label>
              <select id="loc-bodega" class="sw-select">
                <option value="">Seleccione bodega</option>
                ${buildBodegaOptions(bodega)}
              </select>
            </div>
            <div class="field">
              <label>Ubicación</label>
              <select id="loc-ubicacion" class="sw-select">
                ${buildUbicacionOptions(bodega, ubicacion)}
              </select>
            </div>
          </div>
        `,
        focusConfirm: false,
        customClass: { popup: 'sw-popup' },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        didOpen: () => {
          const bodSel = document.getElementById('loc-bodega');
          const ubiSel = document.getElementById('loc-ubicacion');
          bodSel.addEventListener('change', function(){
            const b = bodSel.value || '';
            ubiSel.innerHTML = buildUbicacionOptions(b, '');
            const realOpts = Array.from(ubiSel.options).filter(o => o.value);
            if (realOpts.length === 1) { ubiSel.value = realOpts[0].value; }
          });
          ubiSel.addEventListener('change', function(){
            const opt = ubiSel.options[ubiSel.selectedIndex];
            const dbod = opt && opt.dataset ? opt.dataset.bodega : '';
            if (ubiSel.value && dbod) { bodSel.value = dbod; }
            else if (!ubiSel.value) { bodSel.value = ''; }
          });
        },
        preConfirm: () => {
          const bod = document.getElementById('loc-bodega').value;
          const ubi = document.getElementById('loc-ubicacion').value;
          return { bodega: bod, ubicacion: ubi };
        }
      }).then(res => {
        if (!res.isConfirmed || !res.value) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ url('/articulos') }}/${sku}`;
        const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
        const per = document.createElement('input'); per.type = 'hidden'; per.name = 'per_page'; per.value = '{{ (int)($perPage ?? 20) }}';
        const b = document.createElement('input'); b.type = 'hidden'; b.name = 'bodega'; b.value = res.value.bodega || '';
        const u = document.createElement('input'); u.type = 'hidden'; u.name = 'ubicacion'; u.value = res.value.ubicacion || '';
        // mantener estatus y stock actuales del row
        const e = document.createElement('input'); e.type = 'hidden'; e.name = 'estatus'; e.value = row.dataset.estatus || 'disponible';
        const s = document.createElement('input'); s.type = 'hidden'; s.name = 'stock'; s.value = String(row.dataset.stock || 0);
        form.appendChild(csrf); form.appendChild(per); form.appendChild(b); form.appendChild(u); form.appendChild(e); form.appendChild(s);
        document.body.appendChild(form);
        form.submit();
      });
    }

    function openEditor(row){
      const sku = row.dataset.sku;
      const currentStatus = (row.dataset.estatus || 'disponible');
      let estatus = currentStatus;
      let stock = Number(row.dataset.stock || 0);

      Swal.fire({
        title: `Editar (${sku})`,
        html: `
          <div class="modal-grid">
            <div class="field">
              <label>Estatus</label>
              <select id="sw-estatus" class="sw-select">
                <option value="disponible" ${estatus==='disponible'?'selected':''}>Disponible</option>
                <option value="perdido" ${estatus==='perdido'?'selected':''}>Perdido</option>
                <option value="prestado" ${estatus==='prestado'?'selected':''}>Prestado</option>
                <option value="destruido" ${estatus==='destruido'?'selected':''}>Destruido</option>
              </select>
            </div>
            <div class="field">
              <label id="sw-stock-label">Stock</label>
              <input id="sw-stock" type="number" min="0" value="${stock}" class="sw-input" />
              <small id="sw-hint" class="text-muted"></small>
            </div>
          </div>
        `,
        focusConfirm: false,
        customClass: { popup: 'sw-popup' },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        didOpen: () => {
          const sel = document.getElementById('sw-estatus');
          const label = document.getElementById('sw-stock-label');
          const hint = document.getElementById('sw-hint');
          const input = document.getElementById('sw-stock');
          function updateHint(){
            const target = sel.value;
            if (target !== currentStatus) {
              label.textContent = 'Cantidad a transferir';
              hint.textContent = `Se descontará de "${currentStatus}" y se sumará a "${target}".`;
              input.value = '';
            } else {
              label.textContent = 'Stock';
              hint.textContent = '';
              input.value = stock;
            }
          }
          sel.addEventListener('change', updateHint);
          updateHint();
        },
        preConfirm: () => {
          const target = document.getElementById('sw-estatus').value;
          const qtyStr = document.getElementById('sw-stock').value;
          const qty = Number(qtyStr);
          if (qtyStr === '' || qty < 0) {
            Swal.showValidationMessage('Cantidad inválida');
            return false;
          }
          if (target !== currentStatus && qty > stock) {
            Swal.showValidationMessage('No puedes mover más de lo disponible en este estatus');
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Cantidad supera el stock del estatus actual', showConfirmButton: false, timer: 2500 });
            return false;
          }
          return { targetStatus: target, qty };
        }
      }).then(res => {
        if (!res.isConfirmed || !res.value) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ url('/articulos') }}/${sku}`;
        const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
        const per = document.createElement('input'); per.type = 'hidden'; per.name = 'per_page'; per.value = '{{ (int)($perPage ?? 20) }}';
        const b = document.createElement('input'); b.type = 'hidden'; b.name = 'bodega'; b.value = row.dataset.bodega || '';
        const u = document.createElement('input'); u.type = 'hidden'; u.name = 'ubicacion'; u.value = row.dataset.ubicacion || '';

        const e = document.createElement('input'); e.type = 'hidden'; e.name = 'estatus'; e.value = res.value.targetStatus || currentStatus;
        const s = document.createElement('input'); s.type = 'hidden'; s.name = 'stock'; s.value = String(res.value.qty || 0);
        form.appendChild(csrf); form.appendChild(per); form.appendChild(b); form.appendChild(u); form.appendChild(e); form.appendChild(s);

        // enviar from_status si cambia el estatus
        if (res.value.targetStatus !== currentStatus) {
          const f = document.createElement('input'); f.type = 'hidden'; f.name = 'from_status'; f.value = currentStatus;
          form.appendChild(f);
        }
        document.body.appendChild(form);
        form.submit();
      });
    }

    // vincular botones
    document.querySelectorAll('.btn-icon.location').forEach(function(btn){
      const row = btn.closest('tr');
      if(!row) return;
      btn.addEventListener('click', function(){ openLocation(row); });
    });
    document.querySelectorAll('.btn-icon.edit').forEach(function(btn){
      const row = btn.closest('tr');
      if(!row) return;
      btn.addEventListener('click', function(){ openEditor(row); });
    });

    // Filtrado por pestañas
    const tabs = document.querySelectorAll('.tab-btn');
    const rows = document.querySelectorAll('.tabla-articulos tbody tr');
    function applyFilter(st) {
      rows.forEach(tr => {
        const est = (tr.dataset.estatus || '').toLowerCase();
        tr.style.display = (est === st) ? '' : 'none';
      });
    }
    tabs.forEach(btn => {
      btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        applyFilter(btn.dataset.status);
      });
    });
    applyFilter('disponible');
  })();
</script>
@endsection