<?php

namespace App\Http\Controllers\ElementoXcargo;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\CargoProducto;
use App\Models\Producto;
use App\Models\Operation;
use Illuminate\Http\Request;

class CargoProductosController extends Controller
{
    public function index(Request $request)
    {
        $cargoId = (int) $request->get('cargo_id');
        $operationId = (int) $request->get('operation_id');
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 10;
        $q = trim((string) $request->get('q', ''));

        $cargos = Cargo::orderBy('nombre')->get();
        $operations = Operation::orderBy('operationName')->get();

        $allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();

        $asignaciones = CargoProducto::with(['cargo','operation'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page')
            ->appends(['per_page' => $perPage]);

        return view('elementoxcargo.productos', compact('cargos', 'operations', 'cargoId', 'operationId', 'asignaciones', 'perPage', 'allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
            'operation_id' => ['required', 'integer', 'exists:operation,id'],
            'sku' => ['required', 'string'],
        ]);

        $name = Producto::where('sku', $data['sku'])->value('name_produc');
        if (!$name) {
            return back()->with('errorMessage', 'Producto no encontrado');
        }

        CargoProducto::updateOrCreate(
            ['cargo_id' => (int) $data['cargo_id'], 'operation_id' => (int) $data['operation_id'], 'sku' => $data['sku']],
            ['name_produc' => $name]
        );

        return back()->with('status', 'Producto asignado al cargo y operación');
    }

    public function destroy(CargoProducto $cargoProducto)
    {
        $cargoProducto->delete();
        return back()->with('status', 'Asignación eliminada');
    }
}
