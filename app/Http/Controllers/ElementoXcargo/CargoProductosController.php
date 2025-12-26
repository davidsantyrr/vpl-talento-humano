<?php

namespace App\Http\Controllers\ElementoXcargo;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\CargoProducto;
use App\Models\Producto;
use Illuminate\Http\Request;

class CargoProductosController extends Controller
{
    public function index(Request $request)
    {
        $cargoId = (int) $request->get('cargo_id');
        $perPage = (int) $request->get('per_page', 5);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 5;
        $q = trim((string) $request->get('q', ''));

        $cargos = Cargo::orderBy('nombre')->get();

        $allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();

        // Paginar asignaciones según per_page (tabla principal)
        $asignaciones = CargoProducto::with('cargo')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page')
            ->appends(['per_page' => $perPage]);

        return view('elementoxcargo.productos', compact('cargos', 'cargoId', 'asignaciones', 'perPage', 'allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
            'sku' => ['required', 'string'],
        ]);

        // Buscar nombre del producto por sku
        $name = Producto::where('sku', $data['sku'])->value('name_produc');
        if (!$name) {
            return back()->with('errorMessage', 'Producto no encontrado');
        }

        CargoProducto::updateOrCreate(
            ['cargo_id' => (int) $data['cargo_id'], 'sku' => $data['sku']],
            ['name_produc' => $name]
        );

        return back()->with('status', 'Producto añadido al cargo');
    }

    public function destroy(CargoProducto $cargoProducto)
    {
        $cargoProducto->delete();
        return back()->with('status', 'Asignación eliminada');
    }
}
