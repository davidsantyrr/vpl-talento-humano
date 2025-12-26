<?php

namespace App\Http\Controllers\articulos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Articulos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ArticulosController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 20;

        $productos = Producto::select('sku', 'name_produc', 'categoria_produc')
            ->orderBy('name_produc')
            ->paginate($perPage)
            ->appends(['per_page' => $perPage]);

        $stocks = Schema::hasTable('articulos')
            ? Articulos::whereIn('sku', $productos->pluck('sku'))->get()->keyBy('sku')
            : collect();

        // Build table rows HTML
        $rowsHtml = '';
        foreach ($productos as $p) {
            $cantidad = optional($stocks->get($p->sku))->cantidad ?? 0;
            $updated = optional($stocks->get($p->sku))->updated_at?->diffForHumans() ?? 'Sin registro';
            $rowsHtml .= '<tr>'
                . '<td>' . e($p->sku) . '</td>'
                . '<td>' . e($p->name_produc) . '</td>'
                . '<td>' . e($p->categoria_produc) . '</td>'
                . '<td>'
                . '<form action="' . route('articulos.update', $p->sku) . '" method="POST" class="stock-form">'
                . csrf_field()
                . '<input name="cantidad" type="number" min="0" value="' . e($cantidad) . '" class="input-stock" />'
                . '<input type="hidden" name="per_page" value="' . e($perPage) . '">'
                . '<button type="submit" class="btn-guardar">Guardar</button>'
                . '</form>'
                . '</td>'
                . '<td><span class="badge-fecha">' . e($updated) . '</span></td>'
                . '</tr>';
        }

        // Build pagination HTML (compact window)
        $paginationHtml = '';
        if ($productos->hasPages()) {
            $paginationHtml .= '<nav aria-label="Paginación"><ul class="pagination">';
            // Prev
            if ($productos->onFirstPage()) {
                $paginationHtml .= '<li class="disabled"><span>&lsaquo;</span></li>';
            } else {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->previousPageUrl() . '" rel="prev">&lsaquo;</a></li>';
            }
            // Window size by perPage
            $window = $perPage <= 10 ? 3 : ($perPage <= 20 ? 5 : 7);
            $start = max(1, $productos->currentPage() - intdiv($window, 2));
            $end = min($productos->lastPage(), $start + $window - 1);
            for ($page = $start; $page <= $end; $page++) {
                if ($page == $productos->currentPage()) {
                    $paginationHtml .= '<li class="active"><span>' . $page . '</span></li>';
                } else {
                    $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->url($page) . '">' . $page . '</a></li>';
                }
            }
            // Next
            if ($productos->hasMorePages()) {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->nextPageUrl() . '" rel="next">&rsaquo;</a></li>';
            } else {
                $paginationHtml .= '<li class="disabled"><span>&rsaquo;</span></li>';
            }
            $paginationHtml .= '</ul></nav>';
        }

        return view('articulos.articulos', [
            'rowsHtml' => $rowsHtml,
            'paginationHtml' => $paginationHtml,
            'perPage' => $perPage,
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, string $sku)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:0']
        ]);

        $articulo = Articulos::firstOrNew(['sku' => $sku]);
        $articulo->cantidad = $data['cantidad'];
        $articulo->save();

        return redirect()->route('articulos.index', ['per_page' => (int) $request->get('per_page', 20)])
            ->with('status', 'Stock actualizado');
    }
}
