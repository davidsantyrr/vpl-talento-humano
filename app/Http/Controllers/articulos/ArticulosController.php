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
                      . '<button type="button" class="btn-icon delete" title="Destruir" aria-label="Destruir">'
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
            'from_status' => ['nullable','in:disponible,perdido,prestado,destruido'],
            'new_location' => ['nullable','in:1']
        ]);

        // upsert ubicaciones si el usuario envía datos
        $ubicacionesId = null;
        if (!empty($data['bodega']) || !empty($data['ubicacion'])) {
            $existingU = DB::connection('mysql_third')->table('ubicaciones')
                ->where('bodega', $data['bodega'] ?? '')
                ->where('ubicacion', $data['ubicacion'] ?? '')
                ->first();
            if ($existingU) {
                $ubicacionesId = (int) $existingU->id;
            } else {
                $ubicacionesId = (int) DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
                    'bodega' => $data['bodega'] ?? '',
                    'ubicacion' => $data['ubicacion'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // helper: ubicacion por defecto
        $getDefaultUbicId = function() {
            $row = DB::connection('mysql_third')->table('ubicaciones')
                ->where('bodega', '')
                ->where('ubicacion', '')
                ->first();
            if ($row) return (int) $row->id;
            return (int) DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
                'bodega' => '',
                'ubicacion' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $targetStatus = $data['estatus'] ?? 'disponible';
        $qty = (int) $data['stock'];
        $fromStatus = $data['from_status'] ?? null;
        $isNewLocation = isset($data['new_location']) && $data['new_location'] === '1';

        if ($isNewLocation) {
            // Crear nueva fila de inventarios para la nueva ubicación
            $insertUbicId = !is_null($ubicacionesId) ? $ubicacionesId : $getDefaultUbicId();
            DB::connection('mysql_third')->table('inventarios')->insert([
                'sku' => $sku,
                'stock' => 0, // comienza en 0; luego podrá transferir
                'estatus' => $targetStatus,
                'ubicaciones_id' => $insertUbicId,
            ]);
        } else if ($fromStatus && $fromStatus !== $targetStatus && $qty > 0) {
            // Transferencia entre estatus (generalizado)
            $origin = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $fromStatus)
                ->first();
            if ($origin) {
                $move = min($qty, (int) $origin->stock);
                $newOriginStock = max(0, (int) $origin->stock - $move);
                if ($newOriginStock > 0) {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->update(['stock' => $newOriginStock]);
                } else {
                    DB::connection('mysql_third')->table('inventarios')->where('id', $origin->id)->delete();
                }

                $dest = DB::connection('mysql_third')->table('inventarios')
                    ->where('sku', $sku)
                    ->where('estatus', $targetStatus)
                    ->first();

                $destUbicId = !is_null($ubicacionesId)
                    ? $ubicacionesId
                    : ($dest->ubicaciones_id ?? ($origin->ubicaciones_id ?? $getDefaultUbicId()));

                if ($dest) {
                    $update = [ 'stock' => ((int) $dest->stock) + $move ];
                    if (!is_null($ubicacionesId)) { $update['ubicaciones_id'] = $destUbicId; }
                    DB::connection('mysql_third')->table('inventarios')->where('id', $dest->id)->update($update);
                } else {
                    DB::connection('mysql_third')->table('inventarios')->insert([
                        'sku' => $sku,
                        'stock' => $move,
                        'estatus' => $targetStatus,
                        'ubicaciones_id' => $destUbicId,
                    ]);
                }
            }
        } else {
            // Upsert simple por sku + estatus
            $inv = DB::connection('mysql_third')->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $targetStatus)
                ->first();

            if ($inv) {
                $update = [ 'sku' => $sku, 'stock' => $qty, 'estatus' => $targetStatus ];
                if (!is_null($ubicacionesId)) { $update['ubicaciones_id'] = $ubicacionesId; }
                DB::connection('mysql_third')->table('inventarios')->where('id', $inv->id)->update($update);
            } else {
                $insertUbicId = !is_null($ubicacionesId) ? $ubicacionesId : $getDefaultUbicId();
                DB::connection('mysql_third')->table('inventarios')->insert([
                    'sku' => $sku,
                    'stock' => $qty,
                    'estatus' => $targetStatus,
                    'ubicaciones_id' => $insertUbicId,
                ]);
            }
        }

        return redirect()->route('articulos.index', ['per_page' => (int) ($data['per_page'] ?? 20)])
            ->with('status', 'Inventario actualizado');
    }
}
