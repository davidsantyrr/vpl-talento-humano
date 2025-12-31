<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cargo_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operation')->cascadeOnDelete();
            $table->string('sku')->index();
            $table->string('name_produc');
            $table->timestamps();
            $table->unique(['cargo_id','operation_id','sku']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('cargo_productos');
    }
};