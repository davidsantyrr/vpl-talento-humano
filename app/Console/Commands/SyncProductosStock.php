<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProductosStock extends Command
{
    protected $signature = 'stock:sync-productos';

    protected $description = 'Sincroniza productos.stock_produc con la suma de inventarios.stock por SKU';

    public function handle(): int
    {
        try {
            $prodModel = new Producto();
            $prodConn = $prodModel->getConnectionName() ?: config('database.default');
            $prodTable = $prodModel->getTable();

            $totales = DB::connection('mysql_third')
                ->table('inventarios')
                ->select('sku', DB::raw('SUM(stock) as total_stock'))
                ->groupBy('sku')
                ->get();

            $updated = 0;
            $seenSkus = [];

            foreach ($totales as $row) {
                $sku = (string) ($row->sku ?? '');
                if ($sku === '') {
                    continue;
                }

                $affected = DB::connection($prodConn)
                    ->table($prodTable)
                    ->where('sku', $sku)
                    ->update(['stock_produc' => (int) ($row->total_stock ?? 0)]);

                $updated += (int) $affected;
                $seenSkus[] = $sku;
            }

            $seenSkus = array_values(array_unique($seenSkus));

            $zeroed = 0;
            if (empty($seenSkus)) {
                $zeroed = (int) DB::connection($prodConn)
                    ->table($prodTable)
                    ->where('stock_produc', '!=', 0)
                    ->update(['stock_produc' => 0]);
            } else {
                $zeroed = (int) DB::connection($prodConn)
                    ->table($prodTable)
                    ->whereNotIn('sku', $seenSkus)
                    ->where('stock_produc', '!=', 0)
                    ->update(['stock_produc' => 0]);
            }

            $this->info('Sincronizacion completada');
            $this->line('SKUs en inventario: ' . count($seenSkus));
            $this->line('Filas actualizadas: ' . $updated);
            $this->line('Filas puestas en 0: ' . $zeroed);

            Log::info('stock:sync-productos ejecutado', [
                'updated' => $updated,
                'zeroed' => $zeroed,
                'sku_totales_inventario' => count($seenSkus),
                'productos_connection' => $prodConn,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error sincronizando stock: ' . $e->getMessage());
            Log::error('stock:sync-productos fallo', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
