@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/elementoxcargo/matriz.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="matrix-container">
  <div class="matrix-filters">
    <div class="filter">
      <label for="f-subarea">Filtrar Operación</label>
      <select id="f-subarea">
        <option value="">Todas</option>
        @foreach($subAreas as $sa)
          <option value="sa-{{ $sa->id }}">{{ $sa->operationName }}</option>
        @endforeach
      </select>
    </div>
    <div class="filter">
      <label for="f-cargo">Filtrar cargo</label>
      <select id="f-cargo">
        <option value="">Todos</option>
        @foreach($cargos as $c)
          <option value="c-{{ $c->id }}">{{ $c->nombre }}</option>
        @endforeach
      </select>
    </div>
    <div class="filters-actions">
      <a href="{{ route('elementoxcargo.productos') }}" class="btn back">Volver</a>
    </div>
  </div>

  <div class="matrix-wrapper">
    <table class="matrix-table" id="matrixTable">
      <thead>
        <tr>
          <th class="sticky left">Operación \ Cargo</th>
          @foreach($cargos as $c)
            <th class="sticky top" data-cargo="c-{{ $c->id }}">{{ $c->nombre }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($subAreas as $sa)
          <tr data-subarea="sa-{{ $sa->id }}">
            <th class="sticky left">{{ $sa->operationName }}</th>
            @foreach($cargos as $c)
              @php $list = $map[$sa->id][$c->id] ?? []; @endphp
              <td>
                <div class="matrix-cell">
                  @forelse($list as $p)
                    <div class="product-pill"><span class="sku">{{ $p['sku'] }}</span> <span class="name">— {{ $p['name'] }}</span></div>
                  @empty
                    <span class="empty">—</span>
                  @endforelse
                </div>
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<script>
(function(){
  const subSel = document.getElementById('f-subarea');
  const carSel = document.getElementById('f-cargo');
  const table = document.getElementById('matrixTable');
  function applyFilters(){
    const sub = subSel.value; const car = carSel.value;
    // Filtrar filas por subárea
    table.querySelectorAll('tbody tr').forEach(tr => {
      tr.style.display = (!sub || tr.getAttribute('data-subarea') === sub) ? '' : 'none';
    });
    // Filtrar columnas por cargo
    const headers = Array.from(table.querySelectorAll('thead th')).slice(1); // skip first
    const idxKeep = headers.map((th, i) => (!car || th.getAttribute('data-cargo') === car) ? i : -1).filter(i => i>=0);
    const showAll = !car;
    headers.forEach((th, i) => { th.style.display = (showAll || idxKeep.includes(i)) ? '' : 'none'; });
    table.querySelectorAll('tbody tr').forEach(tr => {
      const tds = Array.from(tr.querySelectorAll('td'));
      tds.forEach((td, i) => { td.style.display = (showAll || idxKeep.includes(i)) ? '' : 'none'; });
    });
  }
  subSel.addEventListener('change', applyFilters);
  carSel.addEventListener('change', applyFilters);
})();
</script>
@endsection