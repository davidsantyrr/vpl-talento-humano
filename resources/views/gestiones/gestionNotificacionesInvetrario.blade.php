@extends('layouts.app')
@section('title', 'Gestión de Notificaciones de Inventario')
@push('styles')
<link rel="stylesheet" href="{{ secure_asset('css/gestiones/gestionNotificacionesInventario.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
<div class="container">

    <h2 class="text-center mb-4">Gestión de Notificaciones de Inventario</h2>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <button id="btnAdd" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createModal">Agregar nueva notificación</button>
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Elemento</th>
                    <th>Stock</th>
                    <th>Fecha de Creación</th>
                    <th>Fecha de Actualización</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notificaciones as $notificacion)
                <tr data-id="{{ $notificacion->id }}" data-elemento="{{ $notificacion->elemento }}" data-stock="{{ $notificacion->stock }}">
                    <td>{{ $notificacion->id }}</td>
                    <td>{{ $notificacion->elemento }}</td>
                    <td>{{ $notificacion->stock }}</td>
                    <td>{{ $notificacion->created_at }}</td>
                    <td>{{ $notificacion->updated_at }}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary btn-edit" data-bs-toggle="modal" data-bs-target="#editModal">Editar</button>
                        <form action="{{ route('gestionNotificacionesInventario.destroy', $notificacion->id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Eliminar notificación?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="createForm" method="POST" action="{{ route('gestionNotificacionesInventario.store') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Nueva Notificación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Elemento</label>
            <select id="createElemento" name="elemento" required class="form-select">
              @if(empty($elementos))
                <option value="">-- No hay elementos asignados para su rol --</option>
              @else
                <option value="">Seleccione el elemento</option>
                @foreach($elementos as $el)
                  @if(is_array($el) || is_object($el))
                    <option value="{{ is_array($el) ? $el['sku'] : $el->sku }}">{{ is_array($el) ? $el['name'] : $el->name }}</option>
                  @else
                    <option value="{{ $el }}">{{ $el }}</option>
                  @endif
                @endforeach
              @endif
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Stock</label>
            <input id="createStock" name="stock" required class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Editar Notificación</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Elemento</label>
            <select id="editElemento" name="elemento" required class="form-select">
              @if(empty($elementos))
                <option value="">-- No hay elementos asignados para su rol --</option>
              @else
                <option value="">Seleccione el elemento</option>
                @foreach($elementos as $el)
                  @if(is_array($el) || is_object($el))
                    <option value="{{ is_array($el) ? $el['sku'] : $el->sku }}">{{ is_array($el) ? $el['name'] : $el->name }}</option>
                  @else
                    <option value="{{ $el }}">{{ $el }}</option>
                  @endif
                @endforeach
              @endif
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Stock</label>
            <input id="editStock" name="stock" required class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Edit button -> populate edit modal
    document.querySelectorAll('.btn-edit').forEach(function(btn){
        btn.addEventListener('click', function(e){
            var tr = e.target.closest('tr');
            var id = tr.getAttribute('data-id');
            var elemento = tr.getAttribute('data-elemento');
            var stock = tr.getAttribute('data-stock');

            var editSelect = document.getElementById('editElemento');
            if (editSelect) {
              var options = Array.from(editSelect.options);
              var match = options.find(function(o){ return o.value === elemento || o.text === elemento; });
              if (match) {
                editSelect.value = match.value;
              } else {
                var opt = document.createElement('option');
                opt.value = elemento;
                opt.text = elemento + ' (actual)';
                editSelect.appendChild(opt);
                editSelect.value = elemento;
              }
            }
            document.getElementById('editStock').value = stock;
            document.getElementById('editForm').action = '/gestionNotificacionesInventario/' + id;
        });
    });

    // Clear create modal fields when it is shown (avoid calling show() twice)
    var createModalEl = document.getElementById('createModal');
    if (createModalEl) {
      createModalEl.addEventListener('show.bs.modal', function (){
        var el = document.getElementById('createElemento');
        var st = document.getElementById('createStock');
        if (el) el.value = '';
        if (st) st.value = '';
      });
    }
});
</script>
@endpush

@endsection