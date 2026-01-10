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
        $skus = ElementoXUsuario::select('sku', 'name_produc')->distinct()->get();

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

        $asignaciones = ElementoXUsuario::where('sku', $sku)->get();
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



