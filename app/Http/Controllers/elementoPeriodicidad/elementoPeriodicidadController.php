<?php

namespace App\Http\Controllers\elementoPeriodicidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ElementoPeriodicidadController extends Controller
{
    /**
     * Mostrar calendario anual de entregas periódicas (vista por año).
     */
    public function index(Request $request)
    {
        // Determinar filtro de rol por defecto desde el usuario en sesión
        $user = auth()->user();
        $roleFilter = $request->input('role') ?? null;
        if (!$roleFilter && $user) {
            $rolesSerialized = json_encode($user);
            $rolesClean = strtolower(str_replace(' ', '', $rolesSerialized));
            if (strpos($rolesClean, 'hseq') !== false) $roleFilter = 'hseq';
            elseif (strpos($rolesClean, 'talento') !== false || strpos($rolesClean, 'talentohumano') !== false) $roleFilter = 'talento';
        }
        $year = $request->input('year')
            ? intval($request->input('year'))
            : Carbon::now()->year;

        // Nombres de meses
        $spanishMonths = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $shortMonths = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        // Estructura de meses y semanas
        // Crear los 12 meses vacíos primero
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'label' => $spanishMonths[$m] . ' ' . $year,
                'weeks' => [],
            ];
        }

        // Construir semanas una sola vez para todo el año y asignarlas
        $firstWeekStart = Carbon::create($year, 1, 1)->startOfWeek(Carbon::MONDAY);
        $lastWeekStart = Carbon::create($year, 12, 31)->startOfWeek(Carbon::MONDAY);
        $cursor = $firstWeekStart->copy();
        while ($cursor->lte($lastWeekStart)) {
            $wkStart = $cursor->copy();
            $wkEnd = $wkStart->copy()->addDays(6);
            $key = $wkStart->format('Y-m-d');
            $mIndex = (int)$wkStart->format('n');
            // asignar la semana al mes correspondiente según el inicio de semana (lunes)
            $months[$mIndex]['weeks'][$key] = [
                'date' => $key,
                'label' => $wkStart->format('d') . ' ' . $shortMonths[$wkStart->month]
                    . ' - ' . $wkEnd->format('d') . ' ' . $shortMonths[$wkEnd->month],
                'products' => [],
                'total' => 0,
                'urgency' => 'empty',
            ];
            $cursor->addWeek();
        }

        // --- REEMPLAZO: obtener asignaciones unificadas ---
        $assignments = $this->buildAssignments();
        if ($roleFilter) {
            $rf = strtolower(str_replace(' ', '', $roleFilter));
            $assignments = array_values(array_filter($assignments, function($a) use ($rf) {
                $row = $a['periodicidad_row'] ?? null;
                if (!$row) return false;
                $r = strtolower(str_replace(' ', '', ($row['rol_periodicidad'] ?? $row['rol'] ?? '')));
                return $r !== '' && strpos($r, $rf) !== false;
            }));
        }
        // --- fin reemplazo ---

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();
        $nowWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        foreach ($assignments as $a) {
            try {
                $periodicidadVal = $a['periodicidad'] ?? null;
                $monthsStep = $this->monthsStepFromCode($periodicidadVal ?? null) ?? 1;
                $lastInfo = $this->getLastEntregaInfoByAssignment($a);
                $seed = $this->resolveSeedDate($lastInfo['fecha'] ?? null, $a['asg_created_at'] ?? null, $monthsStep);
                $cantidadEntrega = 1;
                if (!empty($a['cantidad'])) {
                    $cantidadEntrega = (int)$a['cantidad'];
                } elseif (!empty($lastInfo['cantidad'])) {
                    $cantidadEntrega = (int)$lastInfo['cantidad'];
                }
                $cantidadEntrega = max(1, $cantidadEntrega);

                // Si no hay periodicidad definida, no generar una serie mensual.
                // Solo mostrar la última entrega (si existe) dentro del año.
                if (empty($periodicidadVal)) {
                    if (empty($lastInfo['fecha'])) {
                        // nada que mostrar
                        continue;
                    }
                    $next = Carbon::parse($lastInfo['fecha'])->startOfDay();
                    if ($next->lt($yearStart) || $next->gt($yearEnd)) continue;
                    $wkStart = $next->copy()->startOfWeek(Carbon::MONDAY);
                    $wkKey = $wkStart->format('Y-m-d');

                    $mIndex = (int)$wkStart->format('n');
                    if (!isset($months[$mIndex]['weeks'][$wkKey])) {
                        $wkEnd = $wkStart->copy()->addDays(6);
                        $months[$mIndex]['weeks'][$wkKey] = [
                            'date' => $wkKey,
                            'label' => $wkStart->format('d') . ' ' . $shortMonths[$wkStart->month] . ' - ' . $wkEnd->format('d') . ' ' . $shortMonths[$wkEnd->month],
                            'products' => [], 'total' => 0, 'urgency' => 'empty'
                        ];
                    }

                    $sku = $a['sku'];
                    if (!isset($months[$mIndex]['weeks'][$wkKey]['products'][$sku])) {
                        $months[$mIndex]['weeks'][$wkKey]['products'][$sku] = [
                            'name' => $a['name_produc'] ?? $sku,
                            'quantity' => 0,
                            'users' => 0,
                            'periodicidad' => $a['periodicidad'] ?? null,
                            'monthsStep' => $monthsStep,
                            'periodicidad_row' => $a['periodicidad_row'] ?? null,
                            'next_date' => $next->toDateString(),
                        ];
                    }

                    $months[$mIndex]['weeks'][$wkKey]['products'][$sku]['quantity'] += $cantidadEntrega;
                    $months[$mIndex]['weeks'][$wkKey]['products'][$sku]['users'] += 1;
                    $months[$mIndex]['weeks'][$wkKey]['total'] += $cantidadEntrega;
                    continue;
                }

                // Calcular sólo la próxima entrega relevante (la primera >= hoy)
                $reference = Carbon::now()->startOfDay();
                $next = $seed->copy()->addMonths($monthsStep);
                $iter = 0;
                while ($next->lt($reference) && $iter < 500) { $next->addMonths($monthsStep); $iter++; }

                // si la próxima calculada no está dentro del año, ignorar
                if ($next->lt($yearStart) || $next->gt($yearEnd)) continue;

                $wkStart = $next->copy()->startOfWeek(Carbon::MONDAY);
                $wkKey = $wkStart->format('Y-m-d');
                if ((int)$wkStart->format('Y') !== (int)$year) continue;

                $mIndex = (int)$wkStart->format('n');
                if (!isset($months[$mIndex]['weeks'][$wkKey])) {
                    $wkEnd = $wkStart->copy()->addDays(6);
                    $months[$mIndex]['weeks'][$wkKey] = [
                        'date' => $wkKey,
                        'label' => $wkStart->format('d') . ' ' . $shortMonths[$wkStart->month] . ' - ' . $wkEnd->format('d') . ' ' . $shortMonths[$wkEnd->month],
                        'products' => [], 'total' => 0, 'urgency' => 'empty'
                    ];
                }

                $sku = $a['sku'];
                if (!isset($months[$mIndex]['weeks'][$wkKey]['products'][$sku])) {
                    $months[$mIndex]['weeks'][$wkKey]['products'][$sku] = [
                        'name' => $a['name_produc'] ?? $sku,
                        'quantity' => 0,
                        'users' => 0,
                        // incluir datos de periodicidad y fecha exacta de próxima entrega
                        'periodicidad' => $a['periodicidad'] ?? null,
                        'monthsStep' => $monthsStep,
                        'periodicidad_row' => $a['periodicidad_row'] ?? null,
                        'next_date' => $next->toDateString(),
                    ];
                } else {
                    if (empty($months[$mIndex]['weeks'][$wkKey]['products'][$sku]['next_date'])) {
                        $months[$mIndex]['weeks'][$wkKey]['products'][$sku]['next_date'] = $next->toDateString();
                    }
                }

                $months[$mIndex]['weeks'][$wkKey]['products'][$sku]['quantity'] += $cantidadEntrega;
                $months[$mIndex]['weeks'][$wkKey]['products'][$sku]['users'] += 1;
                $months[$mIndex]['weeks'][$wkKey]['total'] += $cantidadEntrega;

                Log::debug('ElementoPeriodicidad - próxima entrega asignada', ['sku'=>$sku,'wkKey'=>$wkKey,'next'=>$next->toDateString(),'added'=>$cantidadEntrega]);
            } catch (Exception $e) {
                Log::error('ElementoPeriodicidad.index error', ['msg'=>$e->getMessage(),'sku'=>$a['sku'] ?? null]);
                continue;
            }
        }

        // Semaforización usando next_date y avisos de periodicidad
        $now = Carbon::now()->startOfDay();
        foreach ($months as &$mdata) {
            foreach ($mdata['weeks'] as &$wdata) {
                if ($wdata['total'] === 0) { $wdata['urgency'] = 'empty'; continue; }

                $wkStart = Carbon::parse($wdata['date'])->startOfDay();
                $wkEnd = $wkStart->copy()->addDays(6)->endOfDay();

                if ($now->between($wkStart, $wkEnd)) { $wdata['urgency'] = 'urgent'; continue; }
                if ($now->gt($wkEnd)) { $wdata['urgency'] = 'urgent'; continue; }

                $weekPriority = 'ok'; $rank = ['urgent'=>4,'soon'=>3,'warning'=>2,'ok'=>1];
                foreach ($wdata['products'] as $p) {
                    $due = !empty($p['next_date']) ? Carbon::parse($p['next_date'])->startOfDay() : $wkStart;
                    $daysUntilDue = $now->lt($due) ? $now->diffInDays($due) : 0;

                    $urg = 'ok';
                    $thr = $this->parsePeriodicidadThresholds($p['periodicidad_row'] ?? null);
                    if ($thr) {
                        if ($thr['rojo'] !== null && $daysUntilDue <= $thr['rojo']) $urg = 'urgent';
                        elseif ($thr['amarillo'] !== null && $daysUntilDue <= $thr['amarillo']) $urg = 'soon';
                        elseif ($thr['verde'] !== null && $daysUntilDue <= $thr['verde']) $urg = 'warning';
                    } else {
                        $base = $this->thresholdDaysFromMonthsStep($p['monthsStep'] ?? 1);
                        if ($daysUntilDue <= $base) $urg = 'soon';
                        elseif ($daysUntilDue <= $base*2) $urg = 'warning';
                    }

                    if ($rank[$urg] > $rank[$weekPriority]) $weekPriority = $urg;
                    if ($weekPriority === 'urgent') break;
                }
                $wdata['urgency'] = $weekPriority;
            }
        }

        if ($request->input('debug')) {
            return response()->json(['year'=>$year,'months'=>$months]);
        }

        return view('ElementoPeriodicidad.ElementoPeriodicidad', [
            'year'     => $year,
            'months'   => $months,
            'prevYear' => $year - 1,
            'nextYear' => $year + 1,
        ]);
    }

    /**
     * Productos por semana (JSON)
     */
    public function productosPorSemana(Request $request)
    {
        $weekStart = $request->input('weekStart');
        if (!$weekStart) return response()->json(['success' => false, 'message' => 'Parámetro weekStart requerido'], 400);

        $start = Carbon::parse($weekStart)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();

        $assignments = $this->buildAssignments();
        $products = [];
        foreach ($assignments as $a) {
            try {
                $periodicidadVal = $a['periodicidad'] ?? null;
                $monthsStep = $this->monthsStepFromCode($periodicidadVal ?? null) ?: 1;
                $lastInfo = $this->getLastEntregaInfoByAssignment($a);
                $seed = $this->resolveSeedDate($lastInfo['fecha'] ?? null, $a['asg_created_at'] ?? null, $monthsStep);
                $cantidad = max(1, (int)($lastInfo['cantidad'] ?? 1));

                if (empty($periodicidadVal)) {
                    // No periodicidad: solo considerar la última entrega si cae en la semana
                    if (empty($lastInfo['fecha'])) continue;
                    $next = Carbon::parse($lastInfo['fecha']);
                    if ($next->between($start, $end)) {
                        if (!isset($products[$a['sku']])) $products[$a['sku']] = ['sku'=>$a['sku'],'name'=>$a['name_produc'] ?? $a['sku'],'quantity'=>0,'usersCount'=>0];
                        $products[$a['sku']]['quantity'] += $cantidad;
                        $products[$a['sku']]['usersCount'] += 1;
                    }
                    continue;
                }

                $next = $seed->copy()->addMonths($monthsStep);
                $iter = 0;
                while ($next->lt($start) && $iter < 500) { $next->addMonths($monthsStep); $iter++; }
                while ($next->lte($end) && $iter < 1000) {
                    if ($next->between($start, $end)) {
                        if (!isset($products[$a['sku']])) $products[$a['sku']] = ['sku'=>$a['sku'],'name'=>$a['name_produc'] ?? $a['sku'],'quantity'=>0,'usersCount'=>0];
                        $products[$a['sku']]['quantity'] += $cantidad;
                        $products[$a['sku']]['usersCount'] += 1;
                    }
                    $next->addMonths($monthsStep); $iter++;
                }
            } catch (Exception $e) { Log::debug('productosPorSemana item error',['msg'=>$e->getMessage()]); continue; }
        }
        return response()->json(['success'=>true,'weekStart'=>$weekStart,'products'=>array_values($products)]);
    }

    /**
     * Usuarios que deben recibir un SKU
     */
    public function usuariosForSku(Request $request, $sku)
    {
        $weekStart = $request->input('weekStart') ? Carbon::parse($request->input('weekStart'))->startOfDay() : null;
        $weekEnd   = $weekStart ? $weekStart->copy()->addDays(6)->endOfDay() : null;
        if (!$weekStart) return response()->json(['success'=>false,'message'=>'weekStart required'],400);

        $assignments = $this->buildAssignments();
        $users = [];
        foreach ($assignments as $a) {
            if ($a['sku'] !== $sku) continue;
            try {
                $periodicidadVal = $a['periodicidad'] ?? null;
                $monthsStep = $this->monthsStepFromCode($periodicidadVal ?? null) ?: 1;
                $lastInfo = $this->getLastEntregaInfoByAssignment($a);
                $seed = $this->resolveSeedDate($lastInfo['fecha'] ?? null, $a['asg_created_at'] ?? null, $monthsStep);
                $cantidad = max(1, (int)($lastInfo['cantidad'] ?? 1));

                if (empty($periodicidadVal)) {
                    // No periodicidad: solo considerar la última entrega si cae en la semana
                    if (empty($lastInfo['fecha'])) continue;
                    $next = Carbon::parse($lastInfo['fecha']);
                    if ($next->between($weekStart,$weekEnd)) {
                        $u = null;
                        if (!empty($a['usuarios_entregas_id'])) {
                            $u = DB::table('usuarios_entregas')->where('id',$a['usuarios_entregas_id'])->first();
                        } elseif (!empty($a['numero_documento'])) {
                            $u = DB::table('usuarios_entregas')->where('numero_documento',$a['numero_documento'])->first();
                        }
                        if ($u) $users[] = ['id'=>$u->id,'nombres'=>$u->nombres,'apellidos'=>$u->apellidos,'email'=>$u->email,'numero_documento'=>$u->numero_documento,'cantidad'=>$cantidad];
                    }
                    continue;
                }

                $next = $seed->copy()->addMonths($monthsStep);
                $iter = 0;
                while ($next->lt($weekStart) && $iter < 500) { $next->addMonths($monthsStep); $iter++; }
                if ($next->between($weekStart,$weekEnd)) {
                    $u = null;
                    if (!empty($a['usuarios_entregas_id'])) {
                        $u = DB::table('usuarios_entregas')->where('id',$a['usuarios_entregas_id'])->first();
                    } elseif (!empty($a['numero_documento'])) {
                        $u = DB::table('usuarios_entregas')->where('numero_documento',$a['numero_documento'])->first();
                    }
                    if ($u) $users[] = ['id'=>$u->id,'nombres'=>$u->nombres,'apellidos'=>$u->apellidos,'email'=>$u->email,'numero_documento'=>$u->numero_documento,'cantidad'=>$cantidad];
                }
            } catch (Exception $e) { Log::debug('usuariosForSku error',['msg'=>$e->getMessage()]); continue; }
        }
        return response()->json(['success'=>true,'users'=>$users]);
    }

    /**
     * Construye un listado plano de notificaciones próximas para enviar por correo.
     * Devuelve un array con elementos: sku, name, next_date, days_remaining, urgency, quantity, users (array) y rol_periodicidad.
     */
    public function upcomingNotifications(string $role = null): array
    {
        $out = [];
        $assignments = $this->buildAssignments();
        $now = Carbon::now()->startOfDay();
        if ($role) {
            $rf = strtolower(str_replace(' ', '', $role));
            $assignments = array_values(array_filter($assignments, function($a) use ($rf) {
                $row = $a['periodicidad_row'] ?? null;
                if (!$row) return false;
                $r = strtolower(str_replace(' ', '', ($row['rol_periodicidad'] ?? $row['rol'] ?? '')));
                return $r !== '' && strpos($r, $rf) !== false;
            }));
        }
        foreach ($assignments as $a) {
            try {
                $periodicidadVal = $a['periodicidad'] ?? null;
                $monthsStep = $this->monthsStepFromCode($periodicidadVal ?? null) ?: 1;
                $lastInfo = $this->getLastEntregaInfoByAssignment($a);
                $seed = $this->resolveSeedDate($lastInfo['fecha'] ?? null, $a['asg_created_at'] ?? null, $monthsStep);
                $cantidadEntrega = max(1, (int)($lastInfo['cantidad'] ?? 1));

                if (empty($periodicidadVal)) {
                    if (empty($lastInfo['fecha'])) continue;
                    $next = Carbon::parse($lastInfo['fecha'])->startOfDay();
                } else {
                    $next = $seed->copy()->addMonths($monthsStep);
                    $iter = 0;
                    while ($next->lt($now) && $iter < 500) { $next->addMonths($monthsStep); $iter++; }
                }

                $daysUntilDue = $now->lt($next) ? $now->diffInDays($next) : 0;

                $thr = $this->parsePeriodicidadThresholds($a['periodicidad_row'] ?? null);
                $urg = 'ok';
                if ($thr) {
                    if ($thr['rojo'] !== null && $daysUntilDue <= $thr['rojo']) $urg = 'urgent';
                    elseif ($thr['amarillo'] !== null && $daysUntilDue <= $thr['amarillo']) $urg = 'soon';
                    elseif ($thr['verde'] !== null && $daysUntilDue <= $thr['verde']) $urg = 'warning';
                } else {
                    $base = $this->thresholdDaysFromMonthsStep($monthsStep);
                    if ($daysUntilDue <= $base) $urg = 'soon';
                    elseif ($daysUntilDue <= $base*2) $urg = 'warning';
                }

                $weekStart = $next->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $req = new \Illuminate\Http\Request(['weekStart' => $weekStart]);
                $resp = $this->usuariosForSku($req, $a['sku']);
                $data = [];
                if ($resp instanceof \Illuminate\Http\JsonResponse) {
                    $data = json_decode($resp->getContent(), true);
                }
                $users = $data['users'] ?? [];

                // Prefer quantity specified in periodicidad row, then sum of assigned users, then last entrega cantidad
                $periodQty = isset($a['periodicidad_row']['cantidad']) ? (int)$a['periodicidad_row']['cantidad'] : null;
                if ($periodQty !== null && $periodQty > 0) {
                    $finalQty = $periodQty;
                } elseif (!empty($users) && is_array($users)) {
                    $finalQty = array_sum(array_map(function($u){ return max(1, (int)($u['cantidad'] ?? 1)); }, $users));
                } else {
                    $finalQty = $cantidadEntrega;
                }

                // determine threshold days for current urgency (prefer periodicidad row values)
                $thresholdDays = null;
                $thresholdName = null;
                if ($thr) {
                    if ($urg === 'urgent') { $thresholdDays = $thr['rojo']; $thresholdName = 'Rojo'; }
                    elseif ($urg === 'soon') { $thresholdDays = $thr['amarillo']; $thresholdName = 'Amarillo'; }
                    elseif ($urg === 'warning') { $thresholdDays = $thr['verde']; $thresholdName = 'Verde'; }
                } else {
                    $base = $this->thresholdDaysFromMonthsStep($monthsStep);
                    if ($urg === 'soon') { $thresholdDays = $base; $thresholdName = 'Amarillo'; }
                    elseif ($urg === 'warning') { $thresholdDays = $base * 2; $thresholdName = 'Verde'; }
                    elseif ($urg === 'urgent') { $thresholdDays = (int) floor($base/2); $thresholdName = 'Rojo'; }
                }

                $out[] = [
                    'sku' => $a['sku'],
                    'name' => $a['name_produc'] ?? ($a['sku'] ?? null),
                    'next_date' => $next->toDateString(),
                    'days_remaining' => $daysUntilDue,
                    'urgency' => $urg,
                    'quantity' => $finalQty,
                    'threshold_days' => $thresholdDays,
                    'threshold_color' => $thresholdName,
                    'users' => $users,
                    'rol_periodicidad' => $a['periodicidad_row']['rol_periodicidad'] ?? ($a['periodicidad_row']['rol'] ?? null),
                ];
            } catch (\Throwable $e) {
                Log::debug('upcomingNotifications item error', ['msg'=>$e->getMessage(), 'sku'=>$a['sku'] ?? null]);
                continue;
            }
        }

        return $out;
    }

    // --- helper: construir lista unificada de asignaciones (elemento_x_usuario + entregas históricas) ---
    private function buildAssignments(): array
    {
        $pairs = [];

        // elemento_x_usuario -> tomar numero_documento desde usuarios_entregas
        $asigs = DB::table('elemento_x_usuario')->select('sku','name_produc','usuarios_entregas_id','created_at')->get();
        foreach ($asigs as $r) {
            if (empty($r->usuarios_entregas_id)) continue;
            $periodRow = DB::table('periodicidad')->where('sku',$r->sku)->first();
            $doc = DB::table('usuarios_entregas')->where('id',$r->usuarios_entregas_id)->value('numero_documento');
            $key = $r->sku . '|' . ($r->usuarios_entregas_id ?: $doc ?: '0');
            $pairs[$key] = [
                'sku' => $r->sku,
                'name_produc' => $r->name_produc,
                'usuarios_entregas_id' => $r->usuarios_entregas_id,
                'cantidad' => isset($r->cantidad) ? (int)$r->cantidad : null,
                'numero_documento' => $doc,
                'asg_created_at' => $r->created_at,
                'periodicidad' => $periodRow->periodicidad ?? null,
                'periodicidad_row' => $periodRow ? (array)$periodRow : null,
            ];
        }

        // desde entregas históricas (si usuarios_id viene null, usar numero_documento)
        $userCol = null;
        if (Schema::hasColumn('entregas','usuarios_entregas_id')) $userCol = 'usuarios_entregas_id';
        elseif (Schema::hasColumn('entregas','usuarios_id')) $userCol = 'usuarios_id';

        $hasDoc = Schema::hasColumn('entregas','numero_documento');

        $entRows = DB::table('elemento_x_entrega as ee')
            ->join('entregas as en','ee.entrega_id','=','en.id')
            ->select(array_filter([
                'ee.sku',
                $userCol ? DB::raw("en.{$userCol} as usuarios_id") : null,
                $hasDoc ? 'en.numero_documento' : null
            ]))
            ->distinct()->get();

        foreach ($entRows as $r) {
            $uid = $userCol ? ($r->usuarios_id ?? null) : null;
            $doc = $hasDoc ? ($r->numero_documento ?? null) : null;
            if (!$uid && !$doc) continue;

            $periodRow = DB::table('periodicidad')->where('sku', $r->sku)->first();
            if (!$uid && $doc) { // intentar resolver id por documento
                $uid = DB::table('usuarios_entregas')->where('numero_documento',$doc)->value('id');
            }

            $key = $r->sku . '|' . ($uid ?: $doc);
            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'sku' => $r->sku,
                    'name_produc' => $periodRow->nombre ?? $r->sku,
                    'usuarios_entregas_id' => $uid,
                    'numero_documento' => $doc,
                    'asg_created_at' => null,
                    'periodicidad' => $periodRow->periodicidad ?? null,
                    'periodicidad_row' => $periodRow ? (array)$periodRow : null,
                ];
            }
        }

        return array_values($pairs);
    }

    // Normaliza códigos de periodicidad a meses (default = 1 si no reconocible)
    private function monthsStepFromCode($code): ?int
    {
        if ($code === null) return 1;
        if (is_int($code)) return in_array($code, [1,3,6,12], true) ? $code : 1;

        $norm = strtolower(trim((string)$code));
        $map = [
            '1' => 1, '3' => 3, '6' => 6, '12' => 12,
            '1_mes' => 1, '3_meses' => 3, '6_meses' => 6, '12_meses' => 12,
            'mensual' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12,
        ];
        if (isset($map[$norm])) return $map[$norm];

        if (preg_match('/(\d+)\s*mes/i', $norm, $m)) { $n = (int)$m[1]; return in_array($n, [1,3,6,12], true) ? $n : 1; }
        if (preg_match('/cada\s*(\d+)\s*mes/i', $norm, $m)) { $n = (int)$m[1]; return in_array($n, [1,3,6,12], true) ? $n : 1; }
        return 1;
    }

    // Última entrega por asignación (usa uid si existe; si no, numero_documento)
    private function getLastEntregaInfoByAssignment(array $a): array
    {
        try {
            $sku = (string)($a['sku'] ?? '');
            $uid = $a['usuarios_entregas_id'] ?? null;
            $doc = $a['numero_documento'] ?? null;

            $query = DB::table('elemento_x_entrega as ee')
                ->join('entregas as en', 'ee.entrega_id', '=', 'en.id')
                ->where('ee.sku', $sku);

            $hasUe = Schema::hasColumn('entregas', 'usuarios_entregas_id');
            $hasUi = Schema::hasColumn('entregas', 'usuarios_id');
            $hasDoc = Schema::hasColumn('entregas', 'numero_documento');

            $query->where(function($q) use ($uid,$doc,$hasUe,$hasUi,$hasDoc){
                if ($uid) {
                    if ($hasUe) $q->orWhere('en.usuarios_entregas_id', $uid);
                    if ($hasUi) $q->orWhere('en.usuarios_id', $uid);
                }
                if ($hasDoc && $doc) $q->orWhere('en.numero_documento', $doc);
            });

            $row = $query->selectRaw('COALESCE(en.fecha, en.created_at) as fecha_ref, COALESCE(ee.cantidad, 1) as cantidad')
                ->orderByRaw('COALESCE(en.fecha, en.created_at) DESC')
                ->first();

            if (!$row) return ['fecha' => null, 'cantidad' => null];
            return ['fecha' => Carbon::parse($row->fecha_ref), 'cantidad' => (int)$row->cantidad];
        } catch (\Throwable $e) {
            Log::debug('getLastEntregaInfoByAssignment error', ['msg' => $e->getMessage()]);
            return ['fecha' => null, 'cantidad' => null];
        }
    }

    // Fecha base: última entrega, o created_at de la asignación, o ahora (sin restar meses)
    private function resolveSeedDate($lastEntrega, $asgCreatedAt, int $monthsStep): Carbon
    {
        if ($lastEntrega instanceof Carbon) return $lastEntrega->copy();
        if (!empty($asgCreatedAt)) return Carbon::parse($asgCreatedAt);
        return Carbon::now();
    }

    // Avisos de periodicidad desde la fila periodicidad
    private function parsePeriodicidadThresholds($row): ?array
    {
        if (!is_array($row) || empty($row)) return null;
        $toInt = function($v) {
            if ($v === null) return null;
            if (is_numeric($v)) return (int)$v;
            if (is_string($v) && preg_match('/\d+/', $v, $m)) return (int)$m[0];
            return null;
        };
        return [
            'rojo' => $toInt($row['aviso_rojo'] ?? null),
            'amarillo' => $toInt($row['aviso_amarillo'] ?? null),
            'verde' => $toInt($row['aviso_verde'] ?? null),
        ];
    }

    private function thresholdDaysFromMonthsStep(int $monthsStep): int
    {
        switch ($monthsStep) {
            case 1: return 7;
            case 3: return 14;
            case 6: return 30;
            case 12: return 60;
            default: return 14;
        }
    }
}
