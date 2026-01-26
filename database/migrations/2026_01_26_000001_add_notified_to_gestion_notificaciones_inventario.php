<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotifiedToGestionNotificacionesInventario extends Migration
{
    public function up()
    {
        if (Schema::hasTable('gestion_notificaciones_inventario')) {
            Schema::table('gestion_notificaciones_inventario', function (Blueprint $table) {
                $table->boolean('notified')->default(false)->after('stock');
                $table->timestamp('last_notified_at')->nullable()->after('notified');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('gestion_notificaciones_inventario')) {
            Schema::table('gestion_notificaciones_inventario', function (Blueprint $table) {
                $table->dropColumn(['notified', 'last_notified_at']);
            });
        }
    }
}
