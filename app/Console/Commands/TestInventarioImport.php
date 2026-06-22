<?php

namespace App\Console\Commands;

use App\Imports\InventariosImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TestInventarioImport extends Command
{
    protected $signature = 'test:import-inventario {file : Ruta del archivo CSV/XLSX} {mode : Modo de importación (set/add)}';
    protected $description = 'Prueba la importación de inventario desde un archivo';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $mode = $this->argument('mode');

        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: $filePath");
            return self::FAILURE;
        }

        if (!in_array($mode, ['set', 'add'])) {
            $this->error("Modo debe ser 'set' o 'add'");
            return self::FAILURE;
        }

        $this->info("Importando archivo: $filePath");
        $this->info("Modo: $mode");

        try {
            // Ejecutar importación
            Excel::import(new InventariosImport($mode), $filePath);
            $this->info('✓ Importación completada exitosamente');

            // Verificar resultados
            $this->line('');
            $this->info('Verificando stock en base de datos...');

            $inventarios = DB::connection('mysql_third')
                ->table('inventarios')
                ->select('sku', DB::raw('SUM(stock) as total_stock'))
                ->groupBy('sku')
                ->get();

            foreach ($inventarios as $inv) {
                $producto = DB::connection('mysql_second')->table('productos')->where('sku', $inv->sku)->first();
                $productStock = $producto?->stock_produc ?? 'NO EXISTE';
                $match = ($productStock == $inv->total_stock) ? '✓' : '✗';
                $this->line("$match SKU: {$inv->sku} | Inventarios: {$inv->total_stock} | Productos: {$productStock}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error en importación: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
