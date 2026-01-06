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
        if (!Schema::hasTable('periodicidad')) {
            Schema::create('periodicidad', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->nullable()->index();
                $table->string('nombre');
                $table->string('rol_periodicidad');
                $table->string('periodicidad');
                $table->string('aviso_rojo')->nullable();
                $table->string('aviso_amarillo')->nullable();
                $table->string('aviso_verde')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodicidad');
    }
};
