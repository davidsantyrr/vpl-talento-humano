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
        if (!Schema::hasTable('gestion_correos')) return;

        Schema::table('gestion_correos', function (Blueprint $table) {
            if (!Schema::hasColumn('gestion_correos', 'area')) {
                $table->string('area')->nullable()->after('correo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('gestion_correos')) return;

        Schema::table('gestion_correos', function (Blueprint $table) {
            if (Schema::hasColumn('gestion_correos', 'area')) {
                $table->dropColumn('area');
            }
        });
    }
};
