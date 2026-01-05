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
        Schema::create('periodicidad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('periodicidad', 50);
            $table->string('aviso_rojo', 50)->nullable();
            $table->string('aviso_amarillo', 50)->nullable();
            $table->string('aviso_verde', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodicidad');
    }
};
