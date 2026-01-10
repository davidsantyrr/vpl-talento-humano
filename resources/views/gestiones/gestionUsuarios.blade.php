@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionUsuario.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
@endpush

@section('content')
<x-NavEntregasComponente/>

<div class="gestion-card">

    {{-- HEADER --}}
    <div class="gestion-header">
        <div>
            <h1>Gestión de Usuarios</h1>
            <p>Administra los usuarios registrados en el sistema</p>
        </div>

        <button class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalUsuario">
            + Agregar nuevo usuario
        </button>
    </div>

    {{-- ALERTA --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA --}}
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Tipo documento</th>
                    <th>N° documento</th>
                    <th>Email</th>
                    <th>Fecha ingreso</th>
                    <th>Operación</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->id }}</td>
                    <td>{{ $usuario->nombres }}</td>
                    <td>{{ $usuario->apellidos }}</td>
                    <td>{{ $usuario->tipo_documento }}</td>
                    <td>{{ $usuario->numero_documento }}</td>
                    <td>{{ $usuario->email }}</td>
                    <td>{{ $usuario->fecha_ingreso }}</td>
                    <td>{{ optional($usuario->operacion)->operationName ?? 'N/A' }}</td>
                    <td>{{ optional($usuario->area)->nombre_area ?? 'N/A' }}</td>
                    <td>
                        <div class="acciones d-flex align-items-center gap-2">
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm btn-talla"
                                    title="Asignar producto"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalSeleccionProducto"
                                    data-user-id="{{ $usuario->id }}"
                                    data-user-name="{{ $usuario->nombres }} {{ $usuario->apellidos }}">
                                <i class="bi bi-bag-plus"></i>
                            </button>
                            <a href="{{ route('gestionUsuario.edit', $usuario->id) }}"
                                class="btn btn-outline-warning btn-sm" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form action="{{ route('gestionUsuario.destroy', $usuario->id) }}"
                                method="POST">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">No hay usuarios registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODAL CREAR USUARIO --}}
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Agregar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            
            <form action="{{ route('gestionUsuario.store') }}" method="POST">
                @csrf

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label name="nombres" class="form-label">Nombre</label>
                            <input type="text" name="nombres" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo Documento</label>
                            <select name="tipo_documento" class="form-select">
                                <option value="">Seleccione</option>
                                <option value="Cédula de Ciudadanía">Cédula de Ciudadanía</option>
                                <option value="Cédula de Extranjería">Cédula de Extranjería</option>
                                <option value="Pasaporte">Pasaporte</option>    
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Número Documento</label>
                            <input type="text" name="numero_documento" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha Ingreso</label>
                            <input type="date" name="fecha_ingreso" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Operación</label>
                            <select name="operacion_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($operations as $op)
                                    <option value="{{ $op->id }}">{{ $op->operationName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Área</label>
                            <select name="area_id" class="form-select">
                                <option value="">Seleccione</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->nombre_area }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        Guardar Usuario
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

{{-- MODAL SELECCIÓN DE PRODUCTO --}}
<div class="modal fade" id="modalSeleccionProducto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar producto a <span id="productoUsuarioNombre">Usuario</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="productoUsuarioId" value="">

                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Producto</label>
                        <select id="selectProducto" class="form-select">
                            <option value="">Seleccione producto</option>
                            @foreach($productos as $p)
                                <option value="{{ $p->sku }}" data-nombre="{{ $p->name_produc }}">{{ $p->name_produc }} ({{ $p->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="btnAddProducto" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Añadir
                        </button>
                    </div>
                </div>

                <hr>

                <table class="table table-bordered table-sm w-100" id="tablaProductosAsignados">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
    </div>


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/alertas.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalSeleccionProducto');
    const tablaBody = document.querySelector('#tablaProductosAsignados tbody');
    const selectProducto = document.getElementById('selectProducto');
    const btnAdd = document.getElementById('btnAddProducto');
    const usuarioNombreSpan = document.getElementById('productoUsuarioNombre');
    const usuarioIdInput = document.getElementById('productoUsuarioId');
    const csrfToken = '{{ csrf_token() }}';

    // Al abrir modal, setear usuario
    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        usuarioIdInput.value = userId;
        usuarioNombreSpan.textContent = userName || 'Usuario';
                // limpiar selección y tabla
                selectProducto.value = '';
                tablaBody.innerHTML = '';

                // Precargar productos ya asignados
                fetch(`/usuarios/${userId}/productos-asignados`, {
                        headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(json => {
                        if (!json.ok) return;
                        (json.data || []).forEach(item => {
                                const tr = document.createElement('tr');
                                tr.setAttribute('data-assignment-id', item.id);
                                tr.innerHTML = `
                                    <td>${item.name_produc}</td>
                                    <td>${item.sku}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Quitar">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </td>
                                `;
                                tablaBody.appendChild(tr);
                        });
                })
                .catch(() => {});
    });

    // Añadir fila a la tabla
    btnAdd.addEventListener('click', async function() {
        const sku = selectProducto.value;
        const nombre = selectProducto.options[selectProducto.selectedIndex]?.getAttribute('data-nombre');
        const userId = usuarioIdInput.value;

        if (!sku) {
            Swal.fire({ icon: 'warning', title: 'Selecciona un producto' });
            return;
        }

        try {
            const resp = await fetch(`/usuarios/${userId}/producto-asignado`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ sku })
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.message || 'Error al asignar');
            }

                        const tr = document.createElement('tr');
                        tr.setAttribute('data-assignment-id', data.data.id);
                        tr.innerHTML = `
                            <td>${nombre}</td>
                            <td>${sku}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Quitar">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        `;
            tablaBody.appendChild(tr);

            Swal.fire({ icon: 'success', title: data.message || 'Producto asignado' });
        } catch (err) {
            Swal.fire({ icon: 'error', title: err.message });
        }
    });

    // Quitar fila con DELETE en servidor
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-remove');
        if (!btn) return;
        const tr = btn.closest('tr');
        const assignmentId = tr?.getAttribute('data-assignment-id');
        if (!assignmentId) { tr.remove(); return; }
        try {
            const resp = await fetch(`/usuarios/producto-asignado/${assignmentId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) throw new Error('No se pudo eliminar');
            tr.remove();
        } catch (err) {
            Swal.fire({ icon: 'error', title: err.message });
        }
    });
});
</script>
@endpush

@endsection

