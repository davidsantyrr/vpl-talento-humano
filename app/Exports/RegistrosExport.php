<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RegistrosExport implements FromCollection, WithHeadings, WithEvents
{
	protected $rows;
	public function __construct(Collection $rows)
	{
		$this->rows = $rows;
	}

	public function collection()
	{
		return $this->rows->map(function($r) {
			// helpers / fallbacks
			$tipo = data_get($r,'registro_tipo','');
			$fecha = data_get($r,'created_at') ? \Carbon\Carbon::parse(data_get($r,'created_at'))->format('Y-m-d H:i') : '';
			$tipoDoc = data_get($r,'tipo_documento','');
			$numero = data_get($r,'numero_documento', '');
			// forzar texto en Excel (evitar notación científica) agregando apostrofe como prefijo
			if ($numero !== null && $numero !== '') {
				$numero = (string) $numero;
				if (!str_starts_with($numero, "'")) $numero = "'" . $numero;
			} else {
				$numero = '';
			}

			// nombre completo: fallback a nombre_completo si existe
			$nombre = trim((data_get($r,'nombres','') . ' ' . data_get($r,'apellidos','')));
			if (empty($nombre)) {
				$nombre = data_get($r,'nombre_completo', '') ?: data_get($r,'nombre', '');
			}

			$operacion = data_get($r,'operacion', '') ?: data_get($r,'operationName','');
			$subtipo = data_get($r,'tipo','') ?: data_get($r,'tipo_entrega','') ?: data_get($r,'tipo_recepcion','');

			// estado según tipo/flag
			$recibidoFlag = data_get($r,'recibido', data_get($r,'entregado', null));
			if ($tipo === 'entrega') {
				$estado = $recibidoFlag ? 'Recibido' : 'Pendiente';
			} elseif ($tipo === 'recepcion') {
				$estado = $recibidoFlag ? 'Entregado' : 'Pendiente';
			} else {
				$estado = $recibidoFlag ? 'Completo' : 'Pendiente';
			}

			// quien realizó: varios posibles campos
			$realizo = data_get($r,'realizado_por') ?: data_get($r,'entrega_user') ?: data_get($r,'recepcion_user') ?: data_get($r,'realizo') ?: '';

			// área: varios nombres posibles
			$area = data_get($r,'nombre_area') ?: data_get($r,'area') ?: data_get($r,'usuario_area') ?: data_get($r,'usuario_area_nombre') ?: '';

			return [
				'Tipo' => ucfirst($tipo),
				'Fecha' => $fecha,
				'Tipo Doc.' => $tipoDoc,
				'Documento' => $numero,
				'Nombre Completo' => $nombre,
				'Operación' => $operacion,
				'Subtipo' => ucfirst($subtipo),
				'Estado' => $estado,
				'Realizó' => $realizo,
				'Área' => $area,
			];
		});
	}

	public function headings(): array
	{
		return ['Tipo','Fecha','Tipo Doc.','Documento','Nombre Completo','Operación','Subtipo','Estado','Realizó','Área'];
	}

	public function registerEvents(): array
	{
		return [
			AfterSheet::class => function (AfterSheet $event) {

				$sheet = $event->sheet->getDelegate();
				$rowCount = max(1, $this->rows->count()) + 1;

				$headings = $this->headings();
				$numCols = count($headings);
				$lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);
				$range = "A1:{$lastColumn}{$rowCount}";

				$bodyRange = "A2:{$lastColumn}{$rowCount}";

				// Bordes internos del cuerpo
				$sheet->getStyle($bodyRange)->applyFromArray([
					'borders' => [
						'allBorders' => [
							'borderStyle' => Border::BORDER_THIN,
							'color' => ['argb' => 'FF000000'],
						],
					],
				]);

				// Contorno más grueso alrededor de todo el rango (incluye encabezado)
				$sheet->getStyle($range)->applyFromArray([
					'borders' => [
						'outline' => [
							'borderStyle' => Border::BORDER_MEDIUM,
							'color' => ['argb' => 'FF000000'],
						],
					],
				]);

				// Header style
				$headerRange = "A1:{$lastColumn}1";
				$sheet->getStyle($headerRange)->applyFromArray([
					'font' => [
						'bold' => true,
						'color' => ['argb' => 'FFFFFFFF'],
						'size' => 11,
					],
					'fill' => [
						'fillType' => Fill::FILL_SOLID,
						'startColor' => ['argb' => 'FF2F5597'],
						'endColor' => ['argb' => 'FF2F5597'],
					],
					'alignment' => [
						'horizontal' => 'center',
						'vertical' => 'center',
					],
				]);

				// Autofiltro para todo el rango y freeze header
				$sheet->setAutoFilter("A1:{$lastColumn}{$rowCount}");
				$sheet->freezePane('A2');
				$sheet->getRowDimension(1)->setRowHeight(20);

				for ($i = 1; $i <= $numCols; $i++) {
					$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
					$sheet->getColumnDimension($col)->setAutoSize(true);
				}

			},
		];
	}
}
