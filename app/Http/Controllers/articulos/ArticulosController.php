<?php

namespace App\Http\Controllers\articulos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $skus = $productos->pluck('sku');
        // traer todas las filas de inventarios (pueden existir múltiples por SKU)
        $inventariosRows = DB::connection('mysql_third')
            ->table('inventarios as i')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'i.ubicaciones_id')
            ->whereIn('i.sku', $skus)
            ->select('i.sku','i.stock','i.estatus','u.ubicacion','u.bodega')
            ->orderBy('i.sku')
            ->get();
        // agrupar por SKU
        $inventariosBySku = $inventariosRows->groupBy('sku');

        $rowsHtml = '';
        foreach ($productos as $p) {
            $rows = $inventariosBySku->get($p->sku);
            if (!$rows || $rows->isEmpty()) {
                $rows = collect([ (object) [
                    'sku' => $p->sku,
                    'stock' => 0,
                    'estatus' => 'disponible',
                    'ubicacion' => '',
                    'bodega' => ''
                ] ]);
            }

            foreach ($rows as $inv) {
                $stock = (int) ($inv->stock ?? 0);
                $estatus = $inv->estatus ?? 'disponible';
                $ubicacionSel = $inv->ubicacion ?? '';
                $bodegaSel = $inv->bodega ?? '';

                $rowsHtml .= '<tr data-sku="' . e($p->sku) . '" data-bodega="' . e($bodegaSel) . '" data-ubicacion="' . e($ubicacionSel) . '" data-estatus="' . e($estatus) . '" data-stock="' . e($stock) . '">'
                    . '<td>' . e($p->sku) . '</td>'
                    . '<td>' . e($p->name_produc) . '</td>'
                    . '<td>' . e($p->categoria_produc) . '</td>'
                    . '<td>' . e($bodegaSel ?: '-') . '</td>'
                    . '<td>' . e($ubicacionSel ?: '-') . '</td>'
                    . '<td>' . e(ucfirst($estatus)) . '</td>'
                    . '<td>' . e($stock) . '</td>'
                    . '<td>'
                      . '<div class="actions" style="display:inline-flex; gap:8px; align-items:center;">'
                      . '<button type="button" class="btn-icon location" title="Ubicación" aria-label="Ubicación">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s-7-6.16-7-11a7 7 0 1 1 14 0c0 4.84-7 11-7 11z" stroke="currentColor" stroke-width="1.2" fill="none"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon edit" title="Editar" aria-label="Editar">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25z" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M14.62 5.63l3.75 3.75" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon delete" title="Eliminar" aria-label="Eliminar" onclick="alert(\'Eliminar aún no implementado\')">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2"/><path d="M8 6V4h8v2" stroke="currentColor" stroke-width="1.2"/><path d="M6 6l1 14h10l1-14" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>'
                      . '</div>'
                    . '</td>'
                    . '</tr>';
            }
        }

        return view('articulos.articulos', [
            'rowsHtml' => $rowsHtml,
            'paginationHtml' => $this->buildPagination($productos, $perPage),
            'perPage' => $perPage,
            'status' => session('status'),
        ]);
    }

    private function buildPagination($productos, $perPage)
    {
        $paginationHtml = '';
        if ($productos->hasPages()) {
            $paginationHtml .= '<nav aria-label="Paginación"><ul class="pagination">';
            if ($productos->onFirstPage()) {
                $paginationHtml .= '<li class="disabled"><span>&lsaquo;</span></li>';
            } else {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->previousPageUrl() . '" rel="prev">&lsaquo;</a></li>';
            }
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
            if ($productos->hasMorePages()) {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage])->nextPageUrl() . '" rel="next">&rsaquo;</a></li>';
            } else {
                $paginationHtml .= '<li class="disabled"><span>&rsaquo;</span></li>';
            }
            $paginationHtml .= '</ul></nav>';
        }
        return $paginationHtml;
    }

    public function update(Request $request, string $sku)
    {
        $data = $request->validate([
            'bodega' => ['nullable','string','max:255'],
            'ubicacion' => ['nullable','string','max:255'],
            'estatus' => ['nullable','in:disponible,perdido,prestado,destruido'],
            'stock' => ['required','integer','min:0'],
            'per_page' => ['nullable','integer'],
            'from_status' => ['nullable','in:disponible,perdido,prestado,destruido']
        ]);

        // upsert ubicaciones
        $ubicacionesId = null;
        if (!empty($data['bodega']) || !empty($data['ubicacion'])) {
            $existingU = DB::connection('mysql_third')->table('ubicaciones')
                ->where('bodega', $data['bodega'] ?? '')
                ->where('ubicacion', $data['ubicacion'] ?? '')
                ->first();
            if ($existingU) {
                $ubicacionesId = $existingU->id;
            } else {
                $ubicacionesId = DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
                    'bodega' => $data['bodega'] ?? '',
                    'ubicacion' => $data['ubicacion'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $targetStatus = $data['estatus'] ?? 'disponible';
        $qty = (int) $data['stock'];
        $fromStatus = $data['from_status'] ?? null;

        // Transferencia entre estatus (generalizado)
        if ($fromStatus && $fromStatus !== $targetStatus && $qty > 0) {
            // origen
            $origin = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $fromStatus)
                ->first();
            if ($origin) {
                $move = min($qty, (int) $origin->stock);
                // actualizar/eliminar origen
                $newOriginStock = max(0, (int) $origin->stock - $move);
                if ($newOriginStock > 0) {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->update(['stock' => $newOriginStock]);
                } else {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->delete();
                }

                // destino
                $dest = DB::connection('mysql_third')->table('inventarios')
                    ->where('sku', $sku)
                    ->where('estatus', $targetStatus)
                    ->first();

                $payloadDest = [
                    'sku' => $sku,
                    'stock' => $move,
                    'estatus' => $targetStatus,
                ];
                if ($ubicacionesId) { $payloadDest['ubicaciones_id'] = $ubicacionesId; }

                if ($dest) {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $dest->id)->update([
                        'stock' => ((int) $dest->stock) + $move,
                    ] + ($ubicacionesId ? ['ubicaciones_id' => $ubicacionesId] : []));
                } else {
                    if (!isset($payloadDest['ubicaciones_id'])) {
                        $payloadDest['ubicaciones_id'] = $ubicacionesId ?? 0;
                    }
                    DB::connection('mysql_third')->table('inventarios')->insert($payloadDest);
                }
            }
        } else {
            // Upsert simple por sku + estatus
            $inv = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $targetStatus)
                ->first();

            $payload = [
                'sku' => $sku,
                'stock' => $qty,
                'estatus' => $targetStatus,
            ];
            if ($ubicacionesId) { $payload['ubicaciones_id'] = $ubicacionesId; }

            if ($inv) {
                DB::connection('mysql_third')->table('inventarios')->where('id', $inv->id)->update($payload);
            } else {
                if (!isset($payload['ubicaciones_id'])) {
                    $payload['ubicaciones_id'] = $ubicacionesId ?? 0;
                }
                DB::connection('mysql_third')->table('inventarios')->insert($payload);
            }
        }

        return redirect()->route('articulos.index', ['per_page' => (int) ($data['per_page'] ?? 20)])
            ->with('status', 'Inventario actualizado');
    }
}
