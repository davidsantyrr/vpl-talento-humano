@extends('layouts.app')
@section('title', 'Consulta de Elementos por Usuario')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/consultaElementoUsuario/consultaElementoUsuario.css') }}">
@endpush

@section('content')
<x-NavEntregasComponente/>
    <div class="container-fluid">
        <h1 class="mt-4">Consulta de Elementos por Usuario</h1>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('consultaElementoUsuario.consulta') }}">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input type="text" name="usuario" id="usuario" class="form-control" value="{{ request('usuario') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="elemento" class="form-label">Elemento</label>
                            <input type="text" name="elemento" id="elemento" class="form-control" value="{{ request('elemento') }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Buscar</button>
                            <a href="{{ route('consultaElementoUsuario.consulta') }}" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Elemento</th>
                                <th>ultima entrega
                                <th>ultima recepcion</th>
                                <th>cantidad</th>
                                <th>proxima entrega</th>
                                <th>acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resultados as $resultado)
                                <tr>
                                    <td>{{ $resultado->usuario }}</td>
                                    <td>{{ $resultado->elemento }}</td>
                                    <td>{{ $resultado->ultima_entrega }}</td>
                                    <td>{{ $resultado->ultima_recepcion }}</td>
                                    <td>{{ $resultado->cantidad }}</td>
                                    <td>{{ $resultado->proxima_entrega }}</td>

                                    <td>
                                        <button class="btn btn-sm btn-info">descargar pdf</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Elemento</th>
                                <th>cantidad</th>

                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resultados as $resultado)
                                <tr>
                                    
                                    <td>{{ $resultado->elemento }}</td>
                                    <td>{{ $resultado->cantidad }}</td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center">No se encontraron resultados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>