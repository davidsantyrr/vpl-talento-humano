<?php

namespace App\Http\Controllers\elementoXusuario;

use App\Http\Controllers\Controller;
use App\Models\ElementoXUsuario;
use App\Models\ElementoXEntrega;
use App\Models\periodicidad;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class elementoXusuarioController extends Controller
{
    // Lista elementos y cantidad de entregas por semana
    public function index(Request $request)
    {
        // Scope por rol en sesión: obtener cargo(s) cuyo nombre coincide con roles
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
        $cargoIds = [];
        if (!empty($roleNames)) {
            $cargoIds = \DB::table('cargos')
                ->where(function($q) use ($roleNames){
                    foreach ($roleNames as $i => $rn) {
                        if ($i === 0) $q->whereRaw('LOWER(nombre) = ?', [$rn]);
                        else $q->orWhereRaw('LOWER(nombre) = ?', [$rn]);
                    }
                })
                ->pluck('id')
                ->toArray();
        }

        // Filtrar asignaciones por cargo del usuario destino
        $skusQuery = ElementoXUsuario::join('usuarios_entregas', 'elemento_x_usuario.usuarios_entregas_id', '=', 'usuarios_entregas.id')
            ->select('elemento_x_usuario.sku', 'elemento_x_usuario.name_produc')
            ->distinct();
        if (!empty($cargoIds)) {
            $skusQuery->whereIn('usuarios_entregas.cargo_id', $cargoIds);
        }
        $skus = $skusQuery->get();

        $elementos = $skus->map(function ($item) {
            $sku = $item->sku;
            $nombre = $item->name_produc;

            $period = periodicidad::where('sku', $sku)->first();
            $periodWeeks = $period ? (int) $period->periodicidad : null;

            $cantidadTotal = (float) ElementoXEntrega::where('sku', $sku)->sum(DB::raw('CAST(cantidad AS DECIMAL(10,2))'));

            $cantidadPorSemana = null;
            if ($periodWeeks && $periodWeeks > 0) {
                // entregas por semana = cantidadTotal / periodWeeks (redondear hacia arriba)
                $cantidadPorSemana = (int) ceil($cantidadTotal / $periodWeeks);
            }

            return (object) [
                'sku' => $sku,
                'nombre' => $nombre,
                'periodicidad_semanas' => $periodWeeks,
                'cantidad_por_entrega' => $cantidadPorSemana,
            ];
        });

        return view('elementoXusuario.elementos', compact('elementos'));
    }

    // Muestra los usuarios que deben recibir el SKU en la semana indicada
    public function verEntregasPorSemana(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) abort(400, 'sku requerido');

        $date = $request->input('date');
        $dt = $date ? Carbon::parse($date) : Carbon::now();
        $week = (int) $dt->weekOfYear;

        $period = periodicidad::where('sku', $sku)->first();
        $periodWeeks = $period ? (int) $period->periodicidad : 1;

        $tocaEntrega = $periodWeeks > 0 ? ($week % $periodWeeks === 0) : true;

        $asignacionesQuery = ElementoXUsuario::join('usuarios_entregas', 'elemento_x_usuario.usuarios_entregas_id', '=', 'usuarios_entregas.id')
            ->where('elemento_x_usuario.sku', $sku)
            ->select('elemento_x_usuario.*');
        if (!empty($cargoIds)) {
            $asignacionesQuery->whereIn('usuarios_entregas.cargo_id', $cargoIds);
        }
        $asignaciones = $asignacionesQuery->get();
        $usuarios = $asignaciones->map(function ($a) {
            return Usuarios::find($a->usuarios_entregas_id);
        })->filter();

        return view('elementoXusuario.entregas_por_semana', [
            'sku' => $sku,
            'week' => $week,
            'periodWeeks' => $periodWeeks,
            'tocaEntrega' => $tocaEntrega,
            'usuarios' => $usuarios,
        ]);
    }
}
/*<?php

namespace App\Http\Controllers\ElementoXUsuario;

use App\Http\Controllers\Controller;
use App\Models\ElementoXUsuario;
use Illuminate\Http\Request;

class ElementoXUsuarioController extends Controller
{
    public function index(Request $request)
    {
        $elementos = ElementoXUsuario::orderBy('id', 'desc')->get();

        return view('elementoXusuario.elementos', compact('elementos'));
    }

}*/



