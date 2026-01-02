@extends('layouts.app')
@section('title', 'Formulario de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/formularioEntregas.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>

<div class="container">
    <div class="panel">
    <h1 class="title">Formulario de Entregas</h1>
        <form action="{{ route('entregas.store') }}" method="POST" onsubmit="guardarFirma()">
            @csrf
            
            <div class="section">
                <div class="grid">
                    <div class="field">
                        <label>Tipo de documento</label>
                        <select name="tipo_documento">
                            <option value="CC">C.C</option>
                            <option value="TI">T.I</option>
                            <option value="EXTRANJERA">EXTRANJERA</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Número de documento</label>
                        <input type="text" id="numberDocumento" name="numberDocumento" required>
                        <div id="usuarioLookup" data-crear-url="{{ route('gestionUsuario.index') }}" class="small text-muted mt-1"></div>
                    </div>
                    <div class="field">
                        <label>Nombres</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="field">
                        <label>Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos">
                    </div>
                    <div class="field">
                        <label>Tipo</label>
                        <select id="tipoSelect" name="tipo" required>
                            <option value="prestamo">Préstamo</option>
                            <option value="primera vez">Primera vez</option>
                            <option value="periodica">Periódica</option>
                            <option value="cambio">Cambio</option>
                        </select>
                    </div>
                    <div class="field" id="field-operacion">
                        <label>Operación</label>
                        <select id="operacionSelect" name="operacion_id" required>
                            <option value="">Seleccione una operación</option>
                            @foreach($operations as $op)
                            <option value="{{ $op->id }}">{{ $op->operationName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field span-2" id="field-cargo" style="display:none;">
                        <label>Cargo</label>
                        @php($listCargos = isset($cargos) ? $cargos : \App\Models\Cargo::orderBy('nombre')->get())
                        <select id="cargoSelect" name="cargo_id" disabled title="Informativo">
                            <option value="">Seleccione un cargo</option>
                            @foreach($listCargos as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" id="cargoIdHidden" name="cargo_id" value="">
                        <small class="text-muted">Solo informativo, se autocompleta con el cargo del usuario.</small>
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn add" id="btnAnadirElemento" onclick="abrirModal()">Añadir elemento</button>
                    <button type="button" class="btn primary" id="btnSeleccionarRecepcion" onclick="abrirModalRecepcion()" style="display:none;">Seleccionar recepción</button>
                </div>
            </div>

            <div class="section">
                <div class="table-wrapper">
                    <table id="elementosFormTable">
                        <thead>
                            <tr>
                                <th>Elemento</th>
                                <th style="width:120px;">Cantidad</th>
                                <th style="width:120px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="elementosFormTbody"></tbody>
                    </table>
                </div>
            </div>

            <input type="hidden" name="elementos" id="elementosJson" value="[]">

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

            <input type="hidden" name="firma" id="firmaField">

            <div class="actions">
                <button type="submit" class="btn primary">Realizar entrega</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalElementos">
    <div>
        <h1>Elementos a entregar</h1>
        <div class="modal-grid">
            <div class="modal-field">
                <label>Producto</label>
                <select id="elementoSelect">
                    <option value="">Seleccione un producto</option>
                </select>
            </div>
            <div class="modal-field">
                <label>Cantidad</label>
                <input type="number" id="cantidadInput" name="cantidad" min="1" value="1" required>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn add" onclick="agregarElementoModal()">Agregar a lista</button>
        </div>
        <table class="modal-table">
            <thead>
                <tr>
                    <th>Elemento</th>
                    <th style="width:120px;">Cantidad</th>
                </tr>
            </thead>
            <tbody id="elementosTbody"></tbody>
        </table>
        <div class="modal-actions" style="margin-top:16px;">
            <button type="button" class="btn primary" onclick="guardarModal()">Añadir</button>
            <button type="button" class="btn secondary" onclick="cerrarModal()">Cancelar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.FormularioPageConfig = {
        allProducts: @json(($allProducts ?? collect())->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]))
    };
</script>
<script src="{{ asset('js/formularioEntregas.js') }}"></script>
@endsection