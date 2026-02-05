<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RegistrosExport implements FromCollection, WithHeadings, WithEvents
{
	protected $rows;
	protected $firstSkuByRegistro = [];
	protected $productNames = [];
	public function __construct(Collection $rows)
	{
		$this->rows = $rows;

		// Preload first SKU per entrega/recepcion to avoid per-row DB lookups
		try {
			$entregaIds = $rows->filter(fn($r) => (data_get($r,'registro_tipo') === 'entrega' || data_get($r,'registro_type') === 'entrega' || data_get($r,'tipo') === 'entrega'))
				->pluck('id')->filter()->unique()->values()->all();

			$recepIds = $rows->filter(fn($r) => (data_get($r,'registro_tipo') === 'recepcion' || data_get($r,'registro_type') === 'recepcion' || data_get($r,'tipo') === 'recepcion'))
				->pluck('id')->filter()->unique()->values()->all();

			$skus = [];
			if (!empty($entregaIds)) {
				$ele = DB::table('elemento_x_entrega')->whereIn('entrega_id', $entregaIds)->orderBy('id')->get(['entrega_id','sku']);
				foreach ($ele as $row) {
					if (!isset($this->firstSkuByRegistro['entrega'][$row->entrega_id])) {
						$this->firstSkuByRegistro['entrega'][$row->entrega_id] = $row->sku;
						$skus[] = $row->sku;
					}
				}
			}
			if (!empty($recepIds)) {
				$ele = DB::table('elemento_x_recepcion')->whereIn('recepcion_id', $recepIds)->orderBy('id')->get(['recepcion_id','sku']);
				foreach ($ele as $row) {
					if (!isset($this->firstSkuByRegistro['recepcion'][$row->recepcion_id])) {
						$this->firstSkuByRegistro['recepcion'][$row->recepcion_id] = $row->sku;
						$skus[] = $row->sku;
					}
				}
			}

			$skus = array_values(array_unique(array_filter($skus)));
			if (!empty($skus)) {
				// prepare lowercased sku list for case-insensitive matching
				$lowerSkus = array_map(fn($s) => mb_strtolower(trim((string)$s)), $skus);
				$lowerSkus = array_values(array_unique(array_filter($lowerSkus)));
				if (!empty($lowerSkus)) {
					$prods = DB::table('productos')->whereIn(DB::raw('LOWER(sku)'), $lowerSkus)->select('sku','name_produc')->get();
					foreach ($prods as $p) {
						$this->productNames[mb_strtolower($p->sku)] = $p->name_produc;
					}
				}
			}
		} catch (\Throwable $e) {
			// ignore preload errors, fallback lookup will handle
		}
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

			// elemento entregado: intentar extraer nombre del primer elemento
			$elementName = '';
			try {
				$elems = data_get($r, 'elementos');
				$first = null;
				if (is_array($elems) || $elems instanceof \Illuminate\Support\Collection) {
					$first = count($elems) ? (is_object($elems[0]) ? $elems[0] : (object)$elems[0]) : null;
					if ($first) {
						$elementName = data_get($first, 'name') ?: data_get($first, 'name_produc') ?: '';
					}
				}
				// si no hay nombre, intentar usar el mapa precargado por id
				if (empty($elementName)) {
					$regId = data_get($r, 'id');
					$regTipo = data_get($r, 'registro_tipo') ?: data_get($r, 'registro_type') ?: data_get($r, 'tipo_registro');
					if ($regId) {
						$sku = null;
						if ($regTipo === 'entrega' && isset($this->firstSkuByRegistro['entrega'][$regId])) {
							$sku = $this->firstSkuByRegistro['entrega'][$regId];
						} elseif ($regTipo === 'recepcion' && isset($this->firstSkuByRegistro['recepcion'][$regId])) {
							$sku = $this->firstSkuByRegistro['recepcion'][$regId];
						}
						// fallback: intentar obtener sku desde objeto first si existe
						if (empty($sku) && isset($first)) {
							$sku = data_get($first,'sku');
						}
						// usar nombre precargado
						if (!empty($sku)) {
							$skuKey = mb_strtolower(trim((string)$sku));
							if (isset($this->productNames[$skuKey])) {
								$elementName = $this->productNames[$skuKey];
							} else {
								$elementName = $sku;
							}
						}
					}
				}
			} catch (\Throwable $e) {
				$elementName = '';
			}

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
				'Elemento' => $elementName,
			];
		});
	}

	public function headings(): array
	{
		return ['Tipo','Fecha','Tipo Doc.','Documento','Nombre Completo','Operación','Subtipo','Estado','Realizó','Elemento'];
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

				// No usar AutoFilter (evitar 'tabla dinámica') — dejar solo freeze header
				$sheet->freezePane('A2');
				$sheet->getRowDimension(1)->setRowHeight(22);

				// Asegurar bordes también en la cabecera para un aspecto de tabla corporativa
				$sheet->getStyle($headerRange)->applyFromArray([
					'borders' => [
						'allBorders' => [
							'borderStyle' => Border::BORDER_THIN,
							'color' => ['argb' => 'FF000000'],
						],
					],
				]);

				for ($i = 1; $i <= $numCols; $i++) {
					$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
					$sheet->getColumnDimension($col)->setAutoSize(true);
				}

			},
		];
	}
}
