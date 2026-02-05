<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class periodicidad extends Model
{
    use HasFactory;

    protected $table = 'periodicidad';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sku',
        'nombre',
        'rol_periodicidad',
        'periodicidad',
        'aviso_rojo',
        'aviso_amarillo',
        'aviso_verde',
    ];

    
}