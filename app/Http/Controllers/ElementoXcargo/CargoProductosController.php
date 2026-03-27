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
        $authUser = session('auth.user');
        $roleNames = [];
        
        // Función helper para extraer roles de diferentes estructuras
        $extractRoles = function($user) {
            $roles = [];
            $push = function($val) use (&$roles) { 
                if (is_string($val) && !empty(trim($val))) $roles[] = trim(strtolower($val)); 
            };
            
            // Buscar en campos de nivel superior
            $candidates = ['role','rol','perfil','role_name','nombre_rol','tipo_rol'];
            if (is_array($user)) {
                foreach ($candidates as $k) if (isset($user[$k])) $push($user[$k]);
                // Buscar en array de roles
                if (isset($user['roles']) && is_array($user['roles'])) {
                    foreach ($user['roles'] as $item) {
                        if (is_string($item)) { $push($item); continue; }
                        $roleKeys = ['name','nombre','role','rol','roles','slug','key','display_name'];
                        if (is_array($item)) {
                            foreach ($roleKeys as $kk) if (isset($item[$kk])) $push($item[$kk]);
                        } elseif (is_object($item)) {
                            foreach ($roleKeys as $kk) if (isset($item->$kk)) $push($item->$kk);
                        }
                    }
                }
            } elseif (is_object($user)) {
                foreach ($candidates as $k) if (isset($user->$k)) $push($user->$k);
                if (isset($user->roles) && is_array($user->roles)) {
                    foreach ($user->roles as $item) {
                        if (is_string($item)) { $push($item); continue; }
                        $roleKeys = ['name','nombre','role','rol','roles','slug','key','display_name'];
                        if (is_object($item)) {
                            foreach ($roleKeys as $kk) if (isset($item->$kk)) $push($item->$kk);
                        }
                    }
                }
            }
            return array_values(array_filter(array_unique($roles)));
        };
        
        $roleNames = $extractRoles($authUser);
        
        // Fallback: si no se detectaron roles, buscar substrings en el payload serializado
        if (empty($roleNames) && $authUser) {
            $serialized = strtolower(json_encode($authUser));
            if (strpos($serialized, 'hseq') !== false) $roleNames[] = 'hseq';
            if (strpos($serialized, 'talento') !== false) $roleNames[] = 'talento';
            if (strpos($serialized, 'talentohumano') !== false || strpos($serialized, 'talento humano') !== false) $roleNames[] = 'talento humano';
        }
        
        $isAdmin = false;
        foreach ($roleNames as $rn) {
            $rnc = str_replace(' ', '', $rn);
            if (strpos($rnc, 'admin') !== false || strpos($rnc, 'administrador') !== false) { $isAdmin = true; break; }
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
        $allowedSkus = [];
        $allowedNames = [];
        if (!empty($categoryFilters)) {
            try {
                $prodModel = new Producto();
                $conn = $prodModel->getConnectionName() ?: config('database.default');
                $table = $prodModel->getTable();
                $catQuery = \DB::connection($conn)->table($table)->select('sku', 'name_produc');
                $catQuery->where(function($qc) use ($categoryFilters){
                    foreach ($categoryFilters as $i => $term) {
                        $like = '%'.$term.'%';
                        if ($i === 0) $qc->whereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                        else $qc->orWhereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                    }
                });
                $productos = $catQuery->get();
                $allowedSkus = $productos->pluck('sku')->filter()->unique()->values()->all();
                $allowedNames = $productos->pluck('name_produc')->filter()->map(function($n){ return mb_strtolower(trim($n)); })->unique()->values()->all();
            } catch (\Throwable $e) {
                // Si falla, no aplicar filtro por categoría
            }
        }

        // Obtener todas las asignaciones primero
        $asignQuery = CargoProducto::with(['cargo','subArea'])->orderByDesc('id');
        if ($cargoId) { $asignQuery->where('cargo_id', $cargoId); }
        if ($subAreaId) { $asignQuery->where('sub_area_id', $subAreaId); }
        
        // Si hay filtros de categoría, filtrar por SKU O por nombre de producto
        // Aplica para todos los roles no-admin (incluyendo Talento Humano que solo ve dotación)
        $applyFilter = !empty($categoryFilters) && (!empty($allowedSkus) || !empty($allowedNames));
        if ($applyFilter) {
            $asignQuery->where(function($q) use ($allowedSkus, $allowedNames) {
                $first = true;
                // Filtrar por SKU (solo si hay SKUs)
                if (!empty($allowedSkus)) {
                    $q->whereIn('sku', $allowedSkus);
                    $first = false;
                }
                // También incluir por coincidencia de nombre (para asignaciones sin SKU)
                if (!empty($allowedNames)) {
                    foreach ($allowedNames as $nombre) {
                        if ($first) {
                            $q->whereRaw('LOWER(name_produc) LIKE ?', ['%'.$nombre.'%']);
                            $first = false;
                        } else {
                            $q->orWhereRaw('LOWER(name_produc) LIKE ?', ['%'.$nombre.'%']);
                        }
                    }
                }
            });
        }
        
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

        // Determinar filtros por rol (categorías permitidas) - usar misma lógica que index()
        $authUser = session('auth.user');
        $roleNames = [];
        
        // Función helper para extraer roles de diferentes estructuras
        $extractRoles = function($user) {
            $roles = [];
            $push = function($val) use (&$roles) { 
                if (is_string($val) && !empty(trim($val))) $roles[] = trim(strtolower($val)); 
            };
            
            $candidates = ['role','rol','perfil','role_name','nombre_rol','tipo_rol'];
            if (is_array($user)) {
                foreach ($candidates as $k) if (isset($user[$k])) $push($user[$k]);
                if (isset($user['roles']) && is_array($user['roles'])) {
                    foreach ($user['roles'] as $item) {
                        if (is_string($item)) { $push($item); continue; }
                        $roleKeys = ['name','nombre','role','rol','roles','slug','key','display_name'];
                        if (is_array($item)) {
                            foreach ($roleKeys as $kk) if (isset($item[$kk])) $push($item[$kk]);
                        } elseif (is_object($item)) {
                            foreach ($roleKeys as $kk) if (isset($item->$kk)) $push($item->$kk);
                        }
                    }
                }
            } elseif (is_object($user)) {
                foreach ($candidates as $k) if (isset($user->$k)) $push($user->$k);
                if (isset($user->roles) && is_array($user->roles)) {
                    foreach ($user->roles as $item) {
                        if (is_string($item)) { $push($item); continue; }
                        $roleKeys = ['name','nombre','role','rol','roles','slug','key','display_name'];
                        if (is_object($item)) {
                            foreach ($roleKeys as $kk) if (isset($item->$kk)) $push($item->$kk);
                        }
                    }
                }
            }
            return array_values(array_filter(array_unique($roles)));
        };
        
        $roleNames = $extractRoles($authUser);
        
        // Fallback: si no se detectaron roles, buscar substrings en el payload serializado
        if (empty($roleNames) && $authUser) {
            $serialized = strtolower(json_encode($authUser));
            if (strpos($serialized, 'hseq') !== false) $roleNames[] = 'hseq';
            if (strpos($serialized, 'talento') !== false) $roleNames[] = 'talento';
            if (strpos($serialized, 'talentohumano') !== false || strpos($serialized, 'talento humano') !== false) $roleNames[] = 'talento humano';
        }
        
        $isAdmin = false; 
        foreach ($roleNames as $rn) { 
            $rnc = str_replace(' ', '', $rn); 
            if (strpos($rnc, 'admin') !== false || strpos($rnc, 'administrador') !== false) { 
                $isAdmin = true; 
                break; 
            } 
        }

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

        // Si hay filtros de categoría, obtener productos permitidos (por SKU y por nombre)
        $allowedSkus = [];
        $allowedNames = [];
        if (!empty($categoryFilters)) {
            try {
                $prodModel = new Producto();
                $conn = $prodModel->getConnectionName() ?: config('database.default');
                $table = $prodModel->getTable();
                $catQuery = \DB::connection($conn)->table($table)->select('sku', 'name_produc');
                $catQuery->where(function($qc) use ($categoryFilters){
                    foreach ($categoryFilters as $i => $term) {
                        $like = '%'.$term.'%';
                        if ($i === 0) $qc->whereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                        else $qc->orWhereRaw('LOWER(categoria_produc) LIKE ?', [$like]);
                    }
                });
                $productos = $catQuery->get();
                $allowedSkus = $productos->pluck('sku')->filter()->unique()->values()->all();
                $allowedNames = $productos->pluck('name_produc')->filter()->map(function($n){ return mb_strtolower(trim($n)); })->unique()->values()->all();
            } catch (\Throwable $e) {
                // Error obteniendo productos, continuar sin filtro
            }
        }

        // Obtener todas las asignaciones
        $asignQuery = CargoProducto::select('cargo_id','sub_area_id','sku','name_produc');
        $asignaciones = $asignQuery->get();
        
        // Filtrar por categoría si aplica (por SKU O por nombre de producto)
        // Aplica para todos los roles no-admin (incluyendo Talento Humano que solo ve dotación)
        if (!empty($categoryFilters) && (!empty($allowedSkus) || !empty($allowedNames))) {
            $asignaciones = $asignaciones->filter(function($a) use ($allowedSkus, $allowedNames) {
                // Coincide por SKU exacto
                if (!empty($a->sku) && in_array($a->sku, $allowedSkus)) {
                    return true;
                }
                // Coincide por nombre - buscar si el nombre de la asignación contiene algún nombre de producto permitido
                if (!empty($a->name_produc)) {
                    $nombreAsignacion = mb_strtolower(trim($a->name_produc));
                    foreach ($allowedNames as $nombreProducto) {
                        // Si el nombre de la asignación contiene el nombre del producto (o viceversa)
                        if (strpos($nombreAsignacion, $nombreProducto) !== false) {
                            return true;
                        }
                        if (strpos($nombreProducto, $nombreAsignacion) !== false) {
                            return true;
                        }
                        // Comparar las primeras palabras significativas (ignorando talla, género, etc.)
                        $palabrasAsig = array_slice(preg_split('/\s+/', $nombreAsignacion), 0, 3);
                        $palabrasProd = array_slice(preg_split('/\s+/', $nombreProducto), 0, 3);
                        $coinciden = count(array_intersect($palabrasAsig, $palabrasProd));
                        if ($coinciden >= 2) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }

        // Construir mapa [sub_area_id][cargo_id] => array de productos
        $map = [];
        foreach ($asignaciones as $a) {
            $map[$a->sub_area_id][$a->cargo_id][] = ['sku' => $a->sku, 'name' => $a->name_produc];
        }
        return view('elementoxcargo.matriz', compact('cargos','subAreas','map'));
    }
}
