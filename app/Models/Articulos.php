<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Articulos extends Model
{
    protected $connection = 'mysql_second';
    protected $table = 'articulos';

    protected $fillable = [
        'sku',
        'cantidad'
    ];
}