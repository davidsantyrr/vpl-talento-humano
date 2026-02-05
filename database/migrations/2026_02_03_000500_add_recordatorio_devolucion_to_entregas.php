<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('entregas', 'recordatorio_devolucion_at')) {
            Schema::table('entregas', function (Blueprint $table) {
                $table->dateTime('recordatorio_devolucion_at')->nullable()->after('comprobante_path');
            });
        }
        if (!Schema::hasColumn('entregas', 'recordatorio_devolucion_enviado')) {
            Schema::table('entregas', function (Blueprint $table) {
                $table->boolean('recordatorio_devolucion_enviado')->default(false)->after('recordatorio_devolucion_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('entregas', 'recordatorio_devolucion_enviado')) {
            Schema::table('entregas', function (Blueprint $table) {
                $table->dropColumn('recordatorio_devolucion_enviado');
            });
        }
        if (Schema::hasColumn('entregas', 'recordatorio_devolucion_at')) {
            Schema::table('entregas', function (Blueprint $table) {
                $table->dropColumn('recordatorio_devolucion_at');
            });
        }
    }
};
