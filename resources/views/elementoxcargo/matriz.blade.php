@extends('layouts.app')
@push('styles')
<style>
  .matrix-container{max-width:1200px;margin:20px auto;padding:0 16px}
  .matrix-table{width:100%;border-collapse:collapse}
  .matrix-table thead th{background:#0b1b2b;color:#fff;padding:10px;border:1px solid #e5e7eb;text-align:left}
  .matrix-table tbody td,.matrix-table tbody th{padding:10px;border:1px solid #e5e7eb;vertical-align:top}
  .matrix-cell{display:flex;flex-direction:column;gap:6px}
  .product-pill{background:#f3f6fb;border:1px solid #e5e7eb;border-radius:8px;padding:6px 8px;font-size:13px}
  .matrix-actions{display:flex;justify-content:flex-end;margin-bottom:10px}
</style>
@endpush
@section('content')
<x-NavEntregasComponente />
<div class="matrix-container">
  <div class="matrix-actions">
    <a href="{{ route('elementoxcargo.productos') }}" class="btn primary" style="text-decoration:none;">Volver</a>
  </div>
  <table class="matrix-table">
    <thead>
      <tr>
        <th style="width:240px;">Subárea \ Cargo</th>
        @foreach($cargos as $c)
          <th>{{ $c->nombre }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($subAreas as $sa)
        <tr>
          <th>{{ $sa->operationName }}</th>
          @foreach($cargos as $c)
            @php $list = $map[$sa->id][$c->id] ?? []; @endphp
            <td>
              <div class="matrix-cell">
                @forelse($list as $p)
                  <div class="product-pill">{{ $p['sku'] }} — {{ $p['name'] }}</div>
                @empty
                  <span style="color:#94a3b8">—</span>
                @endforelse
              </div>
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection