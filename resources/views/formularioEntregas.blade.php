@extends('layouts.app')
@section('title', 'Formulario de Entregas')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/formularioEntregas.css') }}">
@endpush
@section('content')
<x-sidebarComponente/>

<div class="page-center">
    <div class="container">
        <h2 class="mb-4">Formulario de Entregas</h2>
        <form action="#" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nombres</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="mb-3">
                <label for="apellidos" class="form-label">Apellidos</label>
                <input type="text" class="form-control" id="apellidos" name="apellidos" required>
            </div>
            <div class="mb-3">
                <label for="fecha_entrega" class="form-label">tipo</label>
                <select>
                    <option value="">primera vez</option>
                    <option value="">periodica</option>
                    <option value="">prestamo</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="destinatario" class="form-label">operacion</label>
                <select>
                    <option value="">......</option>
                    <option value="">.....</option>
                </select>
            </div>
            <div>
                <label  class="form-label">tipo de documento</label>
                <select>
                    <option value="">C.C</option>
                    <option value="">T.I</option>
                    <option value="">EXTRANJERA</option>
                </select>
            </div>
            <div>
                <label for="descripcion" class="form-label">numero de documento</label>
                <input type="number" class="form-control" id="numberDocumento" name="numberDocumento" required>
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
        <!-- añadido id -->
        <select id="elementoSelect">
            <option value="item1">Item 1</option>
            <option value="item2">Item 2</option>
            <option value="item3">Item 3</option>
        </select>
        <label for="cantidad">Cantidad</label>
        <!-- añadido id -->
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
        <!-- botón ahora invoca función JS y es type="button" -->
        <button type="button" class="btn btn-primary" onclick="agregarElementoModal()">añadir elemento</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cerrar</button>
    </div>
</div>

<script src="{{ asset('js/firma.js') }}"></script>
@endsection