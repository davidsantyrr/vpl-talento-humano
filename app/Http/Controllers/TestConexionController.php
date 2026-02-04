<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class TestConexionController extends Controller
{
    public function testSegundaBase()
    {
        try {
            $datos = DB::connection('mysql_secundaria')
                ->table('usuarios')
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
