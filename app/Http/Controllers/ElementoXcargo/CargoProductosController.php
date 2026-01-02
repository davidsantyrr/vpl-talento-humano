<?php

namespace App\Http\Controllers\ElementoXcargo;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\CargoProducto;
use App\Models\Producto;
use App\Models\SubArea;
use Illuminate\Http\Request;

class CargoProductosController extends Controller
{
    public function index(Request $request)
    {
        $cargoId = (int) $request->get('cargo_id');
        $subAreaId = (int) $request->get('sub_area_id');
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 10;
        $q = trim((string) $request->get('q', ''));

        $cargos = Cargo::orderBy('nombre')->get();
        $subAreas = SubArea::orderBy('operationName')->get();

        $allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();

        $asignaciones = CargoProducto::with(['cargo','subArea'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page')
            ->appends(['per_page' => $perPage]);

        return view('elementoxcargo.productos', compact('cargos', 'subAreas', 'cargoId', 'subAreaId', 'asignaciones', 'perPage', 'allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
            'sub_area_id' => ['required', 'integer', 'exists:sub_areas,id'],
            'sku' => ['required', 'string'],
        ]);

        $name = Producto::where('sku', $data['sku'])->value('name_produc');
        if (!$name) {
            return back()->with('errorMessage', 'Producto no encontrado');
        }

        CargoProducto::updateOrCreate(
            ['cargo_id' => (int) $data['cargo_id'], 'sub_area_id' => (int) $data['sub_area_id'], 'sku' => $data['sku']],
            ['name_produc' => $name]
        );

        return back()->with('status', 'Producto asignado al cargo y subárea');
    }

    public function destroy(CargoProducto $cargoProducto)
    {
        $cargoProducto->delete();
        return back()->with('status', 'Asignación eliminada');
    }

    public function matrix()
    {
        $cargos = Cargo::orderBy('nombre')->get();
        $subAreas = SubArea::orderBy('operationName')->get();
        $asignaciones = CargoProducto::select('cargo_id','sub_area_id','sku','name_produc')->get();
        // Construir mapa [sub_area_id][cargo_id] => array de productos
        $map = [];
        foreach ($asignaciones as $a) {
            $map[$a->sub_area_id][$a->cargo_id][] = ['sku' => $a->sku, 'name' => $a->name_produc];
        }
        return view('elementoxcargo.matriz', compact('cargos','subAreas','map'));
    }
}
