<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RegistrosExport;

class HistorialController extends Controller
{
    // ...existing code...

    public function exportExcel(Request $request)
    {
        // Construir export basada en las tablas `entregas` y `recepciones` para
        // garantizar que campos como tipo, operacion, subtipo y area estén poblados.

        $q = $request->query('q');
        $operacionFilter = $request->query('operacion');
        $tipoRegistroFilter = $request->query('tipo_registro');
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        // Consultas para entregas
        $queryEntregas = DB::table('entregas')
            ->leftJoin('usuarios_entregas', 'entregas.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('area as usuario_area', 'usuarios_entregas.area_id', '=', 'usuario_area.id')
            ->leftJoin('usuarios_entregas as actor_entrega', function($join) {
                $join->on('actor_entrega.email', '=', 'entregas.entrega_email')
                     ->orOn(DB::raw("CONCAT(actor_entrega.nombres, ' ', actor_entrega.apellidos)"), '=', DB::raw('entregas.entrega_user'))
                     ->orOn('actor_entrega.numero_documento', '=', 'entregas.entrega_user');
            })
            ->leftJoin('area as actor_area', 'actor_entrega.area_id', '=', 'actor_area.id')
            ->leftJoin('sub_areas', 'entregas.sub_area_id', '=', 'sub_areas.id')
            ->select([
                'entregas.id',
                'entregas.created_at',
                DB::raw("'entrega' as registro_tipo"),
                DB::raw('COALESCE(entregas.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(entregas.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(entregas.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(entregas.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'entregas.tipo_entrega as tipo',
                'entregas.recibido',
                DB::raw('entregas.entrega_user as realizado_por'),
                DB::raw('COALESCE(actor_area.nombre_area, usuario_area.nombre_area) as nombre_area')
            ])
            ->whereNull('entregas.deleted_at');

        // Aplicar filtros a entregas
        if (!empty($q)) {
            $queryEntregas->where(function($qb) use ($q) {
                $qb->where('entregas.numero_documento', 'like', "%{$q}%")
                   ->orWhere('entregas.nombres', 'like', "%{$q}%")
                   ->orWhere('entregas.apellidos', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
        }
        if (!empty($operacionFilter)) {
            $queryEntregas->where('entregas.sub_area_id', $operacionFilter);
        }
        if (!empty($fechaInicio)) {
            $queryEntregas->whereDate('entregas.created_at', '>=', $fechaInicio);
        }
        if (!empty($fechaFin)) {
            $queryEntregas->whereDate('entregas.created_at', '<=', $fechaFin);
        }

        // Consultas para recepciones
        $queryRecepciones = DB::table('recepciones')
            ->leftJoin('usuarios_entregas', 'recepciones.usuarios_id', '=', 'usuarios_entregas.id')
            ->leftJoin('area as usuario_area', 'usuarios_entregas.area_id', '=', 'usuario_area.id')
            ->leftJoin('usuarios_entregas as actor_recepcion', function($join) {
                $join->on('actor_recepcion.email', '=', 'recepciones.recepcion_email')
                     ->orOn(DB::raw("CONCAT(actor_recepcion.nombres, ' ', actor_recepcion.apellidos)"), '=', DB::raw('recepciones.recepcion_user'))
                     ->orOn('actor_recepcion.numero_documento', '=', 'recepciones.recepcion_user');
            })
            ->leftJoin('area as actor_area', 'actor_recepcion.area_id', '=', 'actor_area.id')
            ->leftJoin('sub_areas', 'recepciones.operacion_id', '=', 'sub_areas.id')
            ->select([
                'recepciones.id',
                'recepciones.created_at',
                DB::raw("'recepcion' as registro_tipo"),
                DB::raw('COALESCE(recepciones.tipo_documento, usuarios_entregas.tipo_documento) as tipo_documento'),
                DB::raw('COALESCE(recepciones.numero_documento, usuarios_entregas.numero_documento) as numero_documento'),
                DB::raw('COALESCE(recepciones.nombres, usuarios_entregas.nombres) as nombres'),
                DB::raw('COALESCE(recepciones.apellidos, usuarios_entregas.apellidos) as apellidos'),
                'sub_areas.operationName as operacion',
                'recepciones.tipo_recepcion as tipo',
                'recepciones.entregado as recibido',
                DB::raw('recepciones.recepcion_user as realizado_por'),
                DB::raw('COALESCE(actor_area.nombre_area, usuario_area.nombre_area) as nombre_area')
            ])
            ->whereNull('recepciones.deleted_at');

        // Aplicar filtros a recepciones
        if (!empty($q)) {
            $queryRecepciones->where(function($qb) use ($q) {
                $qb->where('recepciones.numero_documento', 'like', "%{$q}%")
                   ->orWhere('recepciones.nombres', 'like', "%{$q}%")
                   ->orWhere('recepciones.apellidos', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.numero_documento', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.nombres', 'like', "%{$q}%")
                   ->orWhere('usuarios_entregas.apellidos', 'like', "%{$q}%");
            });
        }
        if (!empty($operacionFilter)) {
            $queryRecepciones->where('recepciones.operacion_id', $operacionFilter);
        }
        if (!empty($fechaInicio)) {
            $queryRecepciones->whereDate('recepciones.created_at', '>=', $fechaInicio);
        }
        if (!empty($fechaFin)) {
            $queryRecepciones->whereDate('recepciones.created_at', '<=', $fechaFin);
        }

        // Aplicar filtro de tipo_registro (si se solicita)
        $rows = collect();
        if (empty($tipoRegistroFilter) || $tipoRegistroFilter === 'entrega') {
            $rows = $rows->merge($queryEntregas->get());
        }
        if (empty($tipoRegistroFilter) || $tipoRegistroFilter === 'recepcion') {
            $rows = $rows->merge($queryRecepciones->get());
        }

        // ordenar por fecha
        $rows = $rows->sortByDesc('created_at')->values();

        return Excel::download(new RegistrosExport($rows), 'registros.xlsx');
    }

    // ...existing code...
}