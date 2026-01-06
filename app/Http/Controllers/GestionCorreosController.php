<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GestionCorreosController extends Controller
{
    public function index()
    {
        // ...existing code...
        
        $rolesDisponibles = []; // Agregar esta línea para definir la variable
        
        return view('gestiones.gestionCorreos', compact('rolesDisponibles'));
    }
}