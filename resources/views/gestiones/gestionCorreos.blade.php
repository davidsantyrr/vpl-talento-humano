@extends('layouts.app')

@section('title', 'Gestión de Correos')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/gestiones/gestionCorreos.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>
    <div class="container-fluid">
        <h1 class="mt-4">Gestión de Correos</h1>
        <div class="card mb-4">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                {{-- Formulario para agregar nuevo correo --}}
                <form method="POST" action="{{ route('gestionCorreos.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Rol</label>
                        @php
                            $fixedRoles = [
                                'Administrador','Autorizador Visitas','Autorizador Agendamientos','Supervisor Agendamientos',
                                'Autorizador AQL','Developer Vigia','Coordinador','Area de compras','Admin requisicion',
                                'Gerente financiero','Director contable','Gerente talento humano','Gerente operaciones',
                                'Director de proyectos','Talento_Humano','HSEQ','AdminEntregas'
                            ];
                        @endphp
                        <select name="rol" id="rol" class="form-select" required>
                            <option value="">-- Seleccione un rol --</option>
                            @foreach($fixedRoles as $rol)
                                <option value="{{ $rol }}" {{ old('rol') == $rol ? 'selected' : '' }}>{{ $rol }}</option>
                            @endforeach
                        </select>
                        @error('rol') <div class="text-danger small">{{ $message }}</div> @enderror
                        <small class="text-muted">Los roles provienen de las periodicidades configuradas</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" value="{{ old('correo') }}" required maxlength="191">
                        @error('correo') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Agregar correo</button>
                    </div>
                </form>

                @push('scripts')
                <script>
                (function(){
                    const emailInput = document.querySelector('input[name="correo"]');
                    const rolSelect = document.getElementById('rol');
                    if (!emailInput || !rolSelect) return;
                    let timeout = null;
                    function lookupEmail(email){
                        if (!email) return;
                        fetch("{{ route('gestionCorreos.lookupUser') }}?email=" + encodeURIComponent(email))
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.matched_role) {
                                    // Try to select matching option (exact match)
                                    for (let i=0;i<rolSelect.options.length;i++){
                                        if (rolSelect.options[i].value === data.matched_role){
                                            rolSelect.selectedIndex = i;
                                            return;
                                        }
                                    }
                                    // case-insensitive
                                    const want = (data.matched_role || '').toLowerCase();
                                    for (let i=0;i<rolSelect.options.length;i++){
                                        if (rolSelect.options[i].value.toLowerCase() === want){
                                            rolSelect.selectedIndex = i; return;
                                        }
                                    }
                                }
                            }).catch(err=>{ console.error('lookup error', err); });
                    }
                    emailInput.addEventListener('blur', function(){ const v = this.value.trim(); if (v) lookupEmail(v); });
                    emailInput.addEventListener('input', function(){ clearTimeout(timeout); const v = this.value.trim(); if (!v) return; timeout = setTimeout(()=> lookupEmail(v), 700); });
                })();
                </script>
                @endpush

                {{-- Tabla de correos existentes --}}
                <div class="table-responsive mt-4">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Correo</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($correos as $c)
                                <tr>
                                    <td>{{ $c->rol }}</td>
                                    <td>{{ $c->correo }}</td>
                                    <td>{{ optional($c->created_at)->toDateTimeString() }}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-correo" data-id="{{ $c->id }}" data-rol="{{ $c->rol }}" data-correo="{{ $c->correo }}">Editar</button>
                                        <form method="POST" action="{{ route('gestionCorreos.destroy', $c->id) }}" style="display:inline-block" onsubmit="return confirm('Eliminar este correo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No hay correos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Modal para editar correo (abre desde tabla) --}}
                                <!-- Bootstrap modal for editing correo -->
                                <div class="modal fade" id="modalEditCorreo" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar Correo</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form id="formEditCorreo" method="POST" action="">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    @php
                                                            $fixedRoles = [
                                                                    'Administrador','Autorizador Visitas','Autorizador Agendamientos','Supervisor Agendamientos',
                                                                    'Autorizador AQL','Developer Vigia','Coordinador','Area de compras','Admin requisicion',
                                                                    'Gerente financiero','Director contable','Gerente talento humano','Gerente operaciones',
                                                                    'Director de proyectos','Talento_Humano','HSEQ','AdminEntregas'
                                                            ];
                                                    @endphp
                                                    <div class="mb-3">
                                                        <label for="editRol" class="form-label">Rol</label>
                                                        <select name="rol" id="editRol" class="form-select" required>
                                                                <option value="">-- Seleccione un rol --</option>
                                                                @foreach($fixedRoles as $rol)
                                                                        <option value="{{ $rol }}">{{ $rol }}</option>
                                                                @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="editCorreo" class="form-label">Correo</label>
                                                        <input type="email" id="editCorreo" name="correo" class="form-control" required maxlength="191">
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

                {{-- Paginación --}}
                <div class="mt-3">
                    @if(method_exists($correos, 'links'))
                        {{ $correos->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@push('scripts')
<script>
    (function(){
        const modalEl = document.getElementById('modalEditCorreo');
        const modalInstance = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        const form = document.getElementById('formEditCorreo');
        const editRol = document.getElementById('editRol');
        const editCorreo = document.getElementById('editCorreo');

        document.querySelectorAll('.btn-edit-correo').forEach(btn => {
            btn.addEventListener('click', function(e){
                const id = this.dataset.id;
                const rol = this.dataset.rol || '';
                const correo = this.dataset.correo || '';
                if (editCorreo) editCorreo.value = correo;
                if (editRol) {
                    for (let i=0;i<editRol.options.length;i++){
                        if (editRol.options[i].value === rol){ editRol.selectedIndex = i; break; }
                    }
                }
                if (form) form.action = '/gestionCorreos/' + id;
                if (modalInstance) modalInstance.show();
            });
        });
    })();
</script>
@endpush
@endsection