@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/elementoxcargo/producto.css') }}">
@endpush
@section('content')
<div class="container">
  <h1>Asignar productos a cargo</h1>

  <form method="POST" action="{{ route('elementoxcargo.productos.store') }}">
    @csrf
    <input type="hidden" name="cargo_id" id="cargoHidden" value="{{ $cargoId }}">
    <div class="controls-row">
      <div class="left form-field cargo-search">
        <label for="cargoInput">Cargo</label>
        <input id="cargoInput" class="cargo-input" placeholder="Escribe para buscar cargo" autocomplete="off" value="">
        <ul id="cargoDropdown" class="cargo-dropdown" role="listbox" aria-hidden="true"></ul>
      </div>

      <div class="center product-search form-field">
        <label for="skuInput">Producto (SKU)</label>
        <input id="skuInput" class="product-input" placeholder="Escribe para buscar por SKU o nombre" autocomplete="off">
        <input type="hidden" id="skuHidden" name="sku">
        <ul id="productDropdown" class="product-dropdown" role="listbox" aria-hidden="true"></ul>
      </div>

      <div class="right actions">
        <button class="btn primary" type="submit" {{ $cargoId ? '' : 'disabled' }}>Añadir</button>
      </div>
    </div>
  </form>

  <div class="table-wrapper">
    <table class="tabla-articulos">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Nombre</th>
          <th>Cargo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($asignaciones as $a)
          <tr>
            <td>{{ $a->sku }}</td>
            <td>{{ $a->name_produc }}</td>
            <td>{{ $a->cargo->nombre }}</td>
            <td>
              <form method="POST" action="{{ route('elementoxcargo.productos.destroy', $a) }}" class="form-delete">
                @csrf
                @method('DELETE')
                <button class="btn danger" type="submit">Borrar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" style="color:#64748b">No hay asignaciones aún.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="paginacion">
    <div class="per-page-row" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px">
      <form method="GET" action="{{ route('elementoxcargo.productos') }}" class="form-inline">
        <input type="hidden" name="cargo_id" value="{{ $cargoId }}">
        <label for="per_page">Ver</label>
        <select id="per_page" name="per_page" onchange="this.form.submit()">
          @foreach([5,10,20,50] as $size)
            <option value="{{ $size }}" {{ (int)$perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
        <span>artículos</span>
      </form>
      <div class="pagination-row">{{ $asignaciones->appends(['per_page' => $perPage])->links('pagination::bootstrap-4') }}</div>
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
            // al seleccionar cargo, habilitar el envío
            document.querySelector('.btn.primary').disabled = false;
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

      // productos dropdown
      const all = @json($allProducts->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]));
      const input = document.getElementById('skuInput');
      const hidden = document.getElementById('skuHidden');
      const dropdown = document.getElementById('productDropdown');
      function renderList(list){
        dropdown.innerHTML = '';
        list.forEach((it)=>{
          const li = document.createElement('li');
          li.setAttribute('role','option');
          li.className = 'product-item';
          li.innerHTML = '<div>' + escapeHtml(it.sku) + '</div><span>' + escapeHtml(it.name) + '</span>';
          li.addEventListener('click', ()=> { hidden.value = it.sku; input.value = it.sku + ' — ' + it.name; dropdown.setAttribute('aria-hidden','true'); });
          dropdown.appendChild(li);
        });
        dropdown.setAttribute('aria-hidden', list.length ? 'false' : 'true');
      }
      function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
      function filterAll(term){ const t = term.trim().toLowerCase(); if(!t) return all.slice(); return all.filter(a => a.sku.toLowerCase().includes(t) || a.name.toLowerCase().includes(t)); }
      input?.addEventListener('focus', function(){ renderList(filterAll(this.value)); });
      input?.addEventListener('input', function(){ renderList(filterAll(this.value)); hidden.value = ''; });
      document.addEventListener('click', function(e){ if(!dropdown.contains(e.target) && e.target!==input){ dropdown.setAttribute('aria-hidden','true'); } });
    })();
  </script>
</div>
@endsection