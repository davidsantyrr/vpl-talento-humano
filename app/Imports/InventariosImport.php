<?php

namespace App\Imports;

use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InventariosImport implements ToCollection, WithHeadingRow
{
    private string $mode = 'set'; // 'set' = reemplazar, 'add' = sumar al stock existente

    public function __construct(string $mode = 'set')
    {
        $this->mode = $mode === 'add' ? 'add' : 'set';
    }
    public $updated = 0;
    public $created = 0;
    public $skipped = 0;
    public $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $data = is_array($row) ? $row : $row->toArray();
            $isNumeric = array_key_exists(0, $data) || array_key_exists(1, $data);
            $normalized = $this->normalizeRow($data, $isNumeric);

            if ($this->isEmptyRow($normalized)) {
                continue;
            }

            $sku = trim((string)($normalized['sku'] ?? ''));
            if ($sku === '') {
                $this->skipped++;
                $this->errors[] = 'Fila ' . ($index + 2) . ': SKU faltante';
                continue;
            }

            $inventarioId = $this->parseInteger($normalized['inventario_id'] ?? null);
            $ubicacionesId = $this->parseInteger($normalized['ubicaciones_id'] ?? null);
            $stock = $this->parseInteger($normalized['stock']);
            $estatus = trim((string)($normalized['estatus'] ?? ''));
            $bodega = trim((string)($normalized['bodega'] ?? ''));
            $ubicacion = trim((string)($normalized['ubicacion'] ?? ''));
            $precio = $this->parseFloat($normalized['precio'] ?? $normalized['price'] ?? null);
            $name = trim((string)($normalized['nombre'] ?? $normalized['name'] ?? ''));
            $categoria = trim((string)($normalized['categoria'] ?? ''));

            try {
                DB::beginTransaction();

                $ubicacionesId = $this->resolveUbicacionId($ubicacionesId, $bodega, $ubicacion);
                $inventario = $this->findInventario($inventarioId, $sku, $ubicacionesId);

                if ($inventario) {
                    $updates = [];
                    if ($stock !== null) {
                        $updates['stock'] = $stock;
                    }
                    if ($estatus !== '') {
                        $updates['estatus'] = $estatus;
                    }
                    if ($ubicacionesId !== null) {
                        $updates['ubicaciones_id'] = $ubicacionesId;
                    }

                    if (!empty($updates)) {
                        // Si modo es 'add' y se incluye stock, sumar al existente en inventario en lugar de reemplazar
                        if ($this->mode === 'add' && array_key_exists('stock', $updates)) {
                            $updates['stock'] = ((int)($inventario->stock ?? 0)) + (int)$updates['stock'];
                        }
                        DB::connection('mysql_third')->table('inventarios')->where('id', $inventario->id)->update($updates);
                    }

                    // Mantener productos.stock_produc alineado con la suma actual en inventarios.
                    $this->syncStockProducto($sku);

                    if ($name !== '' || $categoria !== '') {
                        $this->syncProductoMeta($sku, $name, $categoria);
                    }

                    if ($precio !== null) {
                        $this->syncPrecio($sku, $precio);
                    }

                    $this->updated++;
                } else {
                    if ($stock === null) {
                        $this->skipped++;
                        $this->errors[] = 'Fila ' . ($index + 2) . ': stock no válido para crear inventario';
                        DB::rollBack();
                        continue;
                    }

                    $insertData = [
                        'sku' => $sku,
                        'stock' => $stock,
                        'estatus' => $estatus,
                        'ubicaciones_id' => $ubicacionesId,
                    ];

                    // Si el modo es 'add' y existe ya un registro de inventario para SKU+ubicacion, sumar stock en vez de insertar duplicado
                    if ($this->mode === 'add') {
                        $existingSame = DB::connection('mysql_third')->table('inventarios')
                            ->where('sku', $sku)
                            ->when($ubicacionesId !== null, function ($q) use ($ubicacionesId) { return $q->where('ubicaciones_id', $ubicacionesId); })
                            ->first();
                        if ($existingSame) {
                            DB::connection('mysql_third')->table('inventarios')->where('id', $existingSame->id)
                                ->update(['stock' => ((int)($existingSame->stock ?? 0)) + (int)$stock]);
                            // sincronizar stock_produc inmediatamente
                            $this->syncStockProducto($sku);
                            $this->created++;
                            DB::commit();
                            continue;
                        }
                    }

                    DB::connection('mysql_third')->table('inventarios')->insert($insertData);

                    // Mantener productos.stock_produc alineado con la suma actual en inventarios.
                    $this->syncStockProducto($sku);

                    if ($name !== '' || $categoria !== '') {
                        $this->syncProductoMeta($sku, $name, $categoria);
                    }
                    if ($precio !== null) {
                        $this->syncPrecio($sku, $precio);
                    }

                    $this->created++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('InventariosImport error fila', ['fila' => $index + 2, 'sku' => $sku, 'error' => $e->getMessage()]);
                $this->skipped++;
                $this->errors[] = 'Fila ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }
    }

    private function normalizeRow(array $data, bool $isNumeric): array
    {
        if ($isNumeric) {
            return [
                'inventario_id' => $data[0] ?? null,
                'ubicaciones_id' => $data[1] ?? null,
                'sku' => $data[2] ?? null,
                'nombre' => $data[3] ?? null,
                'categoria' => $data[4] ?? null,
                'bodega' => $data[5] ?? null,
                'ubicacion' => $data[6] ?? null,
                'estatus' => $data[7] ?? null,
                'stock' => $data[8] ?? null,
                'precio' => $data[9] ?? null,
            ];
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            $cleanKey = $this->normalizeKey((string)$key);
            $normalized[$cleanKey] = $value;
        }

        $aliases = [
            'inventario_id' => ['inventario_id', 'id_inventario', 'inventario id', 'inventarioid'],
            'ubicaciones_id' => ['ubicaciones_id', 'id_ubicaciones', 'ubicacion_id', 'ubicaciones id', 'id ubicacion'],
            'sku' => ['sku', 'codigo', 'codigo_sku'],
            'nombre' => ['nombre', 'name', 'descripcion'],
            'categoria' => ['categoria', 'category'],
            'bodega' => ['bodega', 'warehouse'],
            'ubicacion' => ['ubicacion', 'location'],
            'estatus' => ['estatus', 'status', 'estado'],
            'stock' => ['stock', 'cantidad', 'qty'],
            'precio' => ['precio', 'price', 'valor'],
        ];

        $mapped = [];
        foreach ($aliases as $field => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $normalized) && $normalized[$key] !== null && $normalized[$key] !== '') {
                    $mapped[$field] = $normalized[$key];
                    break;
                }
            }
        }

        return $mapped;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim(mb_strtolower($key));
        $key = str_replace(['á','é','í','ó','ú','ñ','ü',' ','-','.'], ['a','e','i','o','u','n','u','_','_','_'], $key);
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
        $key = preg_replace('/__+/', '_', $key);
        return trim($key, '_');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        // Soportar formatos comunes de Excel/csv como "1.000" o "1,000".
        $clean = trim((string)$value);
        $clean = str_replace([' ', ','], ['', '.'], $clean);
        if (is_numeric($clean)) {
            return (int)round((float)$clean);
        }

        return null;
    }

    private function parseFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace([',', '$', ' '], ['.', '', ''], (string)$value);
        if (is_numeric($clean)) {
            return (float)$clean;
        }

        return null;
    }

    private function findInventario(?int $inventarioId, string $sku, ?int $ubicacionesId)
    {
        $query = DB::connection('mysql_third')->table('inventarios');

        if ($inventarioId) {
            return $query->where('id', $inventarioId)->first();
        }

        $query->where('sku', $sku);
        if ($ubicacionesId) {
            $query->where('ubicaciones_id', $ubicacionesId);
        }

        return $query->first();
    }

    private function resolveUbicacionId(?int $ubicacionesId, string $bodega, string $ubicacion): ?int
    {
        if ($ubicacionesId) {
            $exists = DB::connection('mysql_third')->table('ubicaciones')->where('id', $ubicacionesId)->exists();
            if ($exists) {
                return $ubicacionesId;
            }
        }

        if ($bodega === '' && $ubicacion === '') {
            return $ubicacionesId;
        }

        $query = DB::connection('mysql_third')->table('ubicaciones');
        if ($bodega !== '') {
            $query->where('bodega', $bodega);
        }
        if ($ubicacion !== '') {
            $query->where('ubicacion', $ubicacion);
        }
        $existing = $query->first();
        if ($existing) {
            return $existing->id;
        }

        $insertId = DB::connection('mysql_third')->table('ubicaciones')->insertGetId([
            'bodega' => $bodega,
            'ubicacion' => $ubicacion,
        ]);

        return $insertId;
    }

    private function syncProductoMeta(string $sku, string $name, string $categoria): void
    {
        $updates = [];
        if ($name !== '') {
            $updates['name_produc'] = $name;
        }
        if ($categoria !== '') {
            $updates['categoria_produc'] = $categoria;
        }
        if (empty($updates)) {
            return;
        }

        Producto::where('sku', $sku)->update($updates);
    }

    private function syncPrecio(string $sku, float $precio): void
    {
        try {
            DB::connection('mysql_second')->table('productoxproveedor')->where('sku', $sku)->update(['price_produc' => $precio]);
        } catch (\Throwable $e) {
            Log::debug('InventariosImport syncPrecio', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    private function syncStockProducto(string $sku): void
    {
        try {
            $totalStock = (int) DB::connection('mysql_third')->table('inventarios')->where('sku', $sku)->sum('stock');
            Producto::where('sku', $sku)->update(['stock_produc' => $totalStock]);
        } catch (\Throwable $e) {
            Log::debug('InventariosImport syncStockProducto', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }
}
