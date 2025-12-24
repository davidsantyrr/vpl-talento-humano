<?php
namespace App\Http\Controllers\articulos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Articulos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ArticulosController extends Controller
{
    public function index()
    {
        $productos = Producto::select('sku','name_produc','categoria_produc')->orderBy('name_produc')->paginate(20);
        $stocks = Schema::hasTable('articulos')
            ? Articulos::whereIn('sku', $productos->pluck('sku'))->get()->keyBy('sku')
            : collect();

        return view('articulos_stock.articulos', compact('productos','stocks'));
    }

    public function update(Request $request, string $sku)
    {
        $data = $request->validate([
            'cantidad' => ['required','integer','min:0']
        ]);

        $articulo = Articulos::firstOrNew(['sku' => $sku]);
        $articulo->cantidad = $data['cantidad'];
        $articulo->save();

        return redirect()->route('articulos.index')->with('status', 'Stock actualizado');
    }
}