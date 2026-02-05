<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreosNotificacion extends Model
{
    use HasFactory;

    protected $table = 'gestion_correos';

    protected $fillable = [
        'rol',
        'correo',
        'area',
    ];
}
