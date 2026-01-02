<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElementoXRecepcion extends Model
{
    use HasFactory;

    protected $table = 'elemento_x_recepcion';

    protected $fillable = [
        'recepcion_id',
        'sku',
        'cantidad',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class, 'recepcion_id');
    }
}
