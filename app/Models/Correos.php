<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Correos extends Model
{
    protected $table = 'gestion_correos';
    public $timestamps = true;

    protected $fillable = [
        'rol',
        'correo',
    ];
}