<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Operation;


class EntregaController extends Controller
{
    /** Mostrar el formulario de entregas */
    public function create()
    {
        $operations = Operation::orderBy('operationName')->get();

        return view('formularioEntregas.formularioEntregas', compact('operations'));
    }

    /** Procesar el envío del formulario, generar PDF y devolver descarga */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'numberDocumento' => 'required|string',
            'elementos' => 'nullable|string',
            'firma' => 'nullable|string',
            'tipo' => 'nullable|string',
            'operacion' => 'nullable|string',
            'tipo_documento' => 'nullable|string',
        ]);

        try {
            if (!Storage::exists('public/entregas')) {
                Storage::makeDirectory('public/entregas');
            }

            $firmaBase64 = $data['firma'] ?? '';
            $pdf = Pdf::loadView('pdf', [
                'firmaBase64' => $firmaBase64,
                'nombre' => trim(($data['nombre'] ?? '') . ' ' . ($data['apellidos'] ?? '')),
                'documento' => $data['numberDocumento'] ?? '',
                'elementos' => json_decode($data['elementos'] ?? '[]', true),
            ]);

            $nombrePdf = 'entrega_' . time() . '_' . uniqid() . '.pdf';
            $rutaRelativa = 'public/entregas/' . $nombrePdf;
            Storage::put($rutaRelativa, $pdf->output());
            $fullPath = storage_path('app/' . $rutaRelativa);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'file' => asset('storage/entregas/' . $nombrePdf), 'name' => $nombrePdf], 200);
            }

            return response()->download($fullPath, $nombrePdf)->deleteFileAfterSend(false);
        } catch (Exception $e) {
            Log::error('Error creando entrega PDF: ' . $e->getMessage(), ['request' => $request->all()]);
            return redirect()->back()->with('error', 'Ocurrió un error al generar el PDF.');
        }
    }
}