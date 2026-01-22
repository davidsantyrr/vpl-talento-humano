<?php

namespace App\Http\Controllers\articulos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\GestionArticulos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InventariosExport;
use Illuminate\Support\Collection;

class ArticulosController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 20, 50]) ? $perPage : 20;
        $category = trim((string) $request->get('category', ''));

        // listado de categorías disponibles (union externas + locales)
        $catExt = Producto::select('categoria_produc')
            ->whereNotNull('categoria_produc')
            ->distinct()
            ->orderBy('categoria_produc')
            ->pluck('categoria_produc')
            ->toArray();
        $catLocal = GestionArticulos::select('categoria')
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->toArray();
        $categories = collect(array_unique(array_merge($catExt, $catLocal)))->sort()->values();

        // Filtrar opciones de categoría que se mostrarán en el select según rol
        $user = session('auth.user');
        $roleStringsForCategories = $this->collectRoleStringsFromUser($user);
        $isAdmin = $this->matchesRoleStringArray($roleStringsForCategories, ['administrador','admin']);
        $isHseq = $this->matchesRoleStringArray($roleStringsForCategories, ['hseq']);
        $isTalento = $this->matchesRoleStringArray($roleStringsForCategories, ['talento','talentohumano']);

        if (!$isAdmin) {
            if ($isHseq) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.hseq')));
                $categories = $categories->filter(function($c) use ($patterns) {
                    foreach ($patterns as $p) {
                        if (stripos($c, $p) !== false) return true;
                    }
                    return false;
                })->values();
            } elseif ($isTalento) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.talento')));
                $categories = $categories->filter(function($c) use ($patterns) {
                    foreach ($patterns as $p) {
                        if (stripos($c, $p) !== false) return true;
                    }
                    return false;
                })->values();
            } else {
                // Si no es ninguno, mostrar nada salvo 'Todas'
                $categories = collect([]);
            }
        }

        // consulta de productos con filtro opcional por categoría
        $productosQuery = Producto::select('sku', 'name_produc', 'categoria_produc')
            ->orderBy('name_produc');

        // Aplicar filtro por rol (HSEQ / Talento humano) si corresponde
        $user = session('auth.user');
        $roleStrings = $this->collectRoleStringsFromUser($user);
        $isAdmin = $this->matchesRoleStringArray($roleStrings, ['administrador','admin']);
        $isHseq = $this->matchesRoleStringArray($roleStrings, ['hseq']);
        $isTalento = $this->matchesRoleStringArray($roleStrings, ['talento','talentohumano']);

        // Aplicar filtros solo si no es admin
        if (!$isAdmin) {
            if ($isHseq) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.hseq')));
                $productosQuery->where(function($q) use ($patterns) {
                    foreach ($patterns as $p) {
                        $q->orWhere('categoria_produc', 'like', '%' . $p . '%');
                    }
                });
            } elseif ($isTalento) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.talento')));
                $productosQuery->where(function($q) use ($patterns) {
                    foreach ($patterns as $p) {
                        $q->orWhere('categoria_produc', 'like', '%' . $p . '%');
                    }
                });
            }
        }

        if ($category !== '') {
            $productosQuery->where('categoria_produc', $category);
        }

        $productos = $productosQuery
            ->paginate($perPage)
            ->appends(['per_page' => $perPage, 'category' => $category]);

        $skus = $productos->pluck('sku');
        $skusArr = $skus->map(function($s){ return (string)$s; })->all();
        // traer todas las filas de inventarios (pueden existir múltiples por SKU)
        $inventariosRows = DB::connection('mysql_third')
            ->table('inventarios as i')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'i.ubicaciones_id')
            ->whereIn('i.sku', $skus)
            ->select('i.id as inventario_id','i.sku','i.stock','i.estatus','u.ubicacion','u.bodega','u.id as ubicaciones_id')
            ->orderBy('i.sku')
            ->get();
        // agrupar por SKU
        $inventariosBySku = $inventariosRows->groupBy('sku');

        // intentar traer precios desde posibles conexiones y detectar columnas de precio comunes
        $pricesBySku = collect();
        $connectionsToTry = ['mysql_second', 'mysql_third', null];
        $candidateCols = ['price_produc','precio','price','precio_unitario','valor','valor_unitario','precio_proveedor','precio_provedor'];
        foreach ($connectionsToTry as $connName) {
            try {
                $connection = $connName ? DB::connection($connName) : DB::connection();
                $qb = $connection->table('productoxproveedor');

                // detectar columnas disponibles en la tabla
                $cols = [];
                try { $cols = $connection->getSchemaBuilder()->getColumnListing('productoxproveedor'); } catch (\Throwable $_) { $cols = []; }

                // obtener filas según columna disponible (sku o producto_id)
                if (in_array('sku', $cols)) {
                    $rows = $qb->whereIn('sku', $skusArr)->get();
                } elseif (in_array('producto_id', $cols) || in_array('product_id', $cols)) {
                    // mapear SKUs a producto_id en la misma conexión (tabla 'productos')
                    $skuToId = [];
                    try {
                        $prodRows = $connection->table('productos')->whereIn('sku', $skusArr)->select('id','sku')->get();
                        foreach ($prodRows as $pr) { $skuToId[(string)$pr->sku] = $pr->id; }
                        $ids = array_values($skuToId);
                        if (!empty($ids)) {
                            $colId = in_array('producto_id', $cols) ? 'producto_id' : 'product_id';
                            $rows = $qb->whereIn($colId, $ids)->get();
                        } else {
                            $rows = collect();
                        }
                    } catch (\Throwable $_) {
                        $rows = collect();
                    }
                } else {
                    // leer todo y filtrar por sku si es posible
                    $rows = $qb->get();
                }

                if (empty($rows) || $rows->isEmpty()) continue;

                foreach ($rows as $r) {
                    $rArr = (array)$r;
                    $found = null;
                    // buscar columna de precio conocida
                    foreach ($candidateCols as $c) {
                        if (array_key_exists($c, $rArr) && is_numeric($rArr[$c])) { $found = $rArr[$c]; break; }
                    }
                    if ($found === null) {
                        // buscar la primera columna numérica disponible
                        foreach ($rArr as $k => $v) {
                            if (in_array($k, ['sku','producto_id','product_id'])) continue;
                            if (is_numeric($v)) { $found = $v; break; }
                        }
                    }
                    // resolver clave para mapear por sku si fila usa producto_id
                    if (array_key_exists('sku', $rArr)) {
                        $key = (string)$rArr['sku'];
                    } elseif (array_key_exists('producto_id', $rArr) || array_key_exists('product_id', $rArr)) {
                        $prodId = $rArr['producto_id'] ?? ($rArr['product_id'] ?? null);
                        $key = null;
                        if ($prodId) {
                            try {
                                $prod = $connection->table('productos')->where('id', $prodId)->select('sku')->first();
                                if ($prod && isset($prod->sku)) $key = (string)$prod->sku;
                            } catch (\Throwable $_) { $key = null; }
                        }
                        if ($key === null) continue; // no podemos mapear a SKU
                    } else {
                        // sin clave clara; intentar saltar
                        continue;
                    }
                    $pricesBySku[$key] = $found;
                }

                if (!empty($pricesBySku)) break;
            } catch (\Throwable $e) {
                Log::debug('ArticulosController: error leyendo productoxproveedor en ' . ($connName ?? 'default'), ['msg'=>$e->getMessage()]);
                continue;
            }
        }
        $pricesBySku = collect($pricesBySku);

        $rowsHtml = '';
        $remoteSkus = collect($productos->pluck('sku'))->map(function($s){ return (string) $s; })->all();
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

                // Botones de acción según el estatus
                $botonesAccion = '';
                if ($estatus === 'destruido') {
                    // Solo botón de ojo para ver constancias
                    $botonesAccion = '<button type="button" class="btn-icon view-constancias" title="Ver Constancias de Destrucción" aria-label="Ver Constancias" data-sku="' . e($p->sku) . '">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
                      . '<path d="M12 5C7 5 2.73 8.11 1 12.5 2.73 16.89 7 20 12 20s9.27-3.11 11-7.5C21.27 8.11 17 5 12 5z" stroke="currentColor" stroke-width="1.5" fill="none"/>'
                      . '<circle cx="12" cy="12.5" r="3.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
                      . '</svg>'
                      . '</button>'
                      . '<span style="color: #999; font-size: 0.875rem; margin-left: 8px;">Artículo destruido</span>';
                } else {
                    // Botones normales para otros estatus
                    $botonesAccion = '<button type="button" class="btn-icon location" title="Ubicación" aria-label="Ubicación">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s-7-6.16-7-11a7 7 0 1 1 14 0c0 4.84-7 11-7 11z" stroke="currentColor" stroke-width="1.2" fill="none"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon edit" title="Editar" aria-label="Editar">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25z" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M14.62 5.63l3.75 3.75" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>'
                      . '<button type="button" class="btn-icon delete" title="Destruir" aria-label="Destruir">'
                      . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2"/><path d="M8 6V4h8v2" stroke="currentColor" stroke-width="1.2"/><path d="M6 6l1 14h10l1-14" stroke="currentColor" stroke-width="1.2"/></svg>'
                      . '</button>';

                    // Mostrar botón para eliminar la ubicación si hay más de una ubicación para el mismo estatus
                    $sameStatusLocations = collect($rows)->filter(function($x) use ($estatus){ return ($x->estatus ?? '') === $estatus; })->pluck('ubicacion')->filter()->unique();
                    if ($sameStatusLocations->count() > 1 && !empty($ubicacionSel) && !empty($inv->inventario_id)) {
                        $botonesAccion .= '<form method="POST" action="' . route('articulos.ubicacion.eliminar') . '" class="delete-location-form" style="display:inline;margin-left:6px;">'
                            . csrf_field()
                            . '<input type="hidden" name="inventario_id" value="' . e($inv->inventario_id) . '">'
                            . '<input type="hidden" name="per_page" value="' . e($perPage) . '">'
                            . '<input type="hidden" name="category" value="' . e($category) . '">'
                            . '<button type="submit" class="btn-icon delete-location" title="Eliminar ubicación" aria-label="Eliminar ubicación">'
                            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2"/><path d="M8 6V4h8v2" stroke="currentColor" stroke-width="1.2"/><path d="M6 6l1 14h10l1-14" stroke="currentColor" stroke-width="1.2"/></svg>'
                            . '</button>'
                            . '</form>';
                    }
                }

                                $priceVal = $pricesBySku->get((string)$p->sku) ?? null;
                                $priceDisplay = is_null($priceVal) ? '' : number_format((float)$priceVal, 2, '.', '');

                                $rowsHtml .= '<tr data-sku="' . e($p->sku) . '" data-price="' . e($priceDisplay) . '" data-bodega="' . e($bodegaSel) . '" data-ubicacion="' . e($ubicacionSel) . '" data-estatus="' . e($estatus) . '" data-stock="' . e($stock) . '">'
                                        . '<td>' . e($p->sku) . '</td>'
                                        . '<td>' . e($p->name_produc) . '</td>'
                                        . '<td>' . e($p->categoria_produc) . '</td>'
                                        . '<td>' . e($bodegaSel ?: '-') . '</td>'
                                        . '<td>' . e($ubicacionSel ?: '-') . '</td>'
                                        . '<td>' . e(ucfirst($estatus)) . '</td>'
                                        . '<td><input type="text" disabled class="price-input" data-sku="' . e($p->sku) . '" value="' . e($priceDisplay) . '" style="width:90px; text-align:right;" /></td>'
                                        . '<td>' . e($stock) . '</td>'
                                        . '<td>'
                                            . '<div class="actions" style="display:inline-flex; gap:8px; align-items:center;">'
                                            . $botonesAccion
                                            . '</div>'
                                        . '</td>'
                                        . '</tr>';
            }
        }

                // Agregar artículos locales no presentes en catálogo externo (mysql_second)
                $extrasQuery = GestionArticulos::query();
                if ($category !== '') {
                        $extrasQuery->where('categoria', $category);
                }
                // Aplicar mismo filtrado por rol para extras (si no es admin)
                if (!$isAdmin) {
                    if ($isHseq) {
                        $patterns = array_map('trim', explode(',', config('vpl.role_filters.hseq')));
                        $extrasQuery->where(function($q) use ($patterns) {
                            foreach ($patterns as $p) {
                                $q->orWhere('categoria', 'like', '%' . $p . '%');
                            }
                        });
                    } elseif ($isTalento) {
                        $patterns = array_map('trim', explode(',', config('vpl.role_filters.talento')));
                        $extrasQuery->where(function($q) use ($patterns) {
                            foreach ($patterns as $p) {
                                $q->orWhere('categoria', 'like', '%' . $p . '%');
                            }
                        });
                    }
                }

                $extras = $extrasQuery->whereNotIn('sku', $remoteSkus)->orderBy('nombre_articulo')->get();

                foreach ($extras as $loc) {
                        $rows = collect([ (object) [
                                'sku' => $loc->sku,
                                'stock' => 0,
                                'estatus' => 'disponible',
                                'ubicacion' => '',
                                'bodega' => ''
                        ] ]);

                        foreach ($rows as $inv) {
                                $stock = (int) ($inv->stock ?? 0);
                                $estatus = $inv->estatus ?? 'disponible';
                                $ubicacionSel = $inv->ubicacion ?? '';
                                $bodegaSel = $inv->bodega ?? '';

                                $botonesAccion = '<button type="button" class="btn-icon location" title="Ubicación" aria-label="Ubicación">'
                                    . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s-7-6.16-7-11a7 7 0 1 1 14 0c0 4.84-7 11-7 11z" stroke="currentColor" stroke-width="1.2" fill="none"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>'
                                    . '</button>'
                                    . '<button type="button" class="btn-icon edit" title="Editar" aria-label="Editar">'
                                    . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L18.37 9.38l-3.75-3.75L3 17.25z" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M14.62 5.63l3.75 3.75" stroke="currentColor" stroke-width="1.2"/></svg>'
                                    . '</button>'
                                    . '<button type="button" class="btn-icon delete" title="Destruir" aria-label="Destruir">'
                                    . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2"/><path d="M8 6V4h8v2" stroke="currentColor" stroke-width="1.2"/><path d="M6 6l1 14h10l1-14" stroke="currentColor" stroke-width="1.2"/></svg>'
                                    . '</button>';

                                $priceValLocal = $pricesBySku->get((string)$loc->sku) ?? null;
                                $priceDisplayLocal = is_null($priceValLocal) ? '' : number_format((float)$priceValLocal, 2, '.', '');
                                $rowsHtml .= '<tr data-sku="' . e($loc->sku) . '" data-price="' . e($priceDisplayLocal) . '" data-bodega="' . e($bodegaSel) . '" data-ubicacion="' . e($ubicacionSel) . '" data-estatus="' . e($estatus) . '" data-stock="' . e($stock) . '">'
                                        . '<td>' . e($loc->sku) . '</td>'
                                        . '<td>' . e($loc->nombre_articulo) . '</td>'
                                        . '<td>' . e($loc->categoria ?? '') . '</td>'
                                        . '<td>' . e($bodegaSel ?: '-') . '</td>'
                                        . '<td>' . e($ubicacionSel ?: '-') . '</td>'
                                        . '<td>' . e(ucfirst($estatus)) . '</td>'
                                        . '<td><input type="text" disabled class="price-input" data-sku="' . e($loc->sku) . '" value="' . e($priceDisplayLocal) . '" style="width:90px; text-align:right;" /></td>'
                                        . '<td>' . e($stock) . '</td>'
                                        . '<td>'
                                            . '<div class="actions" style="display:inline-flex; gap:8px; align-items:center;">'
                                            . $botonesAccion
                                            . '</div>'
                                        . '</td>'
                                        . '</tr>';
                        }
                }

        return view('articulos.articulos', [
            'rowsHtml' => $rowsHtml,
            'paginationHtml' => $this->buildPagination($productos, $perPage, $category),
            'perPage' => $perPage,
            'categories' => $categories,
            'selectedCategory' => $category,
            'status' => session('status'),
            'canExport' => ($isAdmin || $isHseq || $isTalento),
        ]);
    }

    private function buildPagination($productos, $perPage, $category = '')
    {
        $paginationHtml = '';
        if ($productos->hasPages()) {
            $paginationHtml .= '<nav aria-label="Paginación"><ul class="pagination">';
            if ($productos->onFirstPage()) {
                $paginationHtml .= '<li class="disabled"><span>&lsaquo;</span></li>';
            } else {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage, 'category' => $category])->previousPageUrl() . '" rel="prev">&lsaquo;</a></li>';
            }
            $window = $perPage <= 10 ? 3 : ($perPage <= 20 ? 5 : 7);
            $start = max(1, $productos->currentPage() - intdiv($window, 2));
            $end = min($productos->lastPage(), $start + $window - 1);
            for ($page = $start; $page <= $end; $page++) {
                if ($page == $productos->currentPage()) {
                    $paginationHtml .= '<li class="active"><span>' . $page . '</span></li>';
                } else {
                    $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage, 'category' => $category])->url($page) . '">' . $page . '</a></li>';
                }
            }
            if ($productos->hasMorePages()) {
                $paginationHtml .= '<li><a href="' . $productos->appends(['per_page' => $perPage, 'category' => $category])->nextPageUrl() . '" rel="next">&rsaquo;</a></li>';
            } else {
                $paginationHtml .= '<li class="disabled"><span>&rsaquo;</span></li>';
            }
            $paginationHtml .= '</ul></nav>';
        }
        return $paginationHtml;
    }

    private function collectRoleStringsFromUser($user): array
    {
        $out = [];
        $push = function($val) use (&$out) { if (is_string($val)) $out[] = mb_strtolower(trim($val)); };
        $candidates = ['role','rol','perfil','roles','perfil_name','perfilNombre','role_name','nombre_rol','slug','key','codigo','tipo','tipo_rol','name','display_name','full_name','nombre','nombres','usuario'];
        if (is_array($user)) {
            foreach ($candidates as $k) if (isset($user[$k])) $push($user[$k]);
            if (isset($user['roles']) && is_array($user['roles'])) {
                foreach ($user['roles'] as $item) {
                    if (is_string($item)) $push($item);
                    elseif (is_array($item)) foreach (['name','nombre','role','rol','roles'] as $kk) if (isset($item[$kk])) $push($item[$kk]);
                    elseif (is_object($item)) foreach (['name','nombre','role','rol','roles'] as $kk) if (isset($item->$kk)) $push($item->$kk);
                }
            }
        } elseif (is_object($user)) {
            foreach ($candidates as $k) if (isset($user->$k)) $push($user->$k);
            if (isset($user->roles) && is_array($user->roles)) foreach ($user->roles as $item) {
                if (is_string($item)) $push($item);
                elseif (is_object($item)) foreach (['name','nombre','role','rol','roles'] as $kk) if (isset($item->$kk)) $push($item->$kk);
            }
        }
        return array_values(array_unique(array_filter($out)));
    }

    private function matchesRoleStringArray(array $values, array $needles): bool
    {
        foreach ($values as $v) {
            $vClean = str_replace(' ', '', $v);
            foreach ($needles as $n) {
                if (strpos($vClean, str_replace(' ', '', mb_strtolower($n))) !== false) return true;
            }
        }
        return false;
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

        // Si se envió price en el formulario, intentar guardarla en productoxproveedor
        if ($request->has('price')) {
            try {
                $priceVal = $request->input('price');
                $priceVal = ($priceVal === '' || is_null($priceVal)) ? null : (float)$priceVal;
                $this->upsertPriceForSku($sku, $priceVal);
            } catch (\Throwable $e) {
                Log::warning('No se pudo guardar price desde update', ['sku'=>$sku,'msg'=>$e->getMessage()]);
            }
        }

        return redirect()->route('articulos.index', ['per_page' => (int) ($data['per_page'] ?? 20)])
            ->with('status', 'Inventario actualizado');
    }

    // Guardar/actualizar precio en la tabla productoxproveedor (conexion mysql_second)
    public function savePrice(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required','string','max:255'],
            'price' => ['nullable','numeric']
        ]);
        $sku = $data['sku'];
        $price = isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null;
        try {
            DB::connection('mysql_second')->table('productoxproveedor')->updateOrInsert(
                ['sku' => $sku],
                ['price_produc' => $price, 'updated_at' => now(), 'created_at' => now()]
            );
            return response()->json(['success' => true, 'sku' => $sku, 'price' => $price]);
        } catch (\Throwable $e) {
            Log::error('savePrice error', ['msg'=>$e->getMessage(),'sku'=>$sku]);
            return response()->json(['success'=>false,'message'=>'No se pudo guardar el precio'],500);
        }
    }

    /**
     * Upsert price into productoxproveedor for the given sku.
     * Tries by sku column, falls back to producto_id mapping.
     */
    private function upsertPriceForSku(string $sku, $price)
    {
        try {
            DB::connection('mysql_second')->table('productoxproveedor')->updateOrInsert(
                ['sku' => $sku],
                ['price_produc' => $price, 'updated_at' => now(), 'created_at' => now()]
            );
            return;
        } catch (\Throwable $e) {
            // continuar a fallback
        }

        // fallback: map producto_id from productos table
        try {
            $prod = DB::connection('mysql_second')->table('productos')->where('sku', $sku)->select('id')->first();
            if ($prod && isset($prod->id)) {
                DB::connection('mysql_second')->table('productoxproveedor')->updateOrInsert(
                    ['producto_id' => $prod->id],
                    ['price_produc' => $price, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        } catch (\Throwable $_) {
            // swallow fallback errors
        }
    }

    public function destruir(Request $request)
    {
        try {
            $data = $request->validate([
                'sku' => ['required','string'],
                'bodega' => ['nullable','string','max:255'],
                'ubicacion' => ['nullable','string','max:255'],
                'estatus' => ['required','in:disponible,perdido,prestado,destruido'],
                'cantidad' => ['required','integer','min:1'],
                'constancia' => ['required','file','mimes:pdf','max:5120'], // máx 5MB
                'per_page' => ['nullable','integer'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        }

        try {
            $sku = $data['sku'];
            $cantidad = (int) $data['cantidad'];
            $estatusOrigen = $data['estatus'];

            // Validar que no esté intentando destruir algo ya destruido
            if ($estatusOrigen === 'destruido') {
                return response()->json([
                    'success' => false,
                    'message' => 'El artículo ya está destruido'
                ], 400);
            }

            // Buscar inventario origen
            $inventarioOrigen = DB::connection('mysql_third')
                ->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', $estatusOrigen)
                ->first();

            if (!$inventarioOrigen || (int)$inventarioOrigen->stock < $cantidad) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay suficiente stock {$estatusOrigen} para destruir"
                ], 400);
            }

            // Guardar archivo PDF
            if ($request->hasFile('constancia')) {
                $file = $request->file('constancia');
                
                // Crear directorio si no existe (storage/app/constancias_destruccion)
                $directory = storage_path('app/constancias_destruccion');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Generar nombre único: SKU_FECHA_TIMESTAMP.pdf
                $fecha = now()->format('Y-m-d');
                $timestamp = now()->timestamp;
                $nombreArchivo = "{$sku}_{$fecha}_{$timestamp}.pdf";
                
                // Mover archivo
                $file->move($directory, $nombreArchivo);
                $rutaArchivo = "constancias_destruccion/{$nombreArchivo}";
                
                Log::info('Constancia de destrucción guardada', [
                    'sku' => $sku,
                    'ruta_completa' => $directory . '/' . $nombreArchivo,
                    'ruta_relativa' => $rutaArchivo,
                    'nombre_archivo' => $nombreArchivo
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe cargar la constancia de destrucción'
                ], 400);
            }

            // Restar del inventario origen
            $nuevoStockOrigen = (int)$inventarioOrigen->stock - $cantidad;
            if ($nuevoStockOrigen > 0) {
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioOrigen->id)
                    ->update(['stock' => $nuevoStockOrigen]);
            } else {
                // Si llega a 0, eliminar la fila
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioOrigen->id)
                    ->delete();
            }

            // Buscar o crear inventario destruido
            $inventarioDestruido = DB::connection('mysql_third')
                ->table('inventarios')
                ->where('sku', $sku)
                ->where('estatus', 'destruido')
                ->first();

            if ($inventarioDestruido) {
                // Sumar al existente
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->where('id', $inventarioDestruido->id)
                    ->update(['stock' => (int)$inventarioDestruido->stock + $cantidad]);
            } else {
                // Crear nuevo registro
                DB::connection('mysql_third')
                    ->table('inventarios')
                    ->insert([
                        'sku' => $sku,
                        'stock' => $cantidad,
                        'estatus' => 'destruido',
                        'ubicaciones_id' => $inventarioOrigen->ubicaciones_id ?? 1
                    ]);
            }

            // Registrar la destrucción en una tabla de log (opcional pero recomendado)
            try {
                $authUser = session('auth.user');
                $nombreUsuario = 'sistema';
                if (is_array($authUser) && isset($authUser['name'])) {
                    $nombreUsuario = $authUser['name'];
                } elseif (is_object($authUser) && isset($authUser->name)) {
                    $nombreUsuario = $authUser->name;
                }
                
                DB::connection('mysql_third')
                    ->table('log_destrucciones')
                    ->insert([
                        'sku' => $sku,
                        'cantidad' => $cantidad,
                        'estatus_origen' => $estatusOrigen,
                        'constancia_path' => $rutaArchivo,
                        'usuario' => $nombreUsuario,
                        'created_at' => now()
                    ]);
            } catch (\Exception $logError) {
                // Si falla el log, solo registrar el error pero continuar
                Log::warning('No se pudo guardar log de destrucción', ['error' => $logError->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Artículo destruido correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error destruyendo artículo', [
                'error' => $e->getMessage(),
                'sku' => $data['sku'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la destrucción: ' . $e->getMessage()
            ], 500);
        }
    }

    public function eliminarUbicacion(Request $request)
    {
        $data = $request->validate([
            'inventario_id' => ['required','integer'],
            'per_page' => ['nullable','integer'],
            'category' => ['nullable','string']
        ]);

        try {
            $inv = DB::connection('mysql_third')->table('inventarios')->where('id', $data['inventario_id'])->first();
            if (!$inv) {
                return redirect()->back()->with('error', 'Inventario no encontrado');
            }

            $ubicacionesId = $inv->ubicaciones_id ?? null;

            // Eliminar inventario
            DB::connection('mysql_third')->table('inventarios')->where('id', $data['inventario_id'])->delete();

            // Si la ubicación quedó sin referencias, eliminarla también
            if (!is_null($ubicacionesId)) {
                $count = DB::connection('mysql_third')->table('inventarios')->where('ubicaciones_id', $ubicacionesId)->count();
                if ($count === 0) {
                    DB::connection('mysql_third')->table('ubicaciones')->where('id', $ubicacionesId)->delete();
                }
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Ubicación eliminada']);
            }
            return redirect()->route('articulos.index', [
                'per_page' => (int)($data['per_page'] ?? 20),
                'category' => $data['category'] ?? ''
            ])->with('status', 'Ubicación eliminada');

        } catch (\Exception $e) {
            Log::error('Error eliminando inventario/ubicación', ['error' => $e->getMessage(), 'inventario_id' => $data['inventario_id']]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Error eliminando la ubicación'], 500);
            }
            return redirect()->back()->with('error', 'Error eliminando la ubicación');
        }
    }

    /**
     * Exportar inventarios a Excel. Acceso condicionado por rol (admin/hseq).
     */
    public function exportInventario(Request $request)
    {
        $user = session('auth.user');
        $roles = $this->collectRoleStringsFromUser($user);
        $allowed = $this->matchesRoleStringArray($roles, ['administrador','admin','hseq','talento','talentohumano']);
        if (!$allowed) {
            abort(403, 'No autorizado');
        }

        // Determinar rol para aplicar filtros de categoría similares a la vista
        $isAdmin = $this->matchesRoleStringArray($roles, ['administrador','admin']);
        $isHseq = $this->matchesRoleStringArray($roles, ['hseq']);
        $isTalento = $this->matchesRoleStringArray($roles, ['talento','talentohumano']);

        $out = collect();

        if ($isAdmin) {
            // traer inventarios con ubicaciones (todos)
            $rows = DB::connection('mysql_third')
                ->table('inventarios as i')
                ->leftJoin('ubicaciones as u', 'u.id', '=', 'i.ubicaciones_id')
                ->select('i.sku', 'i.stock', 'i.estatus', 'u.bodega', 'u.ubicacion')
                ->orderBy('i.sku')
                ->get();

            foreach ($rows as $r) {
                $sku = (string)($r->sku ?? '');
                $prod = null;
                try { $prod = Producto::where('sku', $sku)->first(); } catch (\Throwable $_) { $prod = null; }
                $name = $prod ? ($prod->name_produc ?? '') : '';
                $cat = $prod ? ($prod->categoria_produc ?? '') : '';
                $price = null;
                try {
                    $p = DB::connection('mysql_second')->table('productoxproveedor')->where('sku', $sku)->select('price_produc')->first();
                    if ($p && isset($p->price_produc)) $price = $p->price_produc;
                } catch (\Throwable $_) {
                    try {
                        $prodRow = DB::connection('mysql_second')->table('productos')->where('sku', $sku)->select('id')->first();
                        if ($prodRow && isset($prodRow->id)) {
                            $p2 = DB::connection('mysql_second')->table('productoxproveedor')->where('producto_id', $prodRow->id)->select('price_produc')->first();
                            if ($p2 && isset($p2->price_produc)) $price = $p2->price_produc;
                        }
                    } catch (\Throwable $__) { }
                }
                $out->push([
                    'sku' => $sku,
                    'name' => $name,
                    'categoria' => $cat,
                    'bodega' => $r->bodega ?? '',
                    'ubicacion' => $r->ubicacion ?? '',
                    'estatus' => $r->estatus ?? '',
                    'stock' => (int)($r->stock ?? 0),
                    'price' => is_null($price) ? '' : (string)$price,
                ]);
            }
        } else {
            // Para roles no-admin, incluir TODOS los SKU pertenecientes a las categorías permitidas
            $patterns = [];
            if ($isHseq) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.hseq')));
            } elseif ($isTalento) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.talento')));
            }

            $skuList = collect();
            if (!empty($patterns)) {
                // traer desde tabla externa productos
                $prodQuery = Producto::query();
                $prodQuery->where(function($q) use ($patterns) {
                    foreach ($patterns as $p) { if ($p !== '') $q->orWhere('categoria_produc', 'like', '%' . $p . '%'); }
                });
                $skuList = $skuList->merge($prodQuery->pluck('sku')->map(function($s){ return (string)$s; }));

                // traer locales desde GestionArticulos
                $extraQuery = GestionArticulos::query();
                $extraQuery->where(function($q) use ($patterns) {
                    foreach ($patterns as $p) { if ($p !== '') $q->orWhere('categoria', 'like', '%' . $p . '%'); }
                });
                $skuList = $skuList->merge($extraQuery->pluck('sku')->map(function($s){ return (string)$s; }));
            }

            $skuList = $skuList->unique()->values()->all();

            foreach ($skuList as $sku) {
                $prod = Producto::where('sku', $sku)->first();
                $name = $prod ? ($prod->name_produc ?? '') : (string) (GestionArticulos::where('sku', $sku)->value('nombre_articulo') ?? '');
                $cat = $prod ? ($prod->categoria_produc ?? '') : (string) (GestionArticulos::where('sku', $sku)->value('categoria') ?? '');

                // obtener stock agregado y una bodega/ubicacion representativa si existe
                $invRows = DB::connection('mysql_third')->table('inventarios as i')
                    ->leftJoin('ubicaciones as u','u.id','=','i.ubicaciones_id')
                    ->where('i.sku', $sku)
                    ->select('i.stock','i.estatus','u.bodega','u.ubicacion')
                    ->get();
                $stockSum = 0;
                $bodega = '';
                $ubicacion = '';
                $estatus = '';
                if ($invRows && !$invRows->isEmpty()) {
                    foreach ($invRows as $ir) { $stockSum += (int)($ir->stock ?? 0); }
                    $first = $invRows->first();
                    $bodega = $first->bodega ?? '';
                    $ubicacion = $first->ubicacion ?? '';
                    $estatus = $first->estatus ?? '';
                }

                // precio
                $price = null;
                try {
                    $p = DB::connection('mysql_second')->table('productoxproveedor')->where('sku', $sku)->select('price_produc')->first();
                    if ($p && isset($p->price_produc)) $price = $p->price_produc;
                } catch (\Throwable $_) {
                    try {
                        $prodRow = DB::connection('mysql_second')->table('productos')->where('sku', $sku)->select('id')->first();
                        if ($prodRow && isset($prodRow->id)) {
                            $p2 = DB::connection('mysql_second')->table('productoxproveedor')->where('producto_id', $prodRow->id)->select('price_produc')->first();
                            if ($p2 && isset($p2->price_produc)) $price = $p2->price_produc;
                        }
                    } catch (\Throwable $__) { }
                }

                $out->push([
                    'sku' => $sku,
                    'name' => $name,
                    'categoria' => $cat,
                    'bodega' => $bodega,
                    'ubicacion' => $ubicacion,
                    'estatus' => $estatus,
                    'stock' => (int)$stockSum,
                    'price' => is_null($price) ? '' : (string)$price,
                ]);
            }
        }

        // Si el usuario no es admin, filtrar por categorías permitidas según rol
        if (!$isAdmin) {
            $patterns = [];
            if ($isHseq) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.hseq')));
            } elseif ($isTalento) {
                $patterns = array_map('trim', explode(',', config('vpl.role_filters.talento')));
            }
            if (!empty($patterns)) {
                $out = $out->filter(function($row) use ($patterns) {
                    $cat = (string)($row['categoria'] ?? '');
                    foreach ($patterns as $p) {
                        if ($p === '') continue;
                        if (stripos($cat, $p) !== false) return true;
                    }
                    return false;
                })->values();
            }
        }

        // transformar a colección de filas simples (arrays en orden de headings)
        $rowsForExport = $out->map(function($i){
            return [
                $i['sku'], $i['name'], $i['categoria'], $i['bodega'], $i['ubicacion'], $i['estatus'], $i['stock'], $i['price']
            ];
        });

        return Excel::download(new InventariosExport(collect($rowsForExport)), 'inventario.xlsx');
    }

    /**
     * Obtener constancias de destrucción por SKU
     */
    public function obtenerConstanciasPorSku($sku)
    {
        try {
            // Verificar si la tabla existe
            $tableExists = DB::connection('mysql_third')
                ->select("SHOW TABLES LIKE 'log_destrucciones'");
            
            if (empty($tableExists)) {
                Log::warning('Tabla log_destrucciones no existe, buscando en carpeta directamente');
                return $this->obtenerConstanciasDeDirectorio($sku);
            }

            // Buscar en la tabla de log
            $destrucciones = DB::connection('mysql_third')
                ->table('log_destrucciones')
                ->where('sku', $sku)
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Destrucciones encontradas en BD', [
                'sku' => $sku,
                'count' => $destrucciones->count()
            ]);

            if ($destrucciones->isEmpty()) {
                // Si no hay en BD, buscar en carpeta
                Log::info('No hay registros en BD, buscando en carpeta');
                return $this->obtenerConstanciasDeDirectorio($sku);
            }

            $constancias = [];
            foreach ($destrucciones as $destruccion) {
                $rutaCompleta = storage_path('app/' . $destruccion->constancia_path);
                $existe = file_exists($rutaCompleta);
                
                Log::info('Verificando archivo', [
                    'path' => $destruccion->constancia_path,
                    'ruta_completa' => $rutaCompleta,
                    'existe' => $existe
                ]);
                
                if ($existe) {
                    $nombreArchivo = basename($destruccion->constancia_path);
                    $constancias[] = [
                        'id' => $destruccion->id,
                        'sku' => $destruccion->sku,
                        'cantidad' => $destruccion->cantidad,
                        'estatus_origen' => $destruccion->estatus_origen ?? 'N/A',
                        'usuario' => $destruccion->usuario ?? 'Sistema',
                        'fecha' => $destruccion->created_at,
                        'fecha_formateada' => date('d/m/Y H:i', strtotime($destruccion->created_at)),
                        'archivo' => $nombreArchivo,
                        'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $nombreArchivo]),
                        'tamano' => filesize($rutaCompleta),
                        'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2)
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total' => count($constancias),
                'constancias' => $constancias
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo constancias por SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Intentar buscar en directorio como fallback
            return $this->obtenerConstanciasDeDirectorio($sku);
        }
    }

    /**
     * Obtener constancias directamente del directorio (fallback)
     */
    private function obtenerConstanciasDeDirectorio($sku)
    {
        try {
            $directory = storage_path('app/constancias_destruccion');
            
            if (!file_exists($directory)) {
                Log::warning('Directorio de constancias no existe', ['path' => $directory]);
                return response()->json([
                    'success' => true,
                    'sku' => $sku,
                    'total' => 0,
                    'constancias' => [],
                    'message' => 'No hay constancias registradas'
                ]);
            }

            $archivos = array_diff(scandir($directory), ['.', '..']);
            $constancias = [];

            foreach ($archivos as $archivo) {
                // Verificar que el archivo pertenece al SKU buscado
                if (strpos($archivo, $sku) === 0 && pathinfo($archivo, PATHINFO_EXTENSION) === 'pdf') {
                    $rutaCompleta = $directory . '/' . $archivo;
                    
                    // Extraer información del nombre del archivo: SKU_FECHA_TIMESTAMP.pdf
                    $partes = explode('_', pathinfo($archivo, PATHINFO_FILENAME));
                    $fecha = count($partes) >= 2 ? $partes[1] : date('Y-m-d');
                    
                    $constancias[] = [
                        'id' => null,
                        'sku' => $sku,
                        'cantidad' => 'N/A',
                        'estatus_origen' => 'N/A',
                        'usuario' => 'Sistema',
                        'fecha' => $fecha,
                        'fecha_formateada' => date('d/m/Y', strtotime($fecha)),
                        'archivo' => $archivo,
                        'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $archivo]),
                        'tamano' => filesize($rutaCompleta),
                        'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2)
                    ];
                }
            }

            // Ordenar por fecha descendente
            usort($constancias, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            Log::info('Constancias encontradas en directorio', [
                'sku' => $sku,
                'count' => count($constancias)
            ]);

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total' => count($constancias),
                'constancias' => $constancias,
                'source' => 'directorio'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo constancias de directorio', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener constancias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar constancia de destrucción
     */
    public function descargarConstancia($nombreArchivo)
    {
        $ruta = storage_path('app/constancias_destruccion/' . $nombreArchivo);
        
        if (!file_exists($ruta)) {
            abort(404, 'Constancia no encontrada');
        }
        
        return response()->download($ruta);
    }

    /**
     * Listar constancias de destrucción
     */
    public function listarConstancias()
    {
        $directory = storage_path('app/constancias_destruccion');
        
        if (!file_exists($directory)) {
            return response()->json([
                'success' => true,
                'constancias' => [],
                'message' => 'No hay constancias registradas'
            ]);
        }
        
        $archivos = array_diff(scandir($directory), ['.', '..']);
        $constancias = [];
        
        foreach ($archivos as $archivo) {
            if (pathinfo($archivo, PATHINFO_EXTENSION) === 'pdf') {
                $rutaCompleta = $directory . '/' . $archivo;
                $constancias[] = [
                    'nombre' => $archivo,
                    'tamano' => filesize($rutaCompleta),
                    'tamano_mb' => round(filesize($rutaCompleta) / 1024 / 1024, 2),
                    'fecha_modificacion' => date('Y-m-d H:i:s', filemtime($rutaCompleta)),
                    'url_descarga' => route('articulos.constancia.descargar', ['archivo' => $archivo])
                ];
            }
        }
        
        // Ordenar por fecha de modificación descendente
        usort($constancias, function($a, $b) {
            return strtotime($b['fecha_modificacion']) - strtotime($a['fecha_modificacion']);
        });
        
        return response()->json([
            'success' => true,
            'total' => count($constancias),
            'directorio' => $directory,
            'constancias' => $constancias
        ]);
    }
}
