<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // inventarios indexes (on remote connection 'mysql_third')
            DB::connection('mysql_third')->statement('ALTER TABLE inventarios ADD INDEX idx_inventarios_sku (sku)');
        } catch (\Throwable $__) { }

        try {
            DB::connection('mysql_third')->statement('ALTER TABLE inventarios ADD INDEX idx_inventarios_ubicaciones_id (ubicaciones_id)');
        } catch (\Throwable $__) { }

        try {
            DB::statement('ALTER TABLE productos ADD INDEX idx_productos_sku (sku)');
        } catch (\Throwable $__) { }

        try {
            DB::connection('mysql_third')->statement('ALTER TABLE ubicaciones ADD INDEX idx_ubicaciones_bodega_ubicacion (bodega, ubicacion)');
        } catch (\Throwable $__) { }
    }

    public function down(): void
    {
        try { DB::connection('mysql_third')->statement('ALTER TABLE inventarios DROP INDEX idx_inventarios_sku'); } catch (\Throwable $__) { }
        try { DB::connection('mysql_third')->statement('ALTER TABLE inventarios DROP INDEX idx_inventarios_ubicaciones_id'); } catch (\Throwable $__) { }
        try { DB::statement('ALTER TABLE productos DROP INDEX idx_productos_sku'); } catch (\Throwable $__) { }
        try { DB::connection('mysql_third')->statement('ALTER TABLE ubicaciones DROP INDEX idx_ubicaciones_bodega_ubicacion'); } catch (\Throwable $__) { }
    }
};
