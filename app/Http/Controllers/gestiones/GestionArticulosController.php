<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GestionArticulos;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class GestionArticulosController extends Controller
{
    public function index()
    {
        $articulos = GestionArticulos::orderBy('id', 'desc')->paginate(10);
        // Categorías disponibles: combinar externas y locales (si externas no están disponibles, usar solo locales)
        try {
            $catExt = Producto::select('categoria_produc')
                ->whereNotNull('categoria_produc')
                ->distinct()
                ->orderBy('categoria_produc')
                ->pluck('categoria_produc')
                ->toArray();
        } catch (\Throwable $e) {
            $catExt = [];
        }
        $catLocal = GestionArticulos::select('categoria')
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->toArray();
        $categorias = collect(array_unique(array_merge($catExt, $catLocal)))->sort()->values();
        return view('gestiones.gestionArticulos', compact('articulos', 'categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:100',
            'nombre_articulo' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:255',
        ]);

        $nuevo = GestionArticulos::create([
            'sku' => trim(strip_tags($validated['sku'])),
            'nombre_articulo' => trim(strip_tags($validated['nombre_articulo'])),
            'categoria' => isset($validated['categoria']) ? trim(strip_tags($validated['categoria'])) : null,
        ]);

        // No sincronizar con base externa (Railway) ni inventario externo; solo guardar localmente

        return redirect()->route('gestionArticulos.index')->with('success', 'Artículo creado exitosamente.');
    }

    public function edit($id)
    {
        $articulo = GestionArticulos::findOrFail($id);
        // Puedes crear una vista específica para edición; por ahora reutilizamos el index
        $articulos = GestionArticulos::orderBy('id', 'desc')->paginate(10);
        return view('gestiones.gestionArticulos', compact('articulos'))->with('editItem', $articulo);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:100',
            'nombre_articulo' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:255',
        ]);

        $articulo = GestionArticulos::findOrFail($id);
        $oldSku = $articulo->sku;
        $articulo->update([
            'sku' => trim(strip_tags($validated['sku'])),
            'nombre_articulo' => trim(strip_tags($validated['nombre_articulo'])),
            'categoria' => isset($validated['categoria']) ? trim(strip_tags($validated['categoria'])) : null,
        ]);
        // No sincronizar cambios a catálogos/inventarios externos

        return redirect()->route('gestionArticulos.index')->with('success', 'Artículo actualizado correctamente.');
    }

    public function destroy($id)
    {
        $articulo = GestionArticulos::findOrFail($id);
        $articulo->delete();
        return redirect()->route('gestionArticulos.index')->with('success', 'Artículo eliminado correctamente.');
    }
}
