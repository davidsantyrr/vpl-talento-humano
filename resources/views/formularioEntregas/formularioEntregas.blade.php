@extends('layouts.app')
@section('title', 'Formulario de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/formularioEntregas.css') }}">
@endpush
@section('content')
<x-NavEntregasComponente/>

<div class="page-center">
    <div class="container">
        <h2 class="mb-4">Formulario de Entregas</h2>
        <form action="{{ route('entregas.store') }}" method="POST" onsubmit="guardarFirma()">
            @csrf

            <div>
                <label  class="form-label">tipo de documento</label>
                <select name="tipo_documento">
                    <option value="CC">C.C</option>
                    <option value="TI">T.I</option>
                    <option value="EXTRANJERA">EXTRANJERA</option>
                </select>
            </div>
            <div>
                <label for="descripcion" class="form-label">numero de documento</label>
                <input type="text" class="form-control" id="numberDocumento" name="numberDocumento" required>
                <div id="usuarioLookup" data-crear-url="{{ route('gestionUsuario.index') }}" class="small text-muted mt-1"></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Nombres</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="mb-3">
                <label for="apellidos" class="form-label">Apellidos</label>
                <input type="text" class="form-control" id="apellidos" name="apellidos">
            </div>
            <div class="mb-3">
                <label for="fecha_entrega" class="form-label">tipo</label>
                <select id="tipoSelect" name="tipo" class="form-control" required>
                    <option value="prestamo">prestamo</option>
                    <option value="primera vez">primera vez</option>
                    <option value="periodica">periodica</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="destinatario" class="form-label">operacion</label>
                
                <select id="operacionSelect" name="operacion_id" class="form-control" required>
                    <option value="">Seleccione una operación</option>

                    @foreach($operations as $op)
                    <option value="{{ $op->id }}">
                    {{ $op->operationName }}
                    </option>
                    @endforeach
                </select>
            </div>
            
            <button type="button" class="btn btn-primary" onclick="abrirModal()">añadir elemento</button>

            <!-- Tabla donde se mostrarán los elementos seleccionados para enviar con el formulario -->
            <table id="elementosFormTable">
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Cantidad</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="elementosFormTbody">
                    <!-- filas añadidas dinámicamente -->
                </tbody>
            </table>

            <!-- input oculto con JSON de elementos para enviar al servidor -->
            <input type="hidden" name="elementos" id="elementosJson" value="[]">

            <!-- Mover la firma dentro del formulario para que también se envie -->
            <div class="firma-container">
                <canvas id="firmaCanvas" width="400" height="200"></canvas>
                <div class="acciones">
                    <button type="button" onclick="limpiarFirma()">Limpiar</button>
                </div>

                <!-- Aquí se guardará la firma en Base64 -->
                <input type="hidden" name="firma" id="firmaInput">
            </div>

            <!-- botón de envío dentro del form -->
            <button type="submit" class="btn btn-primary">Realizar entrega</button>
        </form>
    </div>
</div>

<!-- Modal: mantener fuera/encima del centro pero seguirá centrado por CSS -->
<div class="modal" id="modalElementos">
    <div>
        <h1>Elementos a entregar</h1>
        <label for="elemento">seleccione elemento</label>
        <select id="elementoSelect" class="form-control">
            <option value="">Seleccione un producto</option>
            @foreach($allProducts ?? collect() as $p)
                <option value="{{ $p->sku }}" data-name="{{ $p->name_produc }}">{{ $p->sku }} — {{ $p->name_produc }}</option>
            @endforeach
        </select>
        <label for="cantidad">Cantidad</label>
        <input type="number" class="form-control" id="cantidadInput" name="cantidad" min="1" required>

        <table>
        <thead>
        <tr>
            <td>Elemento</td>
            <td>Cantidad</td>
            <td>Acción</td>
        </tr>
        </thead>
        <tbody id="elementosTbody">
        <!-- filas añadidas dinámicamente (preview en modal) -->
        </tbody>
        </table>
        <button type="button" class="btn btn-primary" onclick="agregarElementoModal()">añadir elemento</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cerrar</button>
    </div>
</div>

<script src="{{ asset('js/firma.js') }}"></script>
<script>
    window.FormularioPageConfig = {
        allProducts: @json(($allProducts ?? collect())->map(fn($p)=>['sku'=>$p->sku,'name'=>$p->name_produc]))
    };
</script>
<script src="{{ asset('js/formularioEntregas.js') }}"></script>
@endsection