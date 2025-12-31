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
    public function usuarios()
    {
        return $this->hasMany(Usuarios::class, 'area_id');
    }
}

