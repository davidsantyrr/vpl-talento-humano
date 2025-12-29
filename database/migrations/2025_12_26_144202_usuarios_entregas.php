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
        Schema::create('usuarios_entregas', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellidos')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('numero_documento');
            $table->string('email');
            $table->date('fecha_ingreso');
            $table->foreignId('operacion_id')->constrained('operation')->onDelete('cascade')->nullable();
            $table->foreignId('area_id')->constrained('area')->onDelete('cascade')->nullable();
            $table->timestamps();
            $table->softdeletes();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios_entregas');
    }
};
