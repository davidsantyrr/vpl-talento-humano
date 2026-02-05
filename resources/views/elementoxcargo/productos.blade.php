@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/elementoxcargo/producto.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="ecargo-page container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Asignar elementos a cargo</h1>
            <div class="muted-text">Selecciona cargo, Operación y producto para asignar</div>
        </div>
        <a href="{{ route('elementoxcargo.productos.matriz') }}" class="btn primary">Ver matriz</a>
    </div>

    <form id="assignForm" method="POST" action="{{ route('elementoxcargo.productos.store') }}">
        @csrf
        <input type="hidden" name="cargo_id" id="cargoHidden" value="{{ $cargoId }}">
        <div class="controls-row">
            <div class="left form-field cargo-search">
                <label for="cargoInput">Cargo</label>
                <input id="cargoInput" class="cargo-input" placeholder="Escribe para buscar cargo" autocomplete="off" value="">
                <ul id="cargoDropdown" class="cargo-dropdown" role="listbox" aria-hidden="true"></ul>
            </div>

            <div class="middle form-field cargo-search">
                <label for="subAreaInput">Operación</label>
                <input id="subAreaInput" class="cargo-input" placeholder="Escribe para buscar subárea" autocomplete="off" value="">
                <input type="hidden" id="subAreaHidden" name="sub_area_id" value="{{ $subAreaId }}">
                <ul id="subAreaDropdown" class="cargo-dropdown" role="listbox" aria-hidden="true"></ul>
            </div>

            <div class="right actions" style="gap:10px;">
                <div class="form-field product-search">
                    <label for="skuInput">Elemento (SKU)</label>
                    <input id="skuInput" class="product-input" placeholder="Escribe para buscar por SKU o nombre" autocomplete="off">
                    <input type="hidden" id="skuHidden" name="sku">
                    <ul id="productDropdown" class="product-dropdown" role="listbox" aria-hidden="true"></ul>
                </div>
                <button id="submitAssign" class="btn primary" type="submit" {{ $cargoId ? '' : 'disabled' }}>Añadir</button>
            </div>
        </div>
    </form>

    <div class="table-wrapper">
        <table class="tabla-articulos">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Elemento</th>
                    <th>Cargo</th>
                    <th>Operación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($asignaciones as $a)
                <tr>
                    <td>{{ $a->sku }}</td>
                    <td>{{ $a->name_produc }}</td>
                    <td>{{ $a->cargo->nombre }}</td>
                    <td>{{ $a->subArea->operationName ?? '-' }}</td>
                    <td>
                        <form method="POST" action="{{ route('elementoxcargo.productos.destroy', $a) }}"
                            class="form-delete">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">Borrar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="color:#64748b">No hay asignaciones aún.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">
        <div class="per-page-row"
            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px">
            <form method="GET" action="{{ route('elementoxcargo.productos') }}" class="form-inline">
                <input type="hidden" name="cargo_id" value="{{ $cargoId }}">
                <input type="hidden" name="sub_area_id" value="{{ $subAreaId }}">
                <label for="per_page">Ver</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()">
                    @foreach([5,10,20,50] as $size)
                    <option value="{{ $size }}" {{ (int)$perPage===$size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                <span>artículos</span>
            </form>
            <div class="pagination-row">{{ $asignaciones->appends(['per_page' =>
                $perPage])->links('pagination::bootstrap-4') }}</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function(){
      // Toast helpers
      function showToast(type, msg){
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: type,
          title: msg,
          showConfirmButton: false,
          timer: 2500,
          timerProgressBar: true
        });
      }
      // Session toasts
      @if(session('status'))
        showToast('success', @json(session('status')));
      @endif
      @if(session('errorMessage'))
        showToast('error', @json(session('errorMessage')));
      @endif

      // Delete confirmation
      document.querySelectorAll('.form-delete').forEach(function(form){
        form.addEventListener('submit', function(e){
          e.preventDefault();
          Swal.fire({
            title: '¿Quitar asignación?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, borrar',
            cancelButtonText: 'Cancelar'
          }).then((result) => {
            if (result.isConfirmed) {
              form.submit();
            }
          });
        });
      });

      // al seleccionar cargo, habilitar el envío (apuntar al botón correcto)
      function enableSubmit(){ const btn = document.getElementById('submitAssign'); if (btn) btn.disabled = false; }

      // cargos dropdown
      const cargos = @json($cargos->map(fn($c)=>['id'=>$c->id,'nombre'=>$c->nombre]));
      const cargoInput = document.getElementById('cargoInput');
      const cargoHidden = document.getElementById('cargoHidden');
      const cargoDropdown = document.getElementById('cargoDropdown');

      function renderCargos(list){
        cargoDropdown.innerHTML = '';
        list.forEach(it => {
          const li = document.createElement('li');
          li.className = 'cargo-item';
          li.textContent = it.nombre;
          li.addEventListener('click', () => {
            cargoInput.value = it.nombre;
            cargoHidden.value = it.id;
            cargoDropdown.setAttribute('aria-hidden','true');
            enableSubmit();
          });
          cargoDropdown.appendChild(li);
        });
        cargoDropdown.setAttribute('aria-hidden', list.length ? 'false' : 'true');
      }
      function filterCargos(term){
        const t = term.trim().toLowerCase();
        if(!t) return cargos.slice();
        return cargos.filter(c => c.nombre.toLowerCase().includes(t));
      }
      cargoInput?.addEventListener('focus', function(){ renderCargos(filterCargos(this.value)); });
      cargoInput?.addEventListener('input', function(){ renderCargos(filterCargos(this.value)); });
      document.addEventListener('click', function(e){ if(!cargoDropdown.contains(e.target) && e.target!==cargoInput){ cargoDropdown.setAttribute('aria-hidden','true'); } });

      // subAreas dropdown (igual a cargo)
      const subAreas = @json($subAreas->map(fn($s)=>['id'=>$s->id,'nombre'=>$s->operationName]));
      const subInput = document.getElementById('subAreaInput');
      const subHidden = document.getElementById('subAreaHidden');
      const subDropdown = document.getElementById('subAreaDropdown');
      function renderSubAreas(list){
        subDropdown.innerHTML = '';
        list.forEach(it => {
          const li = document.createElement('li'); li.className = 'cargo-item'; li.textContent = it.nombre;
          li.addEventListener('click', () => { subInput.value = it.nombre; subHidden.value = it.id; subDropdown.setAttribute('aria-hidden','true'); enableSubmit(); });
          subDropdown.appendChild(li);
        });
        subDropdown.setAttribute('aria-hidden', list.length ? 'false' : 'true');
      }
      function filterSub(term){ const t = term.trim().toLowerCase(); if(!t) return subAreas.slice(); return subAreas.filter(s => s.nombre.toLowerCase().includes(t)); }
      subInput?.addEventListener('focus', function(){ renderSubAreas(filterSub(this.value)); });
      subInput?.addEventListener('input', function(){ renderSubAreas(filterSub(this.value)); });
      document.addEventListener('click', function(e){ if(!subDropdown.contains(e.target) && e.target!==subInput){ subDropdown.setAttribute('aria-hidden','true'); } });

      // productos dropdown (desde API role-filtrada)
      const input = document.getElementById('skuInput');
      const hidden = document.getElementById('skuHidden');
      const dropdown = document.getElementById('productDropdown');

      async function fetchCargoProductos(cargoId, subAreaId, q){
        try{
          let url = `${window.location.origin}/cargo-productos`;
          const params = new URLSearchParams();
          if (cargoId) params.append('cargo_id', cargoId);
          if (subAreaId) params.append('sub_area_id', subAreaId);
          if (q) params.append('q', String(q).trim().toLowerCase());
          if (params.toString()) url += '?' + params.toString();
          const resp = await fetch(url);
          if (!resp.ok) return [];
          const data = await resp.json();
          return Array.isArray(data) ? data.map(d => ({ sku: d.sku, name: d.name_produc })) : [];
        } catch(e){ console.error('cargo-productos fetch failed', e); return []; }
      }

      function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

      function renderList(list){
        dropdown.innerHTML = '';
        list.forEach((it)=>{
          const li = document.createElement('li'); li.setAttribute('role','option'); li.className = 'product-item';
          li.innerHTML = '<div>' + escapeHtml(it.sku) + '</div><span>' + escapeHtml(it.name) + '</span>';
          li.addEventListener('click', ()=> { hidden.value = it.sku; input.value = it.sku + ' — ' + it.name; dropdown.setAttribute('aria-hidden','true'); enableSubmit(); });
          dropdown.appendChild(li);
        });
        dropdown.setAttribute('aria-hidden', list.length ? 'false' : 'true');
      }

      function filterList(list, term){ const t = term.trim().toLowerCase(); if(!t) return list.slice(); return list.filter(a => a.sku.toLowerCase().includes(t) || a.name.toLowerCase().includes(t)); }

      async function loadAndRender(){
        const cargoId = document.getElementById('cargoHidden')?.value || '';
        const subAreaId = document.getElementById('subAreaHidden')?.value || '';
        const term = input.value || '';
        const items = await fetchCargoProductos(cargoId, subAreaId, term);
        renderList(items);
      }

      input?.addEventListener('focus', function(){ loadAndRender(); });
      input?.addEventListener('input', async function(){
        const cargoId = document.getElementById('cargoHidden')?.value || '';
        const subAreaId = document.getElementById('subAreaHidden')?.value || '';
        const items = await fetchCargoProductos(cargoId, subAreaId, this.value);
        // Fallback client-side filter in case backend returned broader set
        renderList(filterList(items, this.value));
        hidden.value = '';
      });
      document.addEventListener('click', function(e){ if(!dropdown.contains(e.target) && e.target!==input){ dropdown.setAttribute('aria-hidden','true'); } });

      // Al enviar: si no se seleccionó del dropdown, usar el texto escrito como SKU
      const form = document.getElementById('assignForm');
      form?.addEventListener('submit', function(e){
        const hiddenVal = (hidden.value || '').trim();
        let typed = (input.value || '').trim();
        if (!hiddenVal && typed) {
          // Si viene en formato "SKU — Nombre", tomar lo anterior a "—"
          const partsDash = typed.split('—');
          if (partsDash.length > 1) typed = partsDash[0].trim();
          // También limpiar paréntesis o sufijos comunes
          typed = typed.split('(')[0].trim();
          // Asignar al hidden para cumplir validación del backend
          hidden.value = typed;
        }
        if (!hidden.value || hidden.value.trim() === '') {
          e.preventDefault();
          showToast('warning', 'Seleccione un elemento o escriba el SKU');
        }
      });
    })();
    </script>
</div>
@endsection