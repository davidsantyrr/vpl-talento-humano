<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CentroCosto extends Model
{
    use HasFactory;

    protected $table = 'centro_costo';

    protected $fillable = [
        'nombre_centro_costo',
        'descripcion',
    ];

    // Make a virtual attribute `centroCostoName` that maps to DB column `nombre_centro_costo`
    protected $appends = ['centroCostoName'];

    public function getCentroCostoNameAttribute()
    {
        return $this->attributes['nombre_centro_costo'] ?? null;
    }

    public function setCentroCostoNameAttribute($value)
    {
        $this->attributes['nombre_centro_costo'] = $value;
    }
}
