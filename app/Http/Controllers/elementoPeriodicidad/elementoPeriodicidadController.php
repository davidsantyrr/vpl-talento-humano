<?php

namespace App\Http\Controllers\elementoPeriodicidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class elementoPeriodicidadController extends Controller
{
    /**
     * Mostrar calendario anual de entregas periódicas (vista por año).
     */
    public function index(Request $request)
    {
        $year = $request->input('year') ? intval($request->input('year')) : Carbon::now()->year;

        // Nombres de meses en español
        $spanishMonths = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $shortMonths = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

        // Preparar estructura de meses y semanas del año
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::create($year, $m, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $weeks = [];
            $cursor = $monthStart->copy()->startOfWeek();
            while ($cursor->lte($monthEnd)) {
                $wkStart = $cursor->copy();
                $wkEnd = $cursor->copy()->addDays(6);
                $key = $wkStart->format('Y-m-d');
                $weeks[$key] = [
                    'date' => $key,
                    'label' => $wkStart->format('d') . ' ' . $shortMonths[$wkStart->month] . ' - ' . $wkEnd->format('d') . ' ' . $shortMonths[$wkEnd->month],
                    'products' => [],
                    'total' => 0,
                    'urgency' => 'empty',
                ];
                $cursor->addWeek();
            }

            $months[$m] = [
                'label' => $spanishMonths[$m] . ' ' . $year,
                'weeks' => $weeks,
            ];
        }

        // Obtener asignaciones con periodicidad
        $assignments = DB::table('elemento_x_usuario as exu')
            ->join('periodicidad as p', 'exu.sku', 'p.sku')
            ->select('exu.sku', 'exu.name_produc', 'exu.usuarios_entregas_id', 'p.periodicidad')
            ->get();

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();
        $nowWeekStart = Carbon::now()->startOfWeek();

        foreach ($assignments as $a) {
            $user = DB::table('usuarios_entregas')->where('id', $a->usuarios_entregas_id)->first();
            if (!$user) continue;

            $lastEntrega = DB::table('elemento_x_entrega as ee')
                ->join('entregas as en', 'ee.entrega_id', 'en.id')
                ->where('en.usuarios_id', $a->usuarios_entregas_id)
                ->where('ee.sku', $a->sku)
                ->orderBy('en.created_at', 'desc')
                ->value('en.created_at');

            if (!$lastEntrega) continue;

            try {
                $dt = Carbon::parse($lastEntrega);
                $monthsStep = null;
                switch ($a->periodicidad) {
                    case '1_mes': $monthsStep = 1; break;
                    case '3_meses': $monthsStep = 3; break;
                    case '6_meses': $monthsStep = 6; break;
                    case '12_meses': $monthsStep = 12; break;
                    default: $monthsStep = null; break;
                }
                if (!$monthsStep) continue;

                $next = $dt->copy()->addMonths($monthsStep);
                $iter = 0;
                while ($next->lte($yearEnd) && $iter < 500) {
                    if ($next->year == $year) {
                        $mIndex = intval($next->format('n'));
                        $wkKey = $next->copy()->startOfWeek()->format('Y-m-d');

                        if (!isset($months[$mIndex]['weeks'][$wkKey])) {
                            // crear si la semana no existía por alguna razón
                            $wkStart = Carbon::parse($wkKey);
                            $wkEnd = $wkStart->copy()->addDays(6);
                            $months[$mIndex]['weeks'][$wkKey] = [
                                'date' => $wkKey,
                                'label' => $wkStart->format('d') . ' ' . $shortMonths[$wkStart->month] . ' - ' . $wkEnd->format('d') . ' ' . $shortMonths[$wkEnd->month],
                                'products' => [],
                                'total' => 0,
                                'urgency' => 'empty',
                            ];
                        }

                        if (!isset($months[$mIndex]['weeks'][$wkKey]['products'][$a->sku])) {
                            $months[$mIndex]['weeks'][$wkKey]['products'][$a->sku] = ['name' => $a->name_produc, 'count' => 0];
                        }
                        $months[$mIndex]['weeks'][$wkKey]['products'][$a->sku]['count']++;
                        $months[$mIndex]['weeks'][$wkKey]['total']++;
                    }

                    $next->addMonths($monthsStep);
                    $iter++;
                }

            } catch (Exception $e) {
                continue;
            }
        }

        // Calcular urgencia para cada semana (en semanas respecto a la semana actual)
        foreach ($months as $mi => &$mdata) {
            foreach ($mdata['weeks'] as $wk => &$wdata) {
                if ($wdata['total'] === 0) { $wdata['urgency'] = 'empty'; continue; }
                $wkStart = Carbon::parse($wdata['date']);
                $weeksUntil = $wkStart->gte($nowWeekStart) ? $nowWeekStart->diffInWeeks($wkStart) : 0;
                if ($weeksUntil === 0) $wdata['urgency'] = 'urgent';
                elseif ($weeksUntil === 1) $wdata['urgency'] = 'soon';
                elseif ($weeksUntil === 2) $wdata['urgency'] = 'warning';
                else $wdata['urgency'] = 'ok';
            }
            unset($wdata);
        }
        unset($mdata);

        return view('ElementoPeriodicidad.ElementoPeriodicidad', [
            'year' => $year,
            'months' => $months,
            'prevYear' => $year - 1,
            'nextYear' => $year + 1,
        ]);
    }

    /**
     * Devuelve productos y conteos para una semana (weekStart = YYYY-MM-DD).
     */
    public function productosPorSemana(Request $request)
    {
        $weekStart = $request->input('weekStart');
        if (!$weekStart) return response()->json(['success' => false, 'message' => 'Parámetro weekStart requerido'], 400);

        $start = Carbon::parse($weekStart)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        $assignments = DB::table('elemento_x_usuario as exu')
            ->join('periodicidad as p', 'exu.sku', 'p.sku')
            ->select('exu.sku', 'exu.name_produc', 'exu.usuarios_entregas_id', 'p.periodicidad')
            ->get();

        $products = [];

        foreach ($assignments as $a) {
            $lastEntrega = DB::table('elemento_x_entrega as ee')
                ->join('entregas as en', 'ee.entrega_id', 'en.id')
                ->where('en.usuarios_id', $a->usuarios_entregas_id)
                ->where('ee.sku', $a->sku)
                ->orderBy('en.created_at', 'desc')
                ->value('en.created_at');

            if (!$lastEntrega) continue;

            try {
                $dt = Carbon::parse($lastEntrega);
                $monthsStep = null;
                switch ($a->periodicidad) {
                    case '1_mes': $monthsStep = 1; break;
                    case '3_meses': $monthsStep = 3; break;
                    case '6_meses': $monthsStep = 6; break;
                    case '12_meses': $monthsStep = 12; break;
                    default: $monthsStep = null; break;
                }
                if (!$monthsStep) continue;

                $next = $dt->copy()->addMonths($monthsStep);
                $iter = 0;
                while ($next->lte($end) && $iter < 500) {
                    if ($next->gte($start) && $next->lte($end)) {
                        if (!isset($products[$a->sku])) $products[$a->sku] = ['sku' => $a->sku, 'name' => $a->name_produc, 'count' => 0];
                        $products[$a->sku]['count']++;
                    }
                    $next->addMonths($monthsStep);
                    $iter++;
                }

            } catch (Exception $e) {
                continue;
            }
        }

        $list = array_values($products);
        return response()->json(['success' => true, 'weekStart' => $weekStart, 'products' => $list]);
    }

    /**
     * Devuelve JSON con los usuarios que deben recibir un sku en una fecha o dentro de una semana.
     */
    public function usuariosForSku(Request $request, $sku)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date'))->format('Y-m-d') : null;
        $weekStart = $request->input('weekStart') ? Carbon::parse($request->input('weekStart'))->startOfDay() : null;
        $weekEnd = $weekStart ? $weekStart->copy()->addDays(6)->endOfDay() : null;

        $assignments = DB::table('elemento_x_usuario as exu')
            ->join('periodicidad as p', 'exu.sku', 'p.sku')
            ->select('exu.usuarios_entregas_id', 'exu.name_produc', 'p.periodicidad')
            ->where('exu.sku', $sku)
            ->get();

        $users = [];

        foreach ($assignments as $a) {
            $user = DB::table('usuarios_entregas')->where('id', $a->usuarios_entregas_id)->first();
            if (!$user) continue;

            $lastEntrega = DB::table('elemento_x_entrega as ee')
                ->join('entregas as en', 'ee.entrega_id', 'en.id')
                ->where('en.usuarios_id', $a->usuarios_entregas_id)
                ->where('ee.sku', $sku)
                ->orderBy('en.created_at', 'desc')
                ->value('en.created_at');

            if (!$lastEntrega) continue;

            try {
                $dt = Carbon::parse($lastEntrega);
                $monthsStep = null;
                switch ($a->periodicidad) {
                    case '1_mes': $monthsStep = 1; break;
                    case '3_meses': $monthsStep = 3; break;
                    case '6_meses': $monthsStep = 6; break;
                    case '12_meses': $monthsStep = 12; break;
                    default: $monthsStep = null; break;
                }
                if (!$monthsStep) continue;

                $next = $dt->copy()->addMonths($monthsStep);
                $iter = 0;

                if ($weekStart) {
                    while ($next->lte($weekEnd) && $iter < 500) {
                        if ($next->gte($weekStart) && $next->lte($weekEnd)) {
                            $users[] = [
                                'id' => $user->id,
                                'nombres' => $user->nombres,
                                'apellidos' => $user->apellidos,
                                'email' => $user->email,
                                'numero_documento' => $user->numero_documento,
                            ];
                            break;
                        }
                        $next->addMonths($monthsStep);
                        $iter++;
                    }
                } elseif ($date) {
                    $target = Carbon::parse($date);
                    while ($next->lte($target) && $iter < 500) {
                        if ($next->format('Y-m-d') === $target->format('Y-m-d')) {
                            $users[] = [
                                'id' => $user->id,
                                'nombres' => $user->nombres,
                                'apellidos' => $user->apellidos,
                                'email' => $user->email,
                                'numero_documento' => $user->numero_documento,
                            ];
                            break;
                        }
                        $next->addMonths($monthsStep);
                        $iter++;
                    }
                }

            } catch (Exception $e) {
                continue;
            }
        }

        return response()->json(['success' => true, 'users' => $users]);
    }
}
