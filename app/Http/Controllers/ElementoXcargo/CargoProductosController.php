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

        // Filtrar asignaciones por rol del usuario en sesión
        // 1) Intentar filtrar por cargos cuyo nombre coincide con el rol
        $authUser = session('auth.user');
        $roleNames = [];
        if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles'])) {
            foreach ($authUser['roles'] as $r) {
                if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; }
                if (is_array($r) && isset($r['roles'])) { $roleNames[] = trim(strtolower($r['roles'])); continue; }
                if (is_array($r) && isset($r['name'])) { $roleNames[] = trim(strtolower($r['name'])); continue; }
            }
        } elseif (is_object($authUser) && isset($authUser->roles) && is_array($authUser->roles)) {
            foreach ($authUser->roles as $r) {
                if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; }
                if (is_object($r) && isset($r->roles)) { $roleNames[] = trim(strtolower($r->roles)); continue; }
                if (is_object($r) && isset($r->name)) { $roleNames[] = trim(strtolower($r->name)); continue; }
            }
        }
        $roleNames = array_values(array_filter(array_unique($roleNames)));
        $isAdmin = false;
        foreach ($roleNames as $rn) {
            $rnc = str_replace(' ', '', $rn);
            if (strpos($rnc, 'admin') !== false || strpos($rnc, 'administrador') !== false) { $isAdmin = true; break; }
        }
        $cargoIdsForRoles = [];
        if (!empty($roleNames) && !$isAdmin) {
            $cargoIdsForRoles = \DB::table('cargos')
                ->where(function($q) use ($roleNames) {
                    foreach ($roleNames as $i => $rn) {
                        if ($i === 0) $q->whereRaw('LOWER(nombre) = ?', [$rn]);
                        else $q->orWhereRaw('LOWER(nombre) = ?', [$rn]);
                    }
                })
                ->pluck('id')
                ->toArray();
        }

        // 2) Construir filtros de categoría de productos según rol (HSEQ vs Talento Humano)
        $categoryFilters = [];
        if (!$isAdmin) {
            foreach ($roleNames as $rn) {
                if (strpos($rn, 'hseq') !== false || strpos($rn, 'seguridad') !== false) {
                    $categoryFilters = array_merge($categoryFilters, array_map('trim', explode(',', config('vpl.role_filters.hseq', ''))));
                }
                if (strpos($rn, 'talento') !== false || strpos($rn, 'humano') !== false || $rn === 'th') {
                    $categoryFilters = array_merge($categoryFilters, array_map('trim', explode(',', config('vpl.role_filters.talento', ''))));
                }
            }
        }
        $categoryFilters = array_values(array_filter(array_unique(array_map(function($t){ return mb_strtolower($t); }, $categoryFilters))));

        // Si hay filtros de categoría, limitar asignaciones a SKUs cuyo `categoria_produc` coincida
        $allowedSkus = null;
        if (!empty($categoryFilters)) {
            try {
                $prodModel = new Producto();
                $conn = $prodModel->getConnectionName() ?: config('database.default');
                $table = $prodModel->getTable();
                $catQuery = \DB::connection($conn)->table($table)->select('sku');
                $catQuery->where(function($qc) use ($categoryFilters){
                    foreach ($categoryFilters as $i => $term) {
                        $like = '%'.$term.'%';
                        if ($i === 0) $qc->whereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                        else $qc->orWhereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                    }
                });
                $allowedSkus = $catQuery->pluck('sku')->filter()->unique()->values()->all();
            } catch (\Throwable $e) {
                $allowedSkus = null; // si falla, no aplicar filtro por categoría
            }
        }

        $asignQuery = CargoProducto::with(['cargo','subArea'])->orderByDesc('id');
        if (!empty($cargoIdsForRoles)) { $asignQuery->whereIn('cargo_id', $cargoIdsForRoles); }
        if (is_array($allowedSkus) && !empty($allowedSkus)) { $asignQuery->whereIn('sku', $allowedSkus); }
        if ($cargoId) { $asignQuery->where('cargo_id', $cargoId); }
        if ($subAreaId) { $asignQuery->where('sub_area_id', $subAreaId); }
        $asignaciones = $asignQuery->paginate($perPage, ['*'], 'page')->appends(['per_page' => $perPage]);

        return view('elementoxcargo.productos', compact('cargos', 'subAreas', 'cargoId', 'subAreaId', 'asignaciones', 'perPage', 'allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
            'sub_area_id' => ['required', 'integer', 'exists:sub_areas,id'],
            'sku' => ['required', 'string'],
        ]);

        $prod = Producto::where('sku', $data['sku'])->first();
        $name = $prod ? $prod->name_produc : (string) $data['sku'];

        // Validar categoría del producto contra el rol en sesión (opcional estricto)
        try {
            $authUser = session('auth.user');
            $roleNames = [];
            if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles'])) {
                foreach ($authUser['roles'] as $r) { if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; } if (is_array($r) && isset($r['roles'])) { $roleNames[] = trim(strtolower($r['roles'])); continue; } if (is_array($r) && isset($r['name'])) { $roleNames[] = trim(strtolower($r['name'])); continue; } }
            } elseif (is_object($authUser) && isset($authUser->roles) && is_array($authUser->roles)) {
                foreach ($authUser->roles as $r) { if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; } if (is_object($r) && isset($r->roles)) { $roleNames[] = trim(strtolower($r->roles)); continue; } if (is_object($r) && isset($r->name)) { $roleNames[] = trim(strtolower($r->name)); continue; } }
            }
            $filters = [];
            $isAdmin = false; foreach ($roleNames as $rn) { $rnc = str_replace(' ', '', $rn); if (strpos($rnc, 'admin') !== false || strpos($rnc, 'administrador') !== false) { $isAdmin = true; break; } }
            if (!$isAdmin) {
                foreach ($roleNames as $rn) {
                    if (strpos($rn, 'hseq') !== false || strpos($rn, 'seguridad') !== false) { $filters = array_merge($filters, array_map('trim', explode(',', config('vpl.role_filters.hseq', '')))); }
                    if (strpos($rn, 'talento') !== false || strpos($rn, 'humano') !== false || $rn === 'th') { $filters = array_merge($filters, array_map('trim', explode(',', config('vpl.role_filters.talento', '')))); }
                }
            }
            $filters = array_values(array_filter(array_unique(array_map(function($t){ return mb_strtolower($t); }, $filters))));
            if (!empty($filters) && $prod && !empty($prod->categoria_produc)) {
                $cat = mb_strtolower($prod->categoria_produc);
                $ok = false; foreach ($filters as $term) { if (strpos($cat, $term) !== false) { $ok = true; break; } }
                if (!$ok) {
                    return back()->with('errorMessage', 'El producto no pertenece a la categoría permitida para su rol');
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

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

        // Determinar filtros por rol (categorías permitidas)
        $authUser = session('auth.user');
        $roleNames = [];
        if (is_array($authUser) && isset($authUser['roles']) && is_array($authUser['roles'])) {
            foreach ($authUser['roles'] as $r) {
                if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; }
                if (is_array($r) && isset($r['roles'])) { $roleNames[] = trim(strtolower($r['roles'])); continue; }
                if (is_array($r) && isset($r['name'])) { $roleNames[] = trim(strtolower($r['name'])); continue; }
            }
        } elseif (is_object($authUser) && isset($authUser->roles) && is_array($authUser->roles)) {
            foreach ($authUser->roles as $r) {
                if (is_string($r)) { $roleNames[] = trim(strtolower($r)); continue; }
                if (is_object($r) && isset($r->roles)) { $roleNames[] = trim(strtolower($r->roles)); continue; }
                if (is_object($r) && isset($r->name)) { $roleNames[] = trim(strtolower($r->name)); continue; }
            }
        }
        $roleNames = array_values(array_filter(array_unique($roleNames)));
        $isAdmin = false; foreach ($roleNames as $rn) { $rnc = str_replace(' ', '', $rn); if (strpos($rnc, 'admin') !== false || strpos($rnc, 'administrador') !== false) { $isAdmin = true; break; } }

        $categoryFilters = [];
        if (!$isAdmin) {
            foreach ($roleNames as $rn) {
                if (strpos($rn, 'hseq') !== false || strpos($rn, 'seguridad') !== false) {
                    $categoryFilters = array_merge($categoryFilters, array_map('trim', explode(',', config('vpl.role_filters.hseq', ''))));
                }
                if (strpos($rn, 'talento') !== false || strpos($rn, 'humano') !== false || $rn === 'th') {
                    $categoryFilters = array_merge($categoryFilters, array_map('trim', explode(',', config('vpl.role_filters.talento', ''))));
                }
            }
        }
        $categoryFilters = array_values(array_filter(array_unique(array_map(function($t){ return mb_strtolower($t); }, $categoryFilters))));

        // Si hay filtros de categoría, obtener SKUs permitidos y filtrar asignaciones
        $allowedSkus = null;
        if (!empty($categoryFilters)) {
            try {
                $prodModel = new Producto();
                $conn = $prodModel->getConnectionName() ?: config('database.default');
                $table = $prodModel->getTable();
                $catQuery = \DB::connection($conn)->table($table)->select('sku');
                $catQuery->where(function($qc) use ($categoryFilters){
                    foreach ($categoryFilters as $i => $term) {
                        $like = '%'.$term.'%';
                        if ($i === 0) $qc->whereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                        else $qc->orWhereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                    }
                });
                $allowedSkus = $catQuery->pluck('sku')->filter()->unique()->values()->all();
            } catch (\Throwable $e) {
                $allowedSkus = null;
            }
        }

        $asignQuery = CargoProducto::select('cargo_id','sub_area_id','sku','name_produc');
        if (is_array($allowedSkus) && !empty($allowedSkus)) {
            $asignQuery->whereIn('sku', $allowedSkus);
        }
        $asignaciones = $asignQuery->get();

        // Construir mapa [sub_area_id][cargo_id] => array de productos
        $map = [];
        foreach ($asignaciones as $a) {
            $map[$a->sub_area_id][$a->cargo_id][] = ['sku' => $a->sku, 'name' => $a->name_produc];
        }
        return view('elementoxcargo.matriz', compact('cargos','subAreas','map'));
    }
}
