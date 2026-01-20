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
        // tablas candidatas en orden de preferencia
        $candidates = [
            'registros',
            'registros_entregas',
            'entregas',
            'entregas_recepciones',
            'historico',
            'historicos',
            'entrega_recepcion',
            'movimientos'
        ];

        $table = null;
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                $table = $t;
                break;
            }
        }

        if (!$table) {
            return redirect()->back()->with('error', 'No se encontró la tabla de registros para exportar.');
        }

        // columnas deseadas y selección segura (si falta la columna devolvemos cadena vacía)
        $desired = ['id','registro_tipo','created_at','tipo_documento','numero_documento','nombres','apellidos','operacion','tipo','recibido','realizado_por','nombre_area'];
        $selects = [];
        foreach ($desired as $col) {
            if (Schema::hasColumn($table, $col)) {
                $selects[] = "{$table}.{$col}";
            } else {
                $selects[] = DB::raw("'' as {$col}");
            }
        }

        $builder = DB::table($table)->select($selects);

        // filtros (solo si las columnas existen)
        if ($q = $request->query('q')) {
            $builder->where(function($qb) use ($q, $table) {
                if (Schema::hasColumn($table, 'nombres')) $qb->orWhere("{$table}.nombres", 'like', "%{$q}%");
                if (Schema::hasColumn($table, 'apellidos')) $qb->orWhere("{$table}.apellidos", 'like', "%{$q}%");
                if (Schema::hasColumn($table, 'numero_documento')) $qb->orWhere("{$table}.numero_documento", 'like', "%{$q}%");
            });
        }
        if ($op = $request->query('operacion') && Schema::hasColumn($table, 'operacion')) {
            $builder->where("{$table}.operacion", $op);
        }
        if ($tipo = $request->query('tipo_registro') && Schema::hasColumn($table, 'registro_tipo')) {
            $builder->where("{$table}.registro_tipo", $tipo);
        }
        if ($fi = $request->query('fecha_inicio') && Schema::hasColumn($table, 'created_at')) {
            $builder->whereDate("{$table}.created_at", '>=', $fi);
        }
        if ($ff = $request->query('fecha_fin') && Schema::hasColumn($table, 'created_at')) {
            $builder->whereDate("{$table}.created_at", '<=', $ff);
        }

        $rows = $builder->orderBy("{$table}.created_at", 'desc')->get();

        return Excel::download(new RegistrosExport($rows), 'registros.xlsx');
    }

    // ...existing code...
}