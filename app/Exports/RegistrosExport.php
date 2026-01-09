<?php

namespace App\Exports;

class RegistrosExport
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
        // Usar clave string para evitar referencias a clases no presentes en análisis estático
        return [
            'Maatwebsite\\Excel\\Events\\AfterSheet' => function($event) {
                try {
                    $sheet = $event->sheet->getDelegate();
                    $rowCount = count($this->rows) + 1;
                    // Estilos y autofilter
                    $sheet->getStyle("A1:I1")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
                    ]);
                    $sheet->getStyle("A2:I{$rowCount}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
                    ]);
                    $sheet->setAutoFilter("A1:I{$rowCount}");
                    foreach (range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
                } catch (\Throwable $e) {
                    // no-op: si la librería no está presente, ignorar
                }
            }
        ];
    }
}
