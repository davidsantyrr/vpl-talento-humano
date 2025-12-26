<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoProducto extends Model
{
    protected $table = 'cargo_productos';
    protected $fillable = ['cargo_id', 'sku', 'name_produc'];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }
}
