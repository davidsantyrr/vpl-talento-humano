@extends('layouts.app')
@section('title', 'Formulario de Entregas')

@section('content')

<div class="container mt-4">
    <h2 class="mb-4">Formulario de Entregas</h2>
    <form action="{{ route('entregas.store') }}" method="POST">
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
                <option value=""></option>
                <option value=""></option>
        </div>
        <div>
            <label for="documento" class="form-label">tipo de documento</label>
            <input type="text" class="form-control" id="documento" name="documento" required>
        </div>
        <div>
            <label for="descripcion" class="form-label">numero de documento</label>
            <input type="number" class="form-control" id="numberDocumento" name="numberDocumento" required>
        </div>
        <button type="submit" class="btn btn-primary">añadir elemento</button>
        <div class ="elementos-entregados">
            <h1>Elementos a entregar</h1>
            
        </div>
        <table>
        <tr>
            <td>Elemento</td>
            <td>cantidad</td>
        </tr>
    </form>
    <div class="firma-container">
    <canvas id="firmaCanvas" width="400" height="200"></canvas>
    <div class="acciones">
        <button type="button" onclick="limpiarFirma()">Limpiar</button>
    </div>

    <!-- Aquí se guardará la firma en Base64 -->
    <input type="hidden" name="firma" id="firmaInput">
    </div>
</div>
@endsection