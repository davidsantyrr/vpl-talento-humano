<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class periodicidad extends Model
{
    protected $table = 'periodicidad';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nombre',
        'periodicidad',
        'aviso_rojo',
        'aviso_amarillo',
        'aviso_verde',
    ];

    
}