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
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->string('rol_entrega');
            $table->string('entrega_user');
            $table->string('tipo_entrega');
            $table->foreignId('usuarios_id')->constrained('usuarios_entregas')->onDelete('restrict');
            $table->foreignId('operacion_id')->constrained('sub_areas')->onDelete('restrict');
            $table->foreignId('recepciones_id')->nullable()->constrained('recepciones')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
