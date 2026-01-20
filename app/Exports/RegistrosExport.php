<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RegistrosExport implements FromCollection, WithHeadings
{
	protected $rows;
	public function __construct(Collection $rows)
	{
		$this->rows = $rows;
	}

	public function collection()
	{
		return $this->rows->map(function($r) {
			return [
				'tipo' => data_get($r,'registro_tipo',''),
				'fecha' => data_get($r,'created_at') ? \Carbon\Carbon::parse(data_get($r,'created_at'))->format('Y-m-d H:i') : '',
				'tipo_documento' => data_get($r,'tipo_documento',''),
				'numero_documento' => data_get($r,'numero_documento',''),
				'nombre_completo' => trim((data_get($r,'nombres','') . ' ' . data_get($r,'apellidos',''))),
				'operacion' => data_get($r,'operacion',''),
				'subtipo' => data_get($r,'tipo',''),
				'estado' => (data_get($r,'registro_tipo') === 'entrega' ? (data_get($r,'recibido') ? 'Recibido' : 'Pendiente') : (data_get($r,'recibido') ? 'Entregado' : 'Pendiente')),
				'realizo' => data_get($r,'realizado_por',''),
				'area' => data_get($r,'nombre_area',''),
			];
		});
	}

	public function headings(): array
	{
		return ['Tipo','Fecha','Tipo Doc.','Documento','Nombre Completo','Operación','Subtipo','Estado','Realizó','Área'];
	}
}
