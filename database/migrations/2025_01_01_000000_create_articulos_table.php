<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['sku']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('articulos');
    }
};