<?php

namespace App\Http\Controllers\consultaEementosUsuario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class controllerConsulta extends Controller
{
    public function index(Request $request)
    {
        // Lógica mínima: evitar error cuando no hay datos y permitir implementar la búsqueda después.
        // Se puede reemplazar por una consulta real según la estructura de la BD.
        $usuario = $request->input('usuario');
        $elemento = $request->input('elemento');

        // Por ahora devolvemos una colección vacía para que la vista no lance "Undefined variable $resultados"
        $resultados = collect();

        return view('consultaElementoUsuario.consulta', compact('resultados'));
    }
    
}