<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PDF\ComprobanteController as PDFComprobante;

class ComprobanteController extends Controller
{
    public function generar(Request $request)
    {
        // Delegar al controlador que ya implementa la lógica de generación
        return app(PDFComprobante::class)->generar($request);
    }
}
