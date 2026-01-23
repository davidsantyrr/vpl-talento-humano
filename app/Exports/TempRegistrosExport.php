<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TempRegistrosExport implements FromArray, WithHeadings, WithEvents
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        return array_map(function ($r) {
            $row = is_array($r) ? $r : (array) $r;

            $nombres = $row['nombres'] ?? '';
            $apellidos = $row['apellidos'] ?? '';
            $nombreCompleto = trim($nombres . ' ' . $apellidos);

            return [
                $row['tipo'] ?? '',
                $row['fecha'] ?? '',
                $row['tipo_documento'] ?? '',
                $row['numero_documento'] ?? '',
                $nombreCompleto,
                $row['operacion'] ?? '',
                $row['subtipo'] ?? '',
                $row['estado'] ?? '',
                $row['realizo'] ?? '',
                $row['area'] ?? '',
            ];
        }, $this->rows);
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'Fecha',
            'Tipo Doc.',
            'Documento',
            'Nombre Completo',
            'Operación',
            'Subtipo',
            'Estado',
            'Realizó',
            'Área',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();
                $rowCount = max(1, count($this->rows)) + 1; // encabezado + datos (garantizar >=1)

                $headings = $this->headings();
                $numCols = count($headings);
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);
                $range = "A1:{$lastColumn}{$rowCount}";

                // 🔹 BORDES: aplicar explícitamente al cuerpo y al contorno
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

                // 🔹 ENCABEZADO AZUL (como el ejemplo) — usar ARGB y endColor para compatibilidad
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
                        'vertical'   => 'center',
                    ],
                ]);

                // Asegurar texto en negrita y color en encabezado individualmente
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));

                // 🔹 AUTOFILTRO PARA RANGO COMPLETO (encabezado + filas) — muestra los dropdowns en el encabezado
                $sheet->setAutoFilter("A1:{$lastColumn}{$rowCount}");

                // 🔹 FIJAR PANE Y AJUSTE DE ALTURA DEL ENCABEZADO
                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(20);

                // 🔹 AUTO AJUSTE DE COLUMNAS (dinámico, índice seguro)
                for ($i = 1; $i <= $numCols; $i++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
        ];
    }
}
