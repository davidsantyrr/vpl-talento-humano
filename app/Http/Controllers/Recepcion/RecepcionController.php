<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Operation;
use App\Models\Producto;

class RecepcionController extends Controller
{
    public function create()
    {
        $operations = Operation::orderBy('operationName')->get();
        $allProducts = Producto::select('sku','name_produc')->orderBy('name_produc')->get();
        return view('recepcion.recepcion', compact('operations','allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo_doc' => ['required','string','max:50'],
            'num_doc' => ['required','string','max:50'],
            'nombres' => ['required','string','max:120'],
            'apellidos' => ['required','string','max:120'],
            'operation_id' => ['required','integer','exists:operation,id'],
            'items' => ['required','string'], // JSON de elementos
            'firma' => ['nullable','string'], // base64 de firma
        ]);
        // TODO: Persistir recepción (modelo/tabla). Por ahora, solo confirma.
        return redirect()->back()->with('status', 'Recepción registrada');
    }
}
