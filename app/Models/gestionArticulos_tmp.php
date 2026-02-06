<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GestionArticulos extends Model
{
    // Tabla asociada (alineada con la migración create_gestion_articulos_table)
    protected $table = 'gestion_articulos';

    // Campos asignables en masa
    protected $fillable = [
        'sku',
        'cantidad',
        'nombre_articulo',
        'categoria'
    ];
}