<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class InventariosExport implements FromCollection, WithHeadings, ShouldAutoSize, WithColumnWidths, WithStyles
{
    protected $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Nombre',
            'Categoria',
            'Bodega',
            'Ubicacion',
            'Estatus',
            'Stock',
            'Precio'
        ];
    }

    /**
     * Column widths (fixed maximums) to avoid an excessively wide column B.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // SKU
            'B' => 50, // Nombre (wrap enabled)
            'C' => 25, // Categoria
            'D' => 18, // Bodega
            'E' => 18, // Ubicacion
            'F' => 15, // Estatus
            'G' => 10, // Stock
            'H' => 14, // Precio
        ];
    }

    /**
     * Apply styles such as wrap-text for column B and header bold.
     */
    public function styles(Worksheet $sheet)
    {
        // Wrap text for entire column B so long names don't expand the column excessively
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        // Bold the header row
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        return [];
    }
}
