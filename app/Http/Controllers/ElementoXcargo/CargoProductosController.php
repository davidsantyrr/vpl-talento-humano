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
        $perPage = (int) $request->get('per_page', 20);
        $perPage = in_array($perPage, [5,10,20,50]) ? $perPage : 20;

        $cargos = Cargo::orderBy('nombre')->get();
        $productos = Producto::select('sku','name_produc','categoria_produc')->orderBy('name_produc')->paginate($perPage)->appends($request->only('cargo_id','per_page'));
        $asignados = $cargoId ? CargoProducto::where('cargo_id',$cargoId)->pluck('sku')->all() : [];

        // Render rows similares a artículos
        $rowsHtml = '';
        foreach ($productos as $p) {
            $checked = in_array($p->sku, $asignados) ? 'checked' : '';
            $rowsHtml .= '<tr>'
                .'<td>'.e($p->sku).'</td>'
                .'<td>'.e($p->name_produc).'</td>'
                .'<td>'.e($p->categoria_produc).'</td>'
                .'<td><input type="checkbox" name="skus[]" value="'.e($p->sku).'" data-name="'.e($p->name_produc).'" '.$checked.'></td>'
                .'</tr>';
        }

        // Paginación simple
        $paginationHtml = '';
        if ($productos->hasPages()) {
            $paginationHtml .= '<nav aria-label="Paginación"><ul class="pagination">';
            if ($productos->onFirstPage()) {
                $paginationHtml .= '<li class="disabled"><span>&lsaquo;</span></li>';
            } else {
                $paginationHtml .= '<li><a href="'.$productos->appends(['cargo_id'=>$cargoId,'per_page'=>$perPage])->previousPageUrl().'" rel="prev">&lsaquo;</a></li>';
            }
            $start = max(1, $productos->currentPage()-2);
            $end = min($productos->lastPage(), $productos->currentPage()+2);
            for ($page = $start; $page <= $end; $page++) {
                if ($page == $productos->currentPage()) {
                    $paginationHtml .= '<li class="active"><span>'.$page.'</span></li>';
                } else {
                    $paginationHtml .= '<li><a href="'.$productos->appends(['cargo_id'=>$cargoId,'per_page'=>$perPage])->url($page).'">'.$page.'</a></li>';
                }
            }
            if ($productos->hasMorePages()) {
                $paginationHtml .= '<li><a href="'.$productos->appends(['cargo_id'=>$cargoId,'per_page'=>$perPage])->nextPageUrl().'" rel="next">&rsaquo;</a></li>';
            } else {
                $paginationHtml .= '<li class="disabled"><span>&rsaquo;</span></li>';
            }
            $paginationHtml .= '</ul></nav>';
        }

        return view('elementoxcargo.productos', [
            'cargos' => $cargos,
            'cargoId' => $cargoId,
            'rowsHtml' => $rowsHtml,
            'paginationHtml' => $paginationHtml,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request)
    {
        $cargoId = (int) $request->get('cargo_id');
        $skus = (array) $request->get('skus', []);
        $namesBySku = [];
        foreach ($skus as $sku) {
            $namesBySku[$sku] = $request->input('names.'.$sku) ?? null;
        }

        // Limpiar asignaciones y re-crear
        CargoProducto::where('cargo_id', $cargoId)->delete();
        foreach ($skus as $sku) {
            $name = $namesBySku[$sku] ?? Producto::where('sku',$sku)->value('name_produc');
            CargoProducto::create([
                'cargo_id' => $cargoId,
                'sku' => $sku,
                'name_produc' => $name,
            ]);
        }

        return back()->with('status','Productos asignados al cargo');
    }
}