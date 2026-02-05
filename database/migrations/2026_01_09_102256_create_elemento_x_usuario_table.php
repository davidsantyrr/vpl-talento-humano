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
        Schema::create('elemento_x_usuario', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('name_produc');
            $table->foreignId('usuarios_entregas_id')->constrained('usuarios_entregas')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elemento_x_usuario');
    }
};
