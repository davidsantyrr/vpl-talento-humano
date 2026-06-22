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
    protected $customHeadings;

    public function __construct(Collection $rows, $headings = null)
    {
        $this->rows = $rows;
        $this->customHeadings = $headings;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->customHeadings !== null) {
            return is_array($this->customHeadings) ? $this->customHeadings : [];
        }
        
        return [
            'Inventario ID',
            'Ubicacion ID',
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
        $columns = $this->customHeadings ?? [
            'Inventario ID',
            'Ubicacion ID',
            'SKU',
            'Nombre',
            'Categoria',
            'Bodega',
            'Ubicacion',
            'Estatus',
            'Stock',
            'Precio'
        ];
        
        $count = count($columns);
        $widths = [];
        
        if ($count === 2 && isset($columns[0]) && strtolower(str_replace(' ', '', $columns[0])) === 'sku') {
            // Plantilla simple: SKU, Stock
            return ['A' => 15, 'B' => 10];
        }
        
        // Inventario completo
        return [
            'A' => 12, // Inventario ID
            'B' => 12, // Ubicacion ID
            'C' => 15, // SKU
            'D' => 50, // Nombre (wrap enabled)
            'E' => 25, // Categoria
            'F' => 18, // Bodega
            'G' => 18, // Ubicacion
            'H' => 15, // Estatus
            'I' => 10, // Stock
            'J' => 14, // Precio
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
