<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Exception;

class ControllerPdf extends Controller
{
    public function generarPDF(Request $request)
    {
        $request->validate([
            'firma' => 'nullable|string',
            'nombre' => 'required|string',
            'numberDocumento' => 'required|string',
        ]);

        try {
            if (!Storage::exists('public/entregas')) {
                Storage::makeDirectory('public/entregas');
            }

            $pdf = Pdf::loadView('pdf', [
                'firmaBase64' => $request->firma,
                'nombre' => $request->nombre,
                'documento' => $request->numberDocumento,
            ]);

            $nombrePdf = 'entrega_' . time() . '_' . uniqid() . '.pdf';
            $rutaRelativa = 'public/entregas/' . $nombrePdf;
            Storage::put($rutaRelativa, $pdf->output());
            $fullPath = storage_path('app/' . $rutaRelativa);

            if ($request->wantsJson() || $request->ajax()) {
                $urlPublica = asset('storage/entregas/' . $nombrePdf);
                return response()->json(['success' => true, 'file' => $urlPublica, 'name' => $nombrePdf], 200);
            }

            return response()->download($fullPath, $nombrePdf)->deleteFileAfterSend(false);
        } catch (Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'Error al generar PDF'], 500);
        }
    }
}
