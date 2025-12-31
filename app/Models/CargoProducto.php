<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CargoProducto extends Model
{
    use HasFactory;

    protected $table = 'cargo_productos';
    protected $fillable = [
        'cargo_id',
        'sub_area_id',
        'sku',
        'name_produc',
    ];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function subArea()
    {
        return $this->belongsTo(SubArea::class, 'sub_area_id');
    }
}
