<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $table = 'area';

    protected $fillable = [
        'nombre_area',
    ];

    // Make a virtual attribute `areaName` that maps to DB column `nombre_area`
    protected $appends = ['areaName'];

    public function getAreaNameAttribute()
    {
        return $this->attributes['nombre_area'] ?? null;
    }

    public function setAreaNameAttribute($value)
    {
        $this->attributes['nombre_area'] = $value;
    }
}
