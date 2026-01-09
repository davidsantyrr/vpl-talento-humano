<?php

// Fallbacks para que el analizador/linter no marque undefined types si las dependencias no están instaladas.

namespace Maatwebsite\Excel\Concerns {
    if (!\interface_exists(FromArray::class)) {
        interface FromArray { public function array(): array; }
    }
    if (!\interface_exists(WithHeadings::class)) {
        interface WithHeadings { public function headings(): array; }
    }
    if (!\interface_exists(WithEvents::class)) {
        interface WithEvents { public function registerEvents(): array; }
    }
}

namespace Maatwebsite\Excel\Events {
    if (!\class_exists(AfterSheet::class)) {
        class AfterSheet {
            public $sheet;
            public function __construct($sheet = null) { $this->sheet = $sheet; }
        }
    }
}

namespace PhpOffice\PhpSpreadsheet\Style {
    if (!\class_exists(Fill::class)) {
        class Fill { const FILL_SOLID = 'solid'; }
    }
    if (!\class_exists(Border::class)) {
        class Border { const BORDER_THIN = 'thin'; }
    }
}
