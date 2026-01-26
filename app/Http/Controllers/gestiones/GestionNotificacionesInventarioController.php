<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\GestionNotificacionInventario;
use App\Models\Producto;
use App\Models\CorreosNotificacion;
use Illuminate\Support\Facades\Mail;
use App\Mail\InventarioBajo;

class GestionNotificacionesInventarioController extends Controller
{
    public function index()
    {
        $notificaciones = GestionNotificacionInventario::orderBy('id', 'desc')->get();

        // Determinar cargo del usuario en sesión para filtrar los elementos disponibles
        $cargoId = null;
        $user = session('auth.user') ?? null;
        if ($user) {
            if (is_array($user) && isset($user['cargo_id'])) {
                $cargoId = (int) $user['cargo_id'];
            } elseif (is_object($user) && isset($user->cargo_id)) {
                $cargoId = (int) $user->cargo_id;
            } elseif (is_array($user) && isset($user['email'])) {
                $u = \App\Models\Usuarios::where('email', $user['email'])->first();
                if ($u) $cargoId = (int) $u->cargo_id;
            } elseif (is_object($user) && isset($user->email)) {
                $u = \App\Models\Usuarios::where('email', $user->email)->first();
                if ($u) $cargoId = (int) $u->cargo_id;
            }
        }

        // Si el usuario es administrador debe ver todos los elementos
        $elementos = [];
        $isAdmin = false;
        // intentar obtener nombre del cargo si está disponible
        $cargoNombre = null;
        if ($cargoId) {
            $cargo = \App\Models\Cargo::find($cargoId);
            if ($cargo) {
                $cargoNombre = $cargo->nombre ?? null;
            }
        }
        // también intentar desde usuario si no se obtuvo nombre
        if (!$cargoNombre && $user) {
            $u = null;
            if (is_array($user) && isset($user['email'])) {
                $u = \App\Models\Usuarios::where('email', $user['email'])->first();
            } elseif (is_object($user) && isset($user->email)) {
                $u = \App\Models\Usuarios::where('email', $user->email)->first();
            }
            // si no se encontró por email, intentar buscar por id (muchas integraciones usan el mismo id)
            if (!$u && is_array($user) && isset($user['id'])) {
                $u = \App\Models\Usuarios::find((int)$user['id']);
            } elseif (!$u && is_object($user) && isset($user->id)) {
                $u = \App\Models\Usuarios::find((int)$user->id);
            }
            if ($u && $u->cargo) $cargoNombre = $u->cargo->nombre ?? null;
        }

        if ($cargoNombre) {
            $cn = mb_strtolower(trim($cargoNombre));
            if (strpos($cn, 'admin') !== false || strpos($cn, 'administrador') !== false) {
                $isAdmin = true;
            }
        }

        // Si todavía no es admin, inspeccionar el payload de sesión para roles detectados
        if (!$isAdmin && $user) {
            $checkInUser = null;
            $checkInUser = function($val) use (&$checkInUser) {
                if (is_string($val)) return mb_stripos($val, 'admin') !== false || mb_stripos($val, 'administrador') !== false;
                if (is_array($val)) {
                    foreach ($val as $v) if ($checkInUser($v)) return true;
                }
                if (is_object($val)) {
                    foreach (get_object_vars($val) as $v) if ($checkInUser($v)) return true;
                }
                return false;
            };
            if ($checkInUser($user)) $isAdmin = true;
        }

        // Detectar explícitamente si el usuario tiene rol 'talento' en el payload
        $isTalento = false;
        if ($user) {
            $checkTalento = null;
            $checkTalento = function($val) use (&$checkTalento) {
                if (is_string($val)) return mb_stripos($val, 'talento') !== false;
                if (is_array($val)) {
                    foreach ($val as $v) if ($checkTalento($v)) return true;
                }
                if (is_object($val)) {
                    foreach (get_object_vars($val) as $v) if ($checkTalento($v)) return true;
                }
                return false;
            };
            try { if ($checkTalento($user)) $isTalento = true; } catch (\Throwable $e) { /* ignore */ }
        }

        // Detectar explícitamente si el usuario tiene rol 'hseq' en el payload
        $isHseq = false;
        if ($user) {
            $checkHseq = null;
            $checkHseq = function($val) use (&$checkHseq) {
                if (is_string($val)) return mb_stripos($val, 'hseq') !== false;
                if (is_array($val)) {
                    foreach ($val as $v) if ($checkHseq($v)) return true;
                }
                if (is_object($val)) {
                    foreach (get_object_vars($val) as $v) if ($checkHseq($v)) return true;
                }
                return false;
            };
            try { if ($checkHseq($user)) $isHseq = true; } catch (\Throwable $e) { /* ignore */ }
        }

        // Si aún no tenemos cargoId, intentar mapearlo desde los roles o campos del usuario en sesión
        if (!$cargoId && $user) {
            try {
                $candidates = [];
                if (is_array($user)) {
                    $candidates = array_merge($candidates, array_values($user));
                    if (isset($user['roles']) && is_array($user['roles'])) {
                        foreach ($user['roles'] as $r) {
                            if (is_array($r)) $candidates = array_merge($candidates, array_values($r));
                            else $candidates[] = $r;
                        }
                    }
                } elseif (is_object($user)) {
                    $candidates = array_merge($candidates, array_values((array)$user));
                    if (isset($user->roles) && is_array($user->roles)) {
                        foreach ($user->roles as $r) {
                            if (is_object($r) || is_array($r)) $candidates = array_merge($candidates, array_values((array)$r));
                            else $candidates[] = $r;
                        }
                    }
                }

                foreach ($candidates as $val) {
                    if (!is_string($val)) continue;
                    $v = mb_strtolower(trim($val));
                    if (strpos($v, 'talento') !== false) {
                        $cargoRecord = \App\Models\Cargo::where('nombre', 'like', '%talento%')->first();
                        if ($cargoRecord) {
                            $cargoId = (int) $cargoRecord->id;
                            $cargoNombre = $cargoRecord->nombre;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // categorías consideradas como 'dotacion' (config)
        $talentoCategories = array_map('trim', explode(',', config('vpl.talento', 'Dotacion')));
        $talentoCategoriesLower = array_map(fn($v)=>mb_strtolower($v), $talentoCategories);
        // categorías para HSEQ (configurable)
        $hseqCategories = array_map('trim', explode(',', config('vpl.hseq', 'HSEQ,EPP')));
        $hseqCategoriesLower = array_map(fn($v)=>mb_strtolower($v), $hseqCategories);

        if ($isAdmin) {
            // Cargar todos los productos desde la tabla `productos` (conexión secundaria)
            $elementos = \App\Models\Producto::orderBy('name_produc')
                ->get(['sku','name_produc'])
                ->map(function($p){ return ['sku' => $p->sku, 'name' => $p->name_produc]; })
                ->toArray();
        } elseif ($cargoId) {
            // Cargar productos asignados al cargo (si existen)
            $rows = \App\Models\CargoProducto::where('cargo_id', $cargoId)
                ->orderBy('name_produc')
                ->get(['sku','name_produc']);

            // Si usuario de talento: preferir productos filtrados por categoria (dotacion)
            if ($isTalento) {
                try {
                    $skus = $rows->pluck('sku')->filter()->unique()->values()->toArray();
                    if (!empty($skus)) {
                        $rows = \App\Models\Producto::whereIn('sku', $skus)
                                ->where(function($q) use ($talentoCategoriesLower) {
                                    foreach ($talentoCategoriesLower as $c) {
                                        $q->orWhereRaw('LOWER(categoria_produc) LIKE ?', ['%'.$c.'%']);
                                    }
                                })
                            ->orderBy('name_produc')
                            ->get(['sku','name_produc']);
                    } else {
                        $rows = collect();
                    }
                } catch (\Throwable $e) {
                    // ignore and keep rows as-is
                }
            }
            // Si usuario HSEQ: preferir productos filtrados por categorias HSEQ/EPP
            elseif ($isHseq) {
                try {
                    $skus = $rows->pluck('sku')->filter()->unique()->values()->toArray();
                    if (!empty($skus)) {
                        $rows = \App\Models\Producto::whereIn('sku', $skus)
                                ->where(function($q) use ($hseqCategoriesLower) {
                                    foreach ($hseqCategoriesLower as $c) {
                                        $q->orWhereRaw('LOWER(categoria_produc) LIKE ?', ['%'.$c.'%']);
                                    }
                                })
                            ->orderBy('name_produc')
                            ->get(['sku','name_produc']);
                    } else {
                        $rows = collect();
                    }
                } catch (\Throwable $e) {
                    // ignore and keep rows as-is
                }
            }

            // Si para este cargo no hay productos, intentar buscar cargos relacionados con 'talento' y combinar
            if ($rows->isEmpty()) {
                try {
                    $talentoCargos = \App\Models\Cargo::whereRaw("LOWER(REPLACE(nombre,'_',' ')) LIKE ?", ['%talento%'])->pluck('id')->toArray();
                    if (!empty($talentoCargos)) {
                        $rows = \App\Models\CargoProducto::whereIn('cargo_id', $talentoCargos)
                            ->orderBy('name_produc')
                            ->get(['sku','name_produc']);
                        // si es talento, mapear a productos filtrados por categoria
                        if ($isTalento && $rows->isNotEmpty()) {
                            $skus2 = $rows->pluck('sku')->filter()->unique()->values()->toArray();
                            if (!empty($skus2)) {
                                $rows = \App\Models\Producto::whereIn('sku', $skus2)
                                        ->where(function($q) use ($talentoCategoriesLower) {
                                            foreach ($talentoCategoriesLower as $c) {
                                                $q->orWhereRaw('LOWER(categoria_produc) LIKE ?', ['%'.$c.'%']);
                                            }
                                        })
                                    ->orderBy('name_produc')
                                    ->get(['sku','name_produc']);
                            } else {
                                $rows = collect();
                            }
                        }
                        // si es HSEQ, mapear a productos filtrados por categorias HSEQ/EPP
                        if ($isHseq && $rows->isNotEmpty()) {
                            $skus2 = $rows->pluck('sku')->filter()->unique()->values()->toArray();
                            if (!empty($skus2)) {
                                $rows = \App\Models\Producto::whereIn('sku', $skus2)
                                        ->where(function($q) use ($hseqCategoriesLower) {
                                            foreach ($hseqCategoriesLower as $c) {
                                                $q->orWhereRaw('LOWER(categoria_produc) LIKE ?', ['%'.$c.'%']);
                                            }
                                        })
                                    ->orderBy('name_produc')
                                    ->get(['sku','name_produc']);
                            } else {
                                $rows = collect();
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $elementos = $rows->map(function($p){ return ['sku' => (string)($p->sku ?? ''), 'name' => (string)($p->name_produc ?? $p->name ?? $p->sku ?? '')]; })->toArray();
        }

        // Si aún no hay elementos y detectamos que el usuario es Talento Humano, cargar elementos de cargos con 'talento'
        if (empty($elementos) && $isTalento) {
            try {
                $talentoCargoIds = \App\Models\Cargo::whereRaw("LOWER(REPLACE(nombre,'_',' ')) LIKE ?", ['%talento%'])->pluck('id')->toArray();
                if (!empty($talentoCargoIds)) {
                    $rows = \App\Models\CargoProducto::whereIn('cargo_id', $talentoCargoIds)->orderBy('name_produc')->get(['sku','name_produc']);
                    $elementos = $rows->map(function($p){ return ['sku' => (string)($p->sku ?? ''), 'name' => (string)($p->name_produc ?? $p->sku ?? '')]; })->toArray();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Si aún no hay elementos y detectamos que el usuario es HSEQ, cargar elementos de cargos con 'hseq'
        if (empty($elementos) && $isHseq) {
            try {
                $hseqCargoIds = \App\Models\Cargo::whereRaw("LOWER(REPLACE(nombre,'_',' ')) LIKE ?", ['%hseq%'])->pluck('id')->toArray();
                if (!empty($hseqCargoIds)) {
                    $rows = \App\Models\CargoProducto::whereIn('cargo_id', $hseqCargoIds)->orderBy('name_produc')->get(['sku','name_produc']);
                    $elementos = $rows->map(function($p){ return ['sku' => (string)($p->sku ?? ''), 'name' => (string)($p->name_produc ?? $p->sku ?? '')]; })->toArray();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Si aún no hay elementos y detectamos talento, hacer fallback a productos filtrados por categoría (dotacion)
        if (empty($elementos) && $isTalento) {
            try {
                if (!empty($talentoCategoriesLower)) {
                    $placeholders = implode(',', array_fill(0, count($talentoCategoriesLower), '?'));
                    $rows = \App\Models\Producto::whereRaw('LOWER(categoria_produc) IN (' . $placeholders . ')', $talentoCategoriesLower)
                        ->orderBy('name_produc')
                        ->get(['sku','name_produc']);
                    $elementos = $rows->map(function($p){ return ['sku' => (string)$p->sku, 'name' => (string)$p->name_produc]; })->toArray();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Si aún no hay elementos y detectamos HSEQ, hacer fallback a productos filtrados por categorías HSEQ/EPP
        if (empty($elementos) && $isHseq) {
            try {
                if (!empty($hseqCategoriesLower)) {
                    $placeholders = implode(',', array_fill(0, count($hseqCategoriesLower), '?'));
                    $rows = \App\Models\Producto::whereRaw('LOWER(categoria_produc) IN (' . $placeholders . ')', $hseqCategoriesLower)
                        ->orderBy('name_produc')
                        ->get(['sku','name_produc']);
                    $elementos = $rows->map(function($p){ return ['sku' => (string)$p->sku, 'name' => (string)$p->name_produc]; })->toArray();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Log para depuración: ayudar a identificar por qué $elementos puede venir vacío
        try {
            Log::info('GestionNotificacionesInventario@index debug', [
                'cargoId' => $cargoId,
                'cargoNombre' => $cargoNombre,
                'isAdmin' => $isAdmin,
                'isTalento' => $isTalento,
                'isHseq' => $isHseq,
                'hseq_categories' => $hseqCategoriesLower,
                'elementos_count' => is_array($elementos) ? count($elementos) : 0,
                'elementos_sample' => is_array($elementos) ? array_slice($elementos, 0, 10) : [],
                'session_user_sample' => is_array($user) ? array_intersect_key($user, array_flip(['id','email','cargo_id','nombres'])) : (is_object($user) ? (array) array_intersect_key((array)$user, array_flip(['id','email','cargo_id','nombres'])) : null),
            ]);
        } catch (\Throwable $e) {
            // no bloquear la vista por fallos de logging
        }

        return view('gestiones.gestionNotificacionesInvetrario', compact('notificaciones', 'elementos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'elemento' => 'required|string|max:255',
            'stock' => 'required|string|max:255',
        ]);

        $n = GestionNotificacionInventario::create($data);
        // comprobación inmediata: si ya está por debajo, enviar correo
        try {
            $this->checkAndNotify($n);
        } catch (\Throwable $e) {
            Log::warning('Error al comprobar notificación recién creada', ['error' => $e->getMessage(), 'notif_id' => $n->id ?? null]);
        }
        return redirect()->route('gestionNotificacionesInventario.index')->with('success', 'Notificación creada.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'elemento' => 'required|string|max:255',
            'stock' => 'required|string|max:255',
        ]);

        $n = GestionNotificacionInventario::findOrFail($id);
        $n->update($data);
        return redirect()->route('gestionNotificacionesInventario.index')->with('success', 'Notificación actualizada.');
    }

    public function destroy($id)
    {
        $n = GestionNotificacionInventario::findOrFail($id);
        $n->delete();
        return redirect()->route('gestionNotificacionesInventario.index')->with('success', 'Notificación eliminada.');
    }

    /**
     * Comprueba una notificación y envía correo si el inventario está por debajo o igual al umbral.
     */
    protected function checkAndNotify(GestionNotificacionInventario $n)
    {
        $sku = (string) $n->elemento;
        $umbral = (int) $n->stock;

        $producto = Producto::where('sku', $sku)->first();
        if (!$producto) {
            $producto = Producto::whereRaw('LOWER(name_produc) = ?', [mb_strtolower($sku)])->first();
        }
        if (!$producto) return;

        $stockActual = (int) ($producto->stock_produc ?? 0);
        if ($stockActual <= $umbral) {
            // Priorizar el correo del usuario en sesión si está disponible
            $sessionUser = session('auth.user') ?? null;
            $sessionEmail = null;
            if ($sessionUser) {
                if (is_array($sessionUser) && isset($sessionUser['email'])) {
                    $sessionEmail = $sessionUser['email'];
                } elseif (is_object($sessionUser) && isset($sessionUser->email)) {
                    $sessionEmail = $sessionUser->email;
                } else {
                    try {
                        if (is_array($sessionUser) && isset($sessionUser['id'])) {
                            $u = \App\Models\Usuarios::find((int)$sessionUser['id']);
                            if ($u && $u->email) $sessionEmail = $u->email;
                        } elseif (is_object($sessionUser) && isset($sessionUser->id)) {
                            $u = \App\Models\Usuarios::find((int)$sessionUser->id);
                            if ($u && $u->email) $sessionEmail = $u->email;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            if ($sessionEmail) {
                $emails = [$sessionEmail];
            } else {
                $emails = CorreosNotificacion::whereRaw("LOWER(rol) LIKE ?", ['%hseq%'])->pluck('correo')->filter()->values()->toArray();
                if (empty($emails)) {
                    $from = config('mail.from.address');
                    if ($from) $emails = [$from];
                }
            }

            if (!empty($emails)) {
                $nombre = (string) ($producto->name_produc ?? $producto->name ?? $n->elemento);
                // enviar sólo si no se ha notificado aún
                if (!$n->notified) {
                    $m = new InventarioBajo($n->elemento, $producto->sku, $stockActual, $umbral);
                    $m->nombre = $nombre;
                    Mail::to($emails)->send($m);
                    $n->notified = true;
                    $n->last_notified_at = now();
                    $n->save();
                    Log::info('Inventario bajo - correo enviado (checkAndNotify)', ['sku' => $producto->sku, 'stock' => $stockActual, 'umbral' => $umbral, 'emails' => $emails, 'nombre' => $nombre]);
                }
            }
        } else {
            // resetear bandera si el stock volvió a superar el umbral
            if ($n->notified) {
                $n->notified = false;
                $n->save();
            }
        }
    }
}
