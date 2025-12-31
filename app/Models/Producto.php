<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $connection = 'mysql_second';
    protected $table = 'productos';
    public $timestamps = false;

    protected $fillable = [
        'sku',
        'name_produc',
        'categoria_produc'
    ];

    protected $primaryKey = 'sku';
    public $incrementing = false;
    protected $keyType = 'string';
}
