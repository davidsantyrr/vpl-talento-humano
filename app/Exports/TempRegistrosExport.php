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
        return array_map(function($r){
            $row = is_array($r) ? $r : (array)$r;
            return [
                $row['id'] ?? '',
                $row['tipo'] ?? '',
                $row['fecha'] ?? '',
                $row['numero_documento'] ?? '',
                $row['tipo_documento'] ?? '',
                $row['nombres'] ?? '',
                $row['apellidos'] ?? '',
                $row['operacion'] ?? '',
                $row['elementos'] ?? '',
            ];
        }, $this->rows);
    }

    public function headings(): array
    {
        return ['ID','Tipo','Fecha','Numero documento','Tipo documento','Nombres','Apellidos','Operacion','Elementos'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = count($this->rows) + 1;
                // Header style
                $sheet->getStyle("A1:I1")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                // Borders for data
                $sheet->getStyle("A2:I{$rowCount}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                // Autofilter
                $sheet->setAutoFilter("A1:I{$rowCount}");
                // Auto size
                foreach (range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
            }
        ];
    }
}
