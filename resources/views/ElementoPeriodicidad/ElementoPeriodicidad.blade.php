@extends('layouts.app')
@section('title', 'Calendario anual de entregas periódicas')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/elementoPeriodicidad/elementoPeriodicidad.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1>Calendario de entregas periódicas - {{ $year }}</h1>
        <div>
            <a href="{{ route('elementoPeriodicidad.index', ['year' => $prevYear]) }}" class="btn btn-outline-secondary btn-sm">&laquo; {{ $prevYear }}</a>
            <a href="{{ route('elementoPeriodicidad.index', ['year' => $nextYear]) }}" class="btn btn-outline-secondary btn-sm ms-2">{{ $nextYear }} &raquo;</a>
        </div>
    </div>

    <div class="mb-3">
        <strong>Leyenda:</strong>
        <span class="badge bg-danger ms-1">Urgente (esta semana)</span>
        <span class="badge bg-warning text-dark ms-1">Próxima semana</span>
        <span class="badge bg-info text-dark ms-1">En 2 semanas</span>
        <span class="badge bg-success ms-1">OK (más adelante)</span>
        <span class="badge bg-light text-dark ms-1">Sin entregas</span>
    </div>

    <div class="row">
        @foreach($months as $mIndex => $month)
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <div class="card month-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="fw-semibold">{{ $month['label'] }}</div>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#month-{{ $mIndex }}">Ver semanas</button>
                    </div>
                    <div class="collapse" id="month-{{ $mIndex }}">
                        <div class="card-body">
                            <div class="row g-2">
                                @foreach($month['weeks'] as $wkKey => $wk)
                                    @php
                                        $colors = ['urgent'=>'danger','soon'=>'warning','warning'=>'info','ok'=>'success','empty'=>'light'];
                                        $bg = $colors[$wk['urgency']] ?? 'light';
                                        $textClass = in_array($bg, ['danger','success']) ? 'text-white' : 'text-dark';
                                    @endphp
                                    <div class="col-6 col-sm-4 col-md-6 col-lg-12">
                                        <div class="week-box p-2 rounded {{ 'bg-'.$bg }} {{ $textClass }}">
                                            <div class="d-flex justify-content-between align-items-center small">
                                                <div>{{ $wk['label'] }}</div>
                                                <div><span class="badge bg-white text-dark">{{ $wk['total'] }}</span></div>
                                            </div>
                                            <div class="mt-2 d-flex justify-content-end">
                                                <button class="btn btn-sm btn-light view-week-products" data-weekstart="{{ $wk['date'] }}" type="button">Ver productos</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>

<!-- Modal semana -->
<div class="modal fade" id="weekModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Productos para la semana <span id="weekModalTitle"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="weekModalContent" class="p-1 text-center text-muted">Cargando...</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal usuarios -->
<div class="modal fade" id="usersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Usuarios programados</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="usersModalContent" class="p-1 text-center text-muted">Cargando...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    var weekModalEl = document.getElementById('weekModal');
    var weekModal = new bootstrap.Modal(weekModalEl);
    var usersModalEl = document.getElementById('usersModal');
    var usersModal = new bootstrap.Modal(usersModalEl);

    document.querySelectorAll('.view-week-products').forEach(function(btn){
        btn.addEventListener('click', async function(){
            var weekStart = this.dataset.weekstart;
            document.getElementById('weekModalTitle').textContent = weekStart;
            var content = document.getElementById('weekModalContent');
            content.innerHTML = '<div class="py-3"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
            weekModal.show();
            try {
                var res = await fetch('/elemento-periodicidad/productos-por-semana?weekStart=' + encodeURIComponent(weekStart), { headers: { 'Accept': 'application/json' } });
                var data = await res.json();
                if (data && data.success) {
                    if (!data.products || data.products.length === 0) {
                        content.innerHTML = '<div class="text-muted">No hay productos para esta semana.</div>';
                        return;
                    }
                    var html = '<ul class="list-group">';
                    data.products.forEach(function(p){
                        html += '<li class="list-group-item d-flex justify-content-between align-items-center">';
                        html += '<div><div class="fw-semibold">' + (p.name || p.sku) + '</div><div class="small text-muted">' + p.sku + '</div></div>';
                        html += '<div><button class="btn btn-sm btn-primary view-prod-users" data-sku="' + encodeURIComponent(p.sku) + '" data-weekstart="' + weekStart + '">Ver usuarios (' + p.count + ')</button></div>';
                        html += '</li>';
                    });
                    html += '</ul>';
                    content.innerHTML = html;

                    // listeners para ver usuarios por producto
                    content.querySelectorAll('.view-prod-users').forEach(function(b){
                        b.addEventListener('click', async function(){
                            var sku = decodeURIComponent(this.dataset.sku);
                            var ws = this.dataset.weekstart;
                            var usersContent = document.getElementById('usersModalContent');
                            usersContent.innerHTML = '<div class="py-3"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
                            usersModal.show();
                            try {
                                var r2 = await fetch('/elemento-periodicidad/usuarios/' + encodeURIComponent(sku) + '?weekStart=' + encodeURIComponent(ws), { headers: { 'Accept': 'application/json' } });
                                var d2 = await r2.json();
                                if (d2 && d2.success) {
                                    if (!d2.users || d2.users.length === 0) {
                                        usersContent.innerHTML = '<div class="text-muted">No hay usuarios para este producto en la semana.</div>';
                                        return;
                                    }
                                    var uhtml = '<ul class="list-group">';
                                    d2.users.forEach(function(u){
                                        uhtml += '<li class="list-group-item">';
                                        uhtml += '<div class="fw-semibold">' + (u.nombres || '') + ' ' + (u.apellidos || '') + '</div>';
                                        uhtml += '<div class="small text-muted">' + (u.email || '') + ' - ' + (u.numero_documento || '') + '</div>';
                                        uhtml += '</li>';
                                    });
                                    uhtml += '</ul>';
                                    usersContent.innerHTML = uhtml;
                                } else {
                                    usersContent.innerHTML = '<div class="text-danger">Error al cargar usuarios.</div>';
                                }
                            } catch (err) {
                                usersContent.innerHTML = '<div class="text-danger">Error de red.</div>';
                            }
                        });
                    });

                } else {
                    content.innerHTML = '<div class="text-danger">Error al cargar productos.</div>';
                }
            } catch (err) {
                content.innerHTML = '<div class="text-danger">Error de red.</div>';
            }
        });
    });
});
</script>
@endpush

@endsection
