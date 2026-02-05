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
        if (!Schema::hasColumn('recepciones', 'comprobante_path')) {
            Schema::table('recepciones', function (Blueprint $table) {
                $table->string('comprobante_path')->nullable()->after('entregas_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('recepciones', 'comprobante_path')) {
            Schema::table('recepciones', function (Blueprint $table) {
                $table->dropColumn('comprobante_path');
            });
        }
    }
};
