<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TestConexionController extends Controller
{
    public function testSegundaBase()
    {
        try {
            // Consulta simple a una tabla real de la segunda BD
            $datos = DB::connection('mysql_secundaria')
                ->table('usuarios') // CAMBIA el nombre de la tabla si es necesario
                ->limit(1)
                ->get();

            return response()->json([
                'conexion' => 'OK',
                'datos' => $datos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'conexion' => 'ERROR',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}
